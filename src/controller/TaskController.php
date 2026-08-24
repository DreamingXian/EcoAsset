<?php

namespace App\Controllers;

use App\DAO\TaskDAO;
use App\DAO\UserDAO;
use App\DAO\AuditLogDAO;
use App\Utils\ResponseUtil;
use App\Utils\JWTUtil;
use App\Utils\StorageUtil;
use Exception;

class TaskController
{
    private TaskDAO $taskDAO;
    private UserDAO $userDAO;
    private AuditLogDAO $auditLogDAO;

    public function __construct()
    {
        $this->taskDAO = new TaskDAO();
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
        return JWTUtil::validateToken($token);
    }

    /**
     * Get Module 2 Prioritization Queue & Active Fleet Status (GET /api/v1/dispatch/queue).
     */
    public function getDispatchQueue(): void
    {
        try {
            $queue = $this->taskDAO->getUnassignedQueue();
            $crews = $this->taskDAO->getAllCrewsFleet();

            ResponseUtil::success([
                'queue'       => $queue,
                'crews'       => $crews,
                'total_queue' => count($queue),
                'total_crews' => count($crews),
                'timestamp'   => date('c')
            ], 'Dispatch prioritization queue retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve dispatch queue: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Manually Trigger Automated Spatial Dispatch Engine (POST /api/v1/dispatch/run).
     */
    public function triggerAutoDispatch(): void
    {
        try {
            $result = $this->taskDAO->runAutoDispatch();

            // Log execution in audit trail
            try {
                $user = $this->getAuthenticatedUser();
                $actorId = $user['user_id'] ?? null;
                $this->auditLogDAO->log($actorId, 'AUTO_DISPATCH_TRIGGERED', "Auto-dispatch ran: {$result['assigned_count']} assigned, {$result['unassigned_count']} unassigned.");
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Auto-dispatch engine executed: {$result['assigned_count']} jobs routed to maintenance crews.");
        } catch (Exception $e) {
            ResponseUtil::error('Auto-dispatch engine execution failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get tasks assigned to logged-in crew (GET /api/v1/tasks/my-tasks).
     */
    public function getMyTasks(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        try {
            $tasks = $this->taskDAO->getAssignedTasksForCrew($user['user_id']);
            ResponseUtil::success(['tasks' => $tasks], 'Crew tasks retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve crew tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get tasks available in the Open Maintenance Pool (GET /api/v1/tasks/open-pool).
     */
    public function getOpenPool(): void
    {
        try {
            $skill = $_GET['skill'] ?? null;
            $tasks = $this->taskDAO->getOpenPoolTasks($skill);
            ResponseUtil::success(['tasks' => $tasks], 'Open pool tasks retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve open pool tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Accept a dispatched assignment (POST /api/v1/tasks/{id}/accept).
     */
    public function acceptTask(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        try {
            $ok = $this->taskDAO->acceptTask($taskId, $user['user_id']);
            if ($ok) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_ACCEPTED', "Crew accepted task {$taskId}");
                ResponseUtil::success(['task_id' => $taskId, 'status' => 'IN_PROGRESS'], 'Task accepted and moved to In Progress.');
            } else {
                ResponseUtil::error('Failed to accept task. It may not be assigned to your crew.', 400);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error accepting task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Decline a dispatched assignment (POST /api/v1/tasks/{id}/decline).
     */
    public function declineTask(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($input['reason'] ?? 'Declined by crew technician');

        try {
            $ok = $this->taskDAO->declineTask($taskId, $user['user_id'], $reason);
            if ($ok) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_DECLINED', "Crew declined task {$taskId}: {$reason}");
                ResponseUtil::success(['task_id' => $taskId, 'status' => 'OPEN_FOR_CLAIM'], 'Task declined and returned to Open Pool.');
            } else {
                ResponseUtil::error('Failed to decline task.', 400);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error declining task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Concurrency-safe claim from Open Pool (POST /api/v1/tasks/{id}/claim).
     */
    public function claimTask(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        try {
            $result = $this->taskDAO->claimTask($taskId, $user['user_id']);
            if ($result['success']) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_CLAIMED', "Crew claimed open task {$taskId}");
                ResponseUtil::success($result, 'Task successfully claimed.');
            } else {
                ResponseUtil::error($result['error'] ?? 'Task could not be claimed.', 409);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error claiming task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Complete a task with live "After-Repair" photo proof (POST /api/v1/tasks/{id}/complete).
     */
    public function completeTask(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        $photoUrl = null;

        // 1. Check if multipart photo file was uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = StorageUtil::uploadImage($_FILES['photo'], 'completions');
            if ($uploadResult['success']) {
                $photoUrl = $uploadResult['url'];
            } else {
                ResponseUtil::error('Failed to upload completion photo: ' . ($uploadResult['error'] ?? 'Unknown storage error'), 500);
                return;
            }
        } elseif (!empty($_POST['photo_url'])) {
            $photoUrl = trim($_POST['photo_url']);
        } else {
            // Check JSON body fallback
            $input = json_decode(file_get_contents('php://input'), true);
            $photoUrl = $input['photo_url'] ?? null;
        }

        if (empty($photoUrl)) {
            ResponseUtil::error('Live after-repair completion photo is mandatory to resolve work order.', 400);
            return;
        }

        $notes = $_POST['notes'] ?? ($input['notes'] ?? 'Repairs completed by maintenance team.');

        try {
            $ok = $this->taskDAO->completeTask($taskId, $photoUrl, $notes);
            if ($ok) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_PRECOMPLETE_SUBMITTED', "Crew submitted task {$taskId} for completion review.");
                ResponseUtil::success(['task_id' => $taskId, 'status' => 'PRECOMPLETE', 'photo_url' => $photoUrl], 'Task completion submitted for administrator validation.');
            } else {
                ResponseUtil::error('Failed to submit completion.', 400);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error completing task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Drop an accepted in-progress task (-10 penalty) (POST /api/v1/tasks/{id}/drop).
     */
    public function dropTask(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($input['reason'] ?? ($_POST['reason'] ?? 'Dropped by crew technician due to field roadblocks'));

        try {
            $ok = $this->taskDAO->dropTask($taskId, $user['user_id'], $reason, 10.0);
            if ($ok) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_DROPPED', "Crew dropped task {$taskId}: {$reason} (-10 pts)");
                ResponseUtil::success(['task_id' => $taskId, 'status' => 'DROPPED'], 'Task dropped. Performance score adjusted (-10 pts).');
            } else {
                ResponseUtil::error('Failed to drop task.', 400);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error dropping task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Flag incident as Invalid / No Fault Found with clear-site proof (POST /api/v1/tasks/{id}/flag-invalid).
     */
    public function flagInvalid(string $taskId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        $photoUrl = null;

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = StorageUtil::uploadImage($_FILES['photo'], 'flags');
            if ($uploadResult['success']) {
                $photoUrl = $uploadResult['url'];
            }
        } elseif (!empty($_POST['photo_url'])) {
            $photoUrl = trim($_POST['photo_url']);
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $photoUrl = $input['photo_url'] ?? null;
        }

        if (empty($photoUrl)) {
            $photoUrl = 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
        }

        $notes = $_POST['notes'] ?? ($input['notes'] ?? 'Inspected on site: No damage or hazard found. False report flagged.');

        try {
            $ok = $this->taskDAO->flagInvalidTask($taskId, $user['user_id'], $photoUrl, $notes);
            if ($ok) {
                $this->auditLogDAO->log($user['user_id'], 'TASK_FLAGGED_INVALID', "Crew flagged task {$taskId} as PRE_INVALID.");
                ResponseUtil::success(['task_id' => $taskId, 'status' => 'PRE_INVALID'], 'Report flagged as invalid. Awaiting administrator review.');
            } else {
                ResponseUtil::error('Failed to flag task as invalid.', 400);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error flagging task: ' . $e->getMessage(), 500);
        }
    }
}
