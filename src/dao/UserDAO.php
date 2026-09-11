<?php

namespace App\DAO;

use App\Config\Database;
use PDO;
use Exception;

class UserDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Parse PostgreSQL array literal (e.g. "{ELECTRICAL,STRUCTURAL}") into PHP array.
     */
    public static function parsePgArray(?string $pgArray): array
    {
        if (empty($pgArray) || $pgArray === '{}') {
            return [];
        }
        $trimmed = trim($pgArray, '{}');
        return array_filter(array_map('trim', explode(',', $trimmed)));
    }

    /**
     * Retrieve all registered users.
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT user_id, email, full_name, role, is_active, must_change_password, created_at 
                FROM users 
                ORDER BY created_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all internal staff users (Maintenance Crew, Administrator).
     */
    public function getAllInternalUsers(): array
    {
        $sql = "SELECT u.user_id, u.email, u.full_name, u.role, u.is_active, u.must_change_password, u.created_at,
                       mc.skill_tags, mc.availability_status, mc.performance_score, mc.station_region AS crew_station
                FROM users u
                LEFT JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE u.role != 'TOURIST'
                ORDER BY u.created_at DESC";
        $stmt = $this->db->query($sql);
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            if (isset($user['skill_tags']) && is_string($user['skill_tags'])) {
                $user['skill_tags'] = self::parsePgArray($user['skill_tags']);
            }
        }
        return $users;
    }

    /**
     * Retrieve a single user with role-specific profile details.
     */
    public function getUserById(string $userId): ?array
    {
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.full_name, u.role, u.is_active, u.must_change_password, u.created_at,
                       tp.reputation_score,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude, mc.station_region AS crew_station
                FROM users u
                LEFT JOIN tourist_profiles tp ON u.user_id = tp.user_id
                LEFT JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE u.user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();
        if ($user && isset($user['skill_tags']) && is_string($user['skill_tags'])) {
            $user['skill_tags'] = self::parsePgArray($user['skill_tags']);
        }
        return $user ?: null;
    }

    /**
     * Retrieve a user by email (includes password_hash for authentication).
     */
    public function getUserByEmail(string $email): ?array
    {
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.full_name, u.role, u.is_active, u.must_change_password, u.created_at,
                       tp.reputation_score,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude
                FROM users u
                LEFT JOIN tourist_profiles tp ON u.user_id = tp.user_id
                LEFT JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE LOWER(u.email) = LOWER(:email)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        if ($user && isset($user['skill_tags']) && is_string($user['skill_tags'])) {
            $user['skill_tags'] = self::parsePgArray($user['skill_tags']);
        }
        return $user ?: null;
    }

    /**
     * Retrieve users by role.
     */
    public function getUsersByRole(string $role): array
    {
        $sql = "SELECT user_id, email, full_name, role, is_active, must_change_password, created_at 
                FROM users 
                WHERE role = :role 
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve available maintenance crews filtered by required skill tag.
     */
    public function getAvailableCrewsBySkill(string $skillCategory): array
    {
        $sql = "SELECT u.user_id, u.email, u.full_name,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude, mc.station_region
                FROM users u
                JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE u.is_active = TRUE 
                  AND mc.availability_status = 'AVAILABLE'
                  AND :skill = ANY(mc.skill_tags)
                ORDER BY mc.performance_score DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['skill' => strtoupper($skillCategory)]);
        $crews = $stmt->fetchAll();
        foreach ($crews as &$crew) {
            if (isset($crew['skill_tags']) && is_string($crew['skill_tags'])) {
                $crew['skill_tags'] = self::parsePgArray($crew['skill_tags']);
            }
        }
        return $crews;
    }

    /**
     * Create a new user with corresponding role sub-profile.
     */
    public function createUser(
        string $email,
        string $password,
        string $fullName,
        string $role = 'TOURIST',
        array $extraData = []
    ): ?string {
        try {
            $this->db->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $mustChange = isset($extraData['must_change_password']) 
                ? ($extraData['must_change_password'] ? 'true' : 'false') 
                : ($role !== 'TOURIST' ? 'true' : 'false');

            $sql = "INSERT INTO users (email, password_hash, full_name, role, must_change_password) 
                    VALUES (:email, :password_hash, :full_name, :role, :must_change) 
                    RETURNING user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'email'         => strtolower(trim($email)),
                'password_hash' => $passwordHash,
                'full_name'     => trim($fullName),
                'role'          => $role,
                'must_change'   => $mustChange
            ]);

            $result = $stmt->fetch();
            $newUserId = $result['user_id'] ?? null;

            if (!$newUserId) {
                throw new Exception("Failed to insert base user record.");
            }

            // Insert role-specific profile records
            if ($role === 'TOURIST') {
                $reputation = $extraData['reputation_score'] ?? 100;
                $profileSql = "INSERT INTO tourist_profiles (user_id, reputation_score) VALUES (:id, :rep)";
                $profileStmt = $this->db->prepare($profileSql);
                $profileStmt->execute(['id' => $newUserId, 'rep' => $reputation]);
            } elseif ($role === 'MAINTENANCE_CREW') {
                $skillTags = $extraData['skill_tags'] ?? ['STRUCTURAL'];
                $skillsParam = empty($skillTags) ? '{}' : '{' . implode(',', array_map('strtoupper', $skillTags)) . '}';
                $lat = $extraData['current_latitude'] ?? 3.139003;
                $lng = $extraData['current_longitude'] ?? 101.686855;
                $station = $extraData['station_region'] ?? 'Kuala Lumpur';

                $crewSql = "INSERT INTO maintenance_crews 
                            (crew_id, skill_tags, availability_status, performance_score, current_latitude, current_longitude, station_region) 
                            VALUES (:id, :skills, 'AVAILABLE', 100.00, :lat, :lng, :station)";
                $crewStmt = $this->db->prepare($crewSql);
                $crewStmt->execute([
                    'id'      => $newUserId,
                    'skills'  => $skillsParam,
                    'lat'     => $lat,
                    'lng'     => $lng,
                    'station' => $station
                ]);
            }

            $this->db->commit();
            return $newUserId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in UserDAO::createUser: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user profile information.
     */
    public function updateUserProfile(string $userId, string $fullName, string $email): bool
    {
        $sql = "UPDATE users 
                SET full_name = :full_name, email = :email 
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'full_name' => trim($fullName),
            'email'     => strtolower(trim($email)),
            'user_id'   => $userId
        ]);
    }

    /**
     * Update password and clear must_change_password flag.
     */
    public function updatePassword(string $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $sql = "UPDATE users 
                SET password_hash = :password_hash, must_change_password = FALSE 
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'password_hash' => $passwordHash,
            'user_id'       => $userId
        ]);
    }

    /**
     * Activate or Deactivate a user account.
     */
    public function updateUserStatus(string $userId, bool $isActive): bool
    {
        $sql = "UPDATE users 
                SET is_active = :is_active 
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'is_active' => $isActive ? 'true' : 'false',
            'user_id'   => $userId
        ]);
    }

    /**
     * Update user details and role by Administrator.
     */
    public function updateUserByAdmin(string $userId, string $fullName, string $email, string $role): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE users 
                    SET full_name = :full_name, email = :email, role = :role 
                    WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'full_name' => trim($fullName),
                'email'     => strtolower(trim($email)),
                'role'      => $role,
                'user_id'   => $userId
            ]);

            // Ensure role-specific profiles exist if transitioned
            if ($role === 'MAINTENANCE_CREW') {
                $check = $this->db->prepare("SELECT crew_id FROM maintenance_crews WHERE crew_id = :id");
                $check->execute(['id' => $userId]);
                if (!$check->fetch()) {
                    $ins = $this->db->prepare("INSERT INTO maintenance_crews (crew_id, skill_tags, availability_status, performance_score, current_latitude, current_longitude, station_region) VALUES (:id, '{STRUCTURAL}', 'AVAILABLE', 100.00, 3.139003, 101.686855, 'Kuala Lumpur')");
                    $ins->execute(['id' => $userId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in UserDAO::updateUserByAdmin: " . $e->getMessage());
            return false;
        }
    }



    /**
     * Helper to serialize PHP array into PostgreSQL array string format.
     */
    public static function toPgArray(array $arr): string
    {
        if (empty($arr)) {
            return '{}';
        }
        $sanitized = array_map(function($item) {
            return '"' . addslashes(strtoupper(trim($item))) . '"';
        }, $arr);
        return '{' . implode(',', $sanitized) . '}';
    }

    /**
     * Retrieve complete maintenance crew profile and auto-initialize if record is missing.
     */
    public function getCrewProfile(string $crewId): ?array
    {
        $sql = "SELECT u.user_id, u.email, u.full_name, u.role, u.is_active,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude, mc.station_region
                FROM users u
                LEFT JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE u.user_id = :crew_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['crew_id' => $crewId]);
        $crew = $stmt->fetch();

        if (!$crew) {
            return null;
        }

        // Auto-create maintenance_crews record if not initialized yet
        if ($crew['availability_status'] === null) {
            $insert = "INSERT INTO maintenance_crews 
                       (crew_id, skill_tags, availability_status, performance_score, current_latitude, current_longitude, station_region)
                       VALUES (:id, '{}', 'AVAILABLE', 100.00, 3.1578, 101.7119, 'Kuala Lumpur')
                       ON CONFLICT (crew_id) DO NOTHING";
            $instStmt = $this->db->prepare($insert);
            $instStmt->execute(['id' => $crewId]);

            $crew['skill_tags'] = [];
            $crew['availability_status'] = 'AVAILABLE';
            $crew['performance_score'] = 100.00;
            $crew['current_latitude'] = 3.1578;
            $crew['current_longitude'] = 101.7119;
            $crew['station_region'] = 'Kuala Lumpur';
        } else {
            $crew['skill_tags'] = self::parsePgArray($crew['skill_tags']);
        }

        // Dynamically compute availability based on active in-progress and pending tasks
        $taskCheck = "SELECT 
                        COUNT(*) FILTER (WHERE r.status = 'IN_PROGRESS') as active_cnt,
                        COUNT(*) FILTER (WHERE r.status = 'PENDING_ACCEPTANCE') as pending_cnt
                      FROM maintenance_tasks t
                      JOIN damage_reports r ON t.report_id = r.report_id
                      WHERE t.assigned_crew_id = :crew_id";
        $taskStmt = $this->db->prepare($taskCheck);
        $taskStmt->execute(['crew_id' => $crewId]);
        $taskCounts = $taskStmt->fetch();

        $activeCnt = (int)($taskCounts['active_cnt'] ?? 0);
        $pendingCnt = (int)($taskCounts['pending_cnt'] ?? 0);

        if ($activeCnt > 0) {
            $computedStatus = 'BUSY';
        } elseif ($pendingCnt > 0) {
            $computedStatus = 'BUSY'; // busy in DB enum, flagged as pending in frontend
        } else {
            $computedStatus = 'AVAILABLE';
        }

        if ($crew['availability_status'] !== $computedStatus) {
            $updateStatus = "UPDATE maintenance_crews SET availability_status = :status WHERE crew_id = :crew_id";
            $uStmt = $this->db->prepare($updateStatus);
            $uStmt->execute(['status' => $computedStatus, 'crew_id' => $crewId]);
            $crew['availability_status'] = $computedStatus;
        }

        $crew['active_tasks_count'] = $activeCnt;
        $crew['pending_tasks_count'] = $pendingCnt;
        $crew['computed_status'] = ($activeCnt > 0) ? 'BUSY' : (($pendingCnt > 0) ? 'PENDING_ACCEPTANCE' : 'AVAILABLE');
        $crew['performance_score'] = (float)$crew['performance_score'];
        $crew['current_latitude'] = (float)$crew['current_latitude'];
        $crew['current_longitude'] = (float)$crew['current_longitude'];

        return $crew;
    }

    /**
     * Update maintenance crew trade capabilities, station region, or base location.
     * Note: Availability status is managed automatically by active task workload.
     */
    public function updateCrewProfile(string $crewId, array $data): bool
    {
        // Ensure crew record exists
        $this->getCrewProfile($crewId);

        $fields = [];
        $params = ['crew_id' => $crewId];

        if (isset($data['skill_tags']) && is_array($data['skill_tags'])) {
            $fields[] = "skill_tags = :skill_tags::skill_category[]";
            $params['skill_tags'] = self::toPgArray($data['skill_tags']);
        }

        if (isset($data['current_latitude']) && isset($data['current_longitude'])) {
            $fields[] = "current_latitude = :lat";
            $fields[] = "current_longitude = :lng";
            $params['lat'] = (float)$data['current_latitude'];
            $params['lng'] = (float)$data['current_longitude'];
        }

        if (isset($data['station_region'])) {
            $fields[] = "station_region = :region";
            $params['region'] = trim($data['station_region']);
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE maintenance_crews SET " . implode(', ', $fields) . " WHERE crew_id = :crew_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update crew availability status ('AVAILABLE' or 'BUSY').
     */
    public function updateCrewAvailability(string $crewId, string $status): bool
    {
        $sql = "UPDATE maintenance_crews 
                SET availability_status = :status 
                WHERE crew_id = :crew_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status'  => strtoupper($status),
            'crew_id' => $crewId
        ]);
    }

    /**
     * Update crew live GPS coordinates.
     */
    public function updateCrewLocation(string $crewId, float $latitude, float $longitude): bool
    {
        $sql = "UPDATE maintenance_crews 
                SET current_latitude = :lat, current_longitude = :lng 
                WHERE crew_id = :crew_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'lat'     => $latitude,
            'lng'     => $longitude,
            'crew_id' => $crewId
        ]);
    }

    /**
     * Apply penalty deduction to crew performance score (e.g. -5 for dropping task).
     */
    public function applyCrewPenalty(string $crewId, float $penaltyPoints = 5.0): bool
    {
        $sql = "UPDATE maintenance_crews 
                SET performance_score = GREATEST(0.0, performance_score - :penalty) 
                WHERE crew_id = :crew_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'penalty' => $penaltyPoints,
            'crew_id' => $crewId
        ]);
    }

    /**
     * Deduct reputation score from tourist account (e.g. -20 for invalid report).
     */
    public function deductTouristReputation(string $userId, int $penaltyPoints = 20): bool
    {
        $sql = "UPDATE tourist_profiles 
                SET reputation_score = GREATEST(0, reputation_score - :penalty) 
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'penalty' => $penaltyPoints,
            'user_id' => $userId
        ]);
    }

    /**
     * Get user contribution statistics (reports submitted, upvotes given, resolved fixes).
     */
    public function getUserActivityStats(string $userId): array
    {
        $stmt1 = $this->db->prepare("SELECT COUNT(*) as cnt FROM damage_reports WHERE user_id = :id");
        $stmt1->execute(['id' => $userId]);
        $reportsCount = (int)($stmt1->fetch()['cnt'] ?? 0);

        $stmt2 = $this->db->prepare("SELECT COUNT(*) as cnt FROM report_upvotes WHERE user_id = :id");
        $stmt2->execute(['id' => $userId]);
        $upvotesCount = (int)($stmt2->fetch()['cnt'] ?? 0);

        $stmt3 = $this->db->prepare("SELECT COUNT(*) as cnt FROM damage_reports WHERE user_id = :id AND status = 'COMPLETED'");
        $stmt3->execute(['id' => $userId]);
        $resolvedCount = (int)($stmt3->fetch()['cnt'] ?? 0);

        return [
            'reports_count'  => $reportsCount,
            'upvotes_count'  => $upvotesCount,
            'resolved_count' => $resolvedCount
        ];
    }

    /**
     * Delete user by ID.
     */
    public function deleteUser(string $userId): bool
    {
        $sql = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
}
