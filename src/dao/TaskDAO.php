<?php

namespace App\DAO;

use App\Config\Database;
use PDO;
use Exception;

class TaskDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Retrieve single task by UUID with full report and crew details.
     */
    public function getTaskById(string $taskId): ?array
    {
        $sql = "SELECT t.*, 
                       r.category, r.required_skill, r.description, r.latitude, r.longitude,
                       r.photo_url as report_photo_url, r.upvote_count, r.priority_score,
                       r.status as report_status, r.district, r.user_id as reporter_id,
                       u.full_name as reporter_name,
                       cu.full_name as assigned_crew_name,
                       mc.availability_status as crew_availability
                FROM maintenance_tasks t
                JOIN damage_reports r ON t.report_id = r.report_id
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN users cu ON t.assigned_crew_id = cu.user_id
                LEFT JOIN maintenance_crews mc ON t.assigned_crew_id = mc.crew_id
                WHERE t.task_id = :task_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['task_id' => $taskId]);
        $data = $stmt->fetch();
        return $data ?: null;
    }

    /**
     * Retrieve tasks assigned to a specific technician ('PENDING_ACCEPTANCE' or 'IN_PROGRESS').
     */
    public function getAssignedTasksForCrew(string $crewId): array
    {
        $sql = "SELECT t.*, 
                       r.category, r.required_skill, r.description, r.latitude, r.longitude,
                       r.photo_url as report_photo_url, r.upvote_count, r.priority_score,
                       r.status as report_status, r.district,
                       u.full_name as reporter_name
                FROM maintenance_tasks t
                JOIN damage_reports r ON t.report_id = r.report_id
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE t.assigned_crew_id = :crew_id
                  AND r.status IN ('PENDING_ACCEPTANCE', 'IN_PROGRESS')
                ORDER BY r.priority_score DESC, r.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['crew_id' => $crewId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve unallocated tasks in Open Maintenance Pool ('OPEN_FOR_CLAIM').
     */
    public function getOpenPoolTasks(?string $skillCategory = null): array
    {
        $sql = "SELECT t.task_id, t.report_id, t.created_at as task_created_at,
                       r.category, r.required_skill, r.description, r.latitude, r.longitude,
                       r.photo_url as report_photo_url, r.upvote_count, r.priority_score,
                       r.status as report_status, r.district, r.created_at as report_created_at,
                       u.full_name as reporter_name
                FROM maintenance_tasks t
                JOIN damage_reports r ON t.report_id = r.report_id
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.status = 'OPEN_FOR_CLAIM'";

        $params = [];
        if (!empty($skillCategory)) {
            $sql .= " AND r.required_skill = :skill";
            $params['skill'] = strtoupper($skillCategory);
        }

        $sql .= " ORDER BY r.priority_score DESC, r.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Claim an open task with transactional row locking (SELECT ... FOR UPDATE) to prevent race conditions.
     */
    public function claimTask(string $taskId, string $crewId): array
    {
        try {
            $this->db->beginTransaction();

            // 1. Lock the task and report row
            $lockSql = "SELECT t.task_id, t.report_id, r.status 
                        FROM maintenance_tasks t
                        JOIN damage_reports r ON t.report_id = r.report_id
                        WHERE t.task_id = :task_id
                        FOR UPDATE";

            $stmt = $this->db->prepare($lockSql);
            $stmt->execute(['task_id' => $taskId]);
            $record = $stmt->fetch();

            if (!$record) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Task not found.'];
            }

            if ($record['status'] !== 'OPEN_FOR_CLAIM') {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'This task was just claimed by another crew.'];
            }

            // 2. Bind task to claiming technician & set SLA (+24h)
            $updateTask = "UPDATE maintenance_tasks 
                           SET assigned_crew_id = :crew_id,
                               assigned_at = CURRENT_TIMESTAMP,
                               sla_expires_at = CURRENT_TIMESTAMP + interval '24 hours'
                           WHERE task_id = :task_id";
            $stmt = $this->db->prepare($updateTask);
            $stmt->execute(['crew_id' => $crewId, 'task_id' => $taskId]);

            // 3. Update report status to IN_PROGRESS
            $updateReport = "UPDATE damage_reports SET status = 'IN_PROGRESS' WHERE report_id = :report_id";
            $stmt = $this->db->prepare($updateReport);
            $stmt->execute(['report_id' => $record['report_id']]);

            // 4. Update crew availability to BUSY
            $updateCrew = "UPDATE maintenance_crews SET availability_status = 'BUSY' WHERE crew_id = :crew_id";
            $stmt = $this->db->prepare($updateCrew);
            $stmt->execute(['crew_id' => $crewId]);

            $this->db->commit();
            return ['success' => true, 'task_id' => $taskId, 'status' => 'IN_PROGRESS'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::claimTask: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Explicitly accept an assigned task.
     */
    public function acceptTask(string $taskId, string $crewId): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE damage_reports 
                    SET status = 'IN_PROGRESS' 
                    WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id AND assigned_crew_id = :crew_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['task_id' => $taskId, 'crew_id' => $crewId]);

            $crewSql = "UPDATE maintenance_crews SET availability_status = 'BUSY' WHERE crew_id = :crew_id";
            $crewStmt = $this->db->prepare($crewSql);
            $crewStmt->execute(['crew_id' => $crewId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Decline an assigned task -> moves to Open Pool ('OPEN_FOR_CLAIM').
     */
    public function declineTask(string $taskId, string $crewId, string $reason): bool
    {
        try {
            $this->db->beginTransaction();

            $taskSql = "UPDATE maintenance_tasks 
                        SET assigned_crew_id = NULL,
                            decline_reason = :reason
                        WHERE task_id = :task_id AND assigned_crew_id = :crew_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'reason'  => $reason,
                'task_id' => $taskId,
                'crew_id' => $crewId
            ]);

            $repSql = "UPDATE damage_reports 
                       SET status = 'OPEN_FOR_CLAIM' 
                       WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id)";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['task_id' => $taskId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Mark task as completed with live "After-Repair" photo proof.
     */
    public function completeTask(string $taskId, string $photoUrl, string $notes): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch assigned crew ID
            $fetchSql = "SELECT assigned_crew_id, report_id FROM maintenance_tasks WHERE task_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task not found.");
            }

            // 1. Update task record
            $taskSql = "UPDATE maintenance_tasks 
                        SET completion_photo_url = :photo,
                            resolution_notes = :notes,
                            completed_at = CURRENT_TIMESTAMP
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'   => $photoUrl,
                'notes'   => $notes,
                'task_id' => $taskId
            ]);

            // 2. Mark report COMPLETED
            $repSql = "UPDATE damage_reports SET status = 'COMPLETED' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $task['report_id']]);

            // 3. Reset crew back to AVAILABLE
            if (!empty($task['assigned_crew_id'])) {
                $crewSql = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                $crewStmt = $this->db->prepare($crewSql);
                $crewStmt->execute(['crew_id' => $task['assigned_crew_id']]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::completeTask: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drop an accepted task due to field complications (-5 penalty to crew, returns to Open Pool).
     */
    public function dropTask(string $taskId, string $crewId, string $dropReason, float $penalty = 5.0): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Update task with drop reason and release crew lock
            $taskSql = "UPDATE maintenance_tasks 
                        SET assigned_crew_id = NULL,
                            drop_reason = :reason
                        WHERE task_id = :task_id AND assigned_crew_id = :crew_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'reason'  => $dropReason,
                'task_id' => $taskId,
                'crew_id' => $crewId
            ]);

            // 2. Return report to Open Maintenance Pool
            $repSql = "UPDATE damage_reports 
                       SET status = 'OPEN_FOR_CLAIM' 
                       WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id)";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['task_id' => $taskId]);

            // 3. Apply performance penalty and set crew to AVAILABLE
            $crewSql = "UPDATE maintenance_crews 
                        SET performance_score = GREATEST(0.0, performance_score - :penalty),
                            availability_status = 'AVAILABLE'
                        WHERE crew_id = :crew_id";
            $crewStmt = $this->db->prepare($crewSql);
            $crewStmt->execute([
                'penalty' => $penalty,
                'crew_id' => $crewId
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::dropTask: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Flag incident as Invalid / No Fault Found with clear-site photo proof (-20 penalty to reporter).
     */
    public function flagInvalidTask(string $taskId, string $crewId, string $photoUrl, string $notes, int $penalty = 20): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch reporter ID and report ID
            $fetchSql = "SELECT t.report_id, r.user_id as reporter_id
                         FROM maintenance_tasks t
                         JOIN damage_reports r ON t.report_id = r.report_id
                         WHERE t.task_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $info = $fetchStmt->fetch();

            if (!$info) {
                throw new Exception("Task record not found.");
            }

            // 1. Update task with invalid resolution
            $taskSql = "UPDATE maintenance_tasks 
                        SET flag_invalid_photo_url = :photo,
                            flag_invalid_notes = :notes,
                            completed_at = CURRENT_TIMESTAMP
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'   => $photoUrl,
                'notes'   => $notes,
                'task_id' => $taskId
            ]);

            // 2. Mark report INVALID_RESOLVED
            $repSql = "UPDATE damage_reports SET status = 'INVALID_RESOLVED' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $info['report_id']]);

            // 3. Deduct reputation from original reporter
            if (!empty($info['reporter_id'])) {
                $userSql = "UPDATE tourist_profiles 
                            SET reputation_score = GREATEST(0, reputation_score - :penalty) 
                            WHERE user_id = :reporter_id";
                $userStmt = $this->db->prepare($userSql);
                $userStmt->execute([
                    'penalty'     => $penalty,
                    'reporter_id' => $info['reporter_id']
                ]);
            }

            // 4. Reset crew to AVAILABLE
            $crewSql = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
            $crewStmt = $this->db->prepare($crewSql);
            $crewStmt->execute(['crew_id' => $crewId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::flagInvalidTask: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Manual dispatch override by Administrator (UC-08b).
     */
    public function manualAssignTask(string $taskId, string $crewId): bool
    {
        try {
            $this->db->beginTransaction();

            $taskSql = "UPDATE maintenance_tasks 
                        SET assigned_crew_id = :crew_id,
                            assigned_at = CURRENT_TIMESTAMP,
                            sla_expires_at = CURRENT_TIMESTAMP + interval '24 hours'
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'crew_id' => $crewId,
                'task_id' => $taskId
            ]);

            $repSql = "UPDATE damage_reports 
                       SET status = 'PENDING_ACCEPTANCE' 
                       WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id)";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['task_id' => $taskId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::manualAssignTask: " . $e->getMessage());
            return false;
        }
    }
}
