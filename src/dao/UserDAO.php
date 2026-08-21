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
        $sql = "SELECT user_id, email, full_name, role, is_active, created_at 
                FROM users 
                ORDER BY created_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve all internal staff users (Site Ranger, Maintenance Crew, Administrator).
     */
    public function getAllInternalUsers(): array
    {
        $sql = "SELECT u.user_id, u.email, u.full_name, u.role, u.is_active, u.created_at,
                       srp.station_region AS ranger_station, srp.employee_id AS ranger_emp_id,
                       mc.skill_tags, mc.availability_status, mc.performance_score, mc.station_region AS crew_station
                FROM users u
                LEFT JOIN site_ranger_profiles srp ON u.user_id = srp.user_id
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
        $sql = "SELECT u.user_id, u.email, u.full_name, u.role, u.is_active, u.created_at,
                       tp.reputation_score,
                       srp.station_region AS ranger_station, srp.employee_id AS ranger_emp_id,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude, mc.station_region AS crew_station
                FROM users u
                LEFT JOIN tourist_profiles tp ON u.user_id = tp.user_id
                LEFT JOIN site_ranger_profiles srp ON u.user_id = srp.user_id
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
        $sql = "SELECT u.user_id, u.email, u.password_hash, u.full_name, u.role, u.is_active, u.created_at,
                       tp.reputation_score,
                       srp.station_region AS ranger_station, srp.employee_id AS ranger_emp_id,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude
                FROM users u
                LEFT JOIN tourist_profiles tp ON u.user_id = tp.user_id
                LEFT JOIN site_ranger_profiles srp ON u.user_id = srp.user_id
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
        $sql = "SELECT user_id, email, full_name, role, is_active, created_at 
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
                  AND :skill = ANY(mc.skill_tags)";

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

            $sql = "INSERT INTO users (email, password_hash, full_name, role) 
                    VALUES (:email, :password_hash, :full_name, :role) 
                    RETURNING user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'email'         => strtolower(trim($email)),
                'password_hash' => $passwordHash,
                'full_name'     => trim($fullName),
                'role'          => $role
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
            } elseif ($role === 'SITE_RANGER') {
                $station = $extraData['station_region'] ?? 'Kuala Lumpur';
                $empId = $extraData['employee_id'] ?? ('RNG-' . rand(1000, 9999));
                $rangerSql = "INSERT INTO site_ranger_profiles (user_id, station_region, employee_id) 
                              VALUES (:id, :station, :emp_id)";
                $rangerStmt = $this->db->prepare($rangerSql);
                $rangerStmt->execute([
                    'id' => $newUserId,
                    'station' => $station,
                    'emp_id' => $empId
                ]);
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
     * Update password.
     */
    public function updatePassword(string $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $sql = "UPDATE users 
                SET password_hash = :password_hash 
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
