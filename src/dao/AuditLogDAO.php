<?php

namespace App\DAO;

use App\Config\Database;
use PDO;
use Exception;

class AuditLogDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Record an immutable system audit log entry.
     */
    public function log(?string $actorId, string $actionType, string $details): bool
    {
        try {
            $sql = "INSERT INTO system_audit_logs (actor_id, action_type, details, timestamp) 
                    VALUES (:actor_id, :action_type, :details, CURRENT_TIMESTAMP)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'actor_id'    => $actorId,
                'action_type' => $actionType,
                'details'     => $details
            ]);
        } catch (Exception $e) {
            error_log("Error in AuditLogDAO::log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve recent audit logs for administration view.
     */
    public function getRecentLogs(int $limit = 50, ?string $actionType = null): array
    {
        $sql = "SELECT l.log_id, l.actor_id, l.action_type, l.details, l.timestamp,
                       u.full_name as actor_name, u.email as actor_email, u.role as actor_role
                FROM system_audit_logs l
                LEFT JOIN users u ON l.actor_id = u.user_id";

        $params = [];
        if (!empty($actionType)) {
            $sql .= " WHERE l.action_type = :action_type";
            $params['action_type'] = $actionType;
        }

        $sql .= " ORDER BY l.timestamp DESC LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
