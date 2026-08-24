<?php
namespace App\Controllers;

use App\DAO\UserDAO;
use App\DAO\AuditLogDAO;
use App\Utils\ResponseUtil;
use Exception;

class CrewController
{
    private UserDAO $userDAO;
    private AuditLogDAO $auditLogDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
        $this->auditLogDAO = new AuditLogDAO();
    }

    /**
     * Inspect authorization header and return decoded user payload.
     */
    private function getAuthenticatedUser(): ?array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));

        if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        return \App\Utils\JWTUtil::validateToken($token);
    }

    /**
     * Get crew profile details (GET /api/v1/crew/profile).
     */
    public function getProfile(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        if ($user['role'] !== 'MAINTENANCE_CREW' && $user['role'] !== 'ADMINISTRATOR') {
            ResponseUtil::error('Access restricted to Maintenance Crew members.', 403);
            return;
        }

        try {
            $profile = $this->userDAO->getCrewProfile($user['user_id']);
            if (!$profile) {
                ResponseUtil::error('Crew profile not found.', 404);
                return;
            }

            ResponseUtil::success(['profile' => $profile], 'Crew profile retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve crew profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update crew trade capabilities, base location, or availability (PUT /api/v1/crew/profile).
     */
    public function updateProfile(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        if ($user['role'] !== 'MAINTENANCE_CREW' && $user['role'] !== 'ADMINISTRATOR') {
            ResponseUtil::error('Access restricted to Maintenance Crew members.', 403);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $body = json_decode($rawInput, true);

        if (!is_array($body)) {
            ResponseUtil::error('Invalid JSON payload provided.', 400);
            return;
        }

        try {
            $updated = $this->userDAO->updateCrewProfile($user['user_id'], $body);

            if ($updated) {
                // Audit log profile update
                try {
                    $this->auditLogDAO->log($user['user_id'], 'CREW_PROFILE_UPDATED', "Crew {$user['user_id']} updated capabilities/location.");
                } catch (Exception $e) {
                    // Non-blocking
                }

                $profile = $this->userDAO->getCrewProfile($user['user_id']);
                ResponseUtil::success(['profile' => $profile], 'Crew capabilities and duty status saved successfully.');
            } else {
                ResponseUtil::error('Failed to update crew profile.', 500);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error updating crew profile: ' . $e->getMessage(), 500);
        }
    }
}
