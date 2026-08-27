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
        $this->sweepExpiredPendingSlaTasks();

        $sql = "SELECT t.*, 
                       r.category, r.required_skill, r.description, r.latitude, r.longitude,
                       r.photo_url as report_photo_url, r.upvote_count, r.priority_score,
                       r.status as report_status, r.district,
                       u.full_name as reporter_name
                FROM maintenance_tasks t
                JOIN damage_reports r ON t.report_id = r.report_id
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE t.assigned_crew_id = :crew_id
                  AND r.status IN ('PENDING_ACCEPTANCE', 'IN_PROGRESS', 'PRECOMPLETE', 'DROPPED', 'PRE_INVALID', 'COMPLETED', 'INVALID_RESOLVED')
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

            // 2. Bind task to claiming technician, set assigned_at = now, clear sla_expires_at
            $updateTask = "UPDATE maintenance_tasks 
                           SET assigned_crew_id = :crew_id,
                               assigned_at = CURRENT_TIMESTAMP,
                               sla_expires_at = NULL
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
     * Renews assigned_at to acceptance timestamp and clears sla_expires_at.
     */
    public function acceptTask(string $taskId, string $crewId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Update maintenance_tasks: set acceptance timestamp as assigned_at, clear SLA timer
            $taskSql = "UPDATE maintenance_tasks 
                        SET assigned_at = CURRENT_TIMESTAMP,
                            sla_expires_at = NULL
                        WHERE task_id = :task_id AND assigned_crew_id = :crew_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute(['task_id' => $taskId, 'crew_id' => $crewId]);

            // 2. Update damage_reports status to IN_PROGRESS
            $sql = "UPDATE damage_reports 
                    SET status = 'IN_PROGRESS' 
                    WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id AND assigned_crew_id = :crew_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['task_id' => $taskId, 'crew_id' => $crewId]);

            // 3. Set crew availability to BUSY
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

            // Sync crew availability status
            $crewCheck = "SELECT COUNT(*) as cnt FROM maintenance_tasks t JOIN damage_reports r ON t.report_id = r.report_id WHERE t.assigned_crew_id = :crew_id AND r.status = 'IN_PROGRESS'";
            $cStmt = $this->db->prepare($crewCheck);
            $cStmt->execute(['crew_id' => $crewId]);
            $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
            if ($cnt === 0) {
                $cUpdate = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                $cuStmt = $this->db->prepare($cUpdate);
                $cuStmt->execute(['crew_id' => $crewId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Mark task as completed with live "After-Repair" photo proof -> transitions to PRECOMPLETE.
     */
    public function completeTask(string $taskId, string $photoUrl, string $notes): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch assigned crew ID and report ID
            $fetchSql = "SELECT task_id, assigned_crew_id, report_id FROM maintenance_tasks WHERE task_id = :task_id OR report_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task not found for ID {$taskId}.");
            }

            // 1. Update task record with completion proof (completed_at remains NULL until admin approval)
            $taskSql = "UPDATE maintenance_tasks 
                        SET completion_photo_url = :photo,
                            resolution_notes = :notes
                        WHERE task_id = :real_task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'        => $photoUrl,
                'notes'        => $notes,
                'real_task_id' => $task['task_id']
            ]);

            // 2. Mark report PRECOMPLETE (waiting for admin validation)
            $repSql = "UPDATE damage_reports SET status = 'PRECOMPLETE' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $task['report_id']]);

            // 3. Sync crew availability: if no other IN_PROGRESS tasks, set to AVAILABLE
            if (!empty($task['assigned_crew_id'])) {
                $crewCheck = "SELECT COUNT(*) as cnt 
                              FROM maintenance_tasks t 
                              JOIN damage_reports r ON t.report_id = r.report_id 
                              WHERE t.assigned_crew_id = :crew_id AND r.status = 'IN_PROGRESS'";
                $cStmt = $this->db->prepare($crewCheck);
                $cStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
                if ($cnt === 0) {
                    $crewSql = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                    $crewStmt = $this->db->prepare($crewSql);
                    $crewStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                }
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
     * Admin approves a PRECOMPLETE work order -> transitions to COMPLETED (+5.0 pts to crew).
     */
    public function approveTaskCompletion(string $taskId, string $adminId, float $rewardPoints = 5.0): array
    {
        try {
            $this->db->beginTransaction();

            $fetchSql = "SELECT t.task_id, t.report_id, t.assigned_crew_id, r.status, u.full_name as crew_name, r.district
                         FROM maintenance_tasks t
                         JOIN damage_reports r ON t.report_id = r.report_id
                         LEFT JOIN users u ON t.assigned_crew_id = u.user_id
                         WHERE t.task_id = :task_id OR t.report_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task record not found for ID {$taskId}.");
            }

            if ($task['status'] !== 'PRECOMPLETE') {
                throw new Exception("Only tasks in PRECOMPLETE status can be approved for completion (Current: {$task['status']}).");
            }

            // 1. Update report status to COMPLETED
            $repSql = "UPDATE damage_reports SET status = 'COMPLETED' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $task['report_id']]);

            // 2. Update task completed_at timestamp
            $taskSql = "UPDATE maintenance_tasks 
                        SET completed_at = CURRENT_TIMESTAMP,
                            admin_return_reason = NULL,
                            decline_reason = NULL
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute(['task_id' => $task['task_id']]);

            // 3. Award performance score (+5.0 points) to the assigned crew
            if (!empty($task['assigned_crew_id'])) {
                $crewSql = "UPDATE maintenance_crews 
                            SET performance_score = LEAST(100.0, performance_score + :reward) 
                            WHERE crew_id = :crew_id";
                $crewStmt = $this->db->prepare($crewSql);
                $crewStmt->execute([
                    'reward'  => $rewardPoints,
                    'crew_id' => $task['assigned_crew_id']
                ]);

                // 4. Ensure crew availability is AVAILABLE if no remaining IN_PROGRESS jobs
                $crewCheck = "SELECT COUNT(*) as cnt 
                              FROM maintenance_tasks t 
                              JOIN damage_reports r ON t.report_id = r.report_id 
                              WHERE t.assigned_crew_id = :crew_id AND r.status = 'IN_PROGRESS'";
                $cStmt = $this->db->prepare($crewCheck);
                $cStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
                if ($cnt === 0) {
                    $cUpdate = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                    $cuStmt = $this->db->prepare($cUpdate);
                    $cuStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                }
            }

            $this->db->commit();
            return [
                'success'       => true,
                'task_id'       => $task['task_id'],
                'report_id'     => $task['report_id'],
                'crew_id'       => $task['assigned_crew_id'],
                'crew_name'     => $task['crew_name'] ?? 'Technician',
                'district'      => $task['district'] ?? 'Incident Site',
                'reward_points' => $rewardPoints
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::approveTaskCompletion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Admin rejects a PRECOMPLETE submission -> returns task back to IN_PROGRESS with mandatory return remarks.
     */
    public function rejectTaskCompletion(string $taskId, string $adminId, string $returnReason): array
    {
        if (empty(trim($returnReason))) {
            throw new Exception("Return reason is required when sending a work order back for rework.");
        }

        try {
            $this->db->beginTransaction();

            $fetchSql = "SELECT t.task_id, t.report_id, t.assigned_crew_id, r.status, u.full_name as crew_name, r.district
                         FROM maintenance_tasks t
                         JOIN damage_reports r ON t.report_id = r.report_id
                         LEFT JOIN users u ON t.assigned_crew_id = u.user_id
                         WHERE t.task_id = :task_id OR t.report_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task record not found for ID {$taskId}.");
            }

            if ($task['status'] !== 'PRECOMPLETE') {
                throw new Exception("Only tasks in PRECOMPLETE status can be returned for rework (Current: {$task['status']}).");
            }

            // 1. Revert report status back to IN_PROGRESS
            $repSql = "UPDATE damage_reports SET status = 'IN_PROGRESS' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $task['report_id']]);

            // 2. Save return reason in maintenance_tasks
            $taskSql = "UPDATE maintenance_tasks 
                        SET admin_return_reason = :reason,
                            decline_reason = :reason,
                            completed_at = NULL
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'reason'  => trim($returnReason),
                'task_id' => $task['task_id']
            ]);

            // 3. Mark crew as BUSY since they have an active in-progress job again
            if (!empty($task['assigned_crew_id'])) {
                $cUpdate = "UPDATE maintenance_crews SET availability_status = 'BUSY' WHERE crew_id = :crew_id";
                $cuStmt = $this->db->prepare($cUpdate);
                $cuStmt->execute(['crew_id' => $task['assigned_crew_id']]);
            }

            $this->db->commit();
            return [
                'success'       => true,
                'task_id'       => $task['task_id'],
                'report_id'     => $task['report_id'],
                'crew_id'       => $task['assigned_crew_id'],
                'crew_name'     => $task['crew_name'] ?? 'Technician',
                'district'      => $task['district'] ?? 'Incident Site',
                'return_reason' => trim($returnReason)
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::rejectTaskCompletion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Drop an accepted task due to field complications (-10 penalty to crew, status -> DROPPED).
     */
    public function dropTask(string $taskId, string $crewId, string $dropReason, float $penalty = 10.0): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Update task with drop reason
            $taskSql = "UPDATE maintenance_tasks 
                        SET drop_reason = :reason
                        WHERE task_id = :task_id AND assigned_crew_id = :crew_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'reason'  => $dropReason,
                'task_id' => $taskId,
                'crew_id' => $crewId
            ]);

            // 2. Transition report status to DROPPED (for admin review)
            $repSql = "UPDATE damage_reports 
                       SET status = 'DROPPED' 
                       WHERE report_id = (SELECT report_id FROM maintenance_tasks WHERE task_id = :task_id)";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['task_id' => $taskId]);

            // 3. Apply performance penalty (-10 points)
            $crewSql = "UPDATE maintenance_crews 
                        SET performance_score = GREATEST(0.0, performance_score - :penalty)
                        WHERE crew_id = :crew_id";
            $crewStmt = $this->db->prepare($crewSql);
            $crewStmt->execute([
                'penalty' => $penalty,
                'crew_id' => $crewId
            ]);

            // 4. Sync crew availability: if 0 active in-progress jobs, set to AVAILABLE
            $crewCheck = "SELECT COUNT(*) as cnt 
                          FROM maintenance_tasks t 
                          JOIN damage_reports r ON t.report_id = r.report_id 
                          WHERE t.assigned_crew_id = :crew_id AND r.status = 'IN_PROGRESS'";
            $cStmt = $this->db->prepare($crewCheck);
            $cStmt->execute(['crew_id' => $crewId]);
            $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
            if ($cnt === 0) {
                $cUpdate = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                $cuStmt = $this->db->prepare($cUpdate);
                $cuStmt->execute(['crew_id' => $crewId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::dropTask: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin returns a DROPPED task back to the OPEN MAINTENANCE POOL.
     * Transitions damage_reports.status to OPEN_FOR_CLAIM, clears assignment fields in maintenance_tasks.
     */
    public function returnTaskToOpenPool(string $taskIdOrReportId, string $adminId): array
    {
        try {
            $this->db->beginTransaction();

            $fetchSql = "SELECT t.task_id, t.report_id, t.assigned_crew_id, r.status, r.district
                         FROM damage_reports r
                         LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                         WHERE t.task_id = :id OR r.report_id = :id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['id' => $taskIdOrReportId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task or damage report not found for ID {$taskIdOrReportId}.");
            }

            $previousCrewId = $task['assigned_crew_id'];

            // 1. Update report status to OPEN_FOR_CLAIM
            $repSql = "UPDATE damage_reports SET status = 'OPEN_FOR_CLAIM' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $task['report_id']]);

            // 2. Clear assignment & SLA details in maintenance_tasks if task record exists
            if (!empty($task['task_id'])) {
                $taskSql = "UPDATE maintenance_tasks 
                            SET assigned_crew_id = NULL,
                                assigned_at = NULL,
                                sla_expires_at = NULL,
                                admin_return_reason = NULL
                            WHERE task_id = :task_id";
                $taskStmt = $this->db->prepare($taskSql);
                $taskStmt->execute(['task_id' => $task['task_id']]);
            }

            // 3. If previous crew has 0 other in-progress/pending tasks, ensure status is AVAILABLE
            if (!empty($previousCrewId)) {
                $crewCheck = "SELECT COUNT(*) as cnt 
                              FROM maintenance_tasks t 
                              JOIN damage_reports r ON t.report_id = r.report_id 
                              WHERE t.assigned_crew_id = :crew_id AND r.status IN ('IN_PROGRESS', 'PENDING_ACCEPTANCE')";
                $cStmt = $this->db->prepare($crewCheck);
                $cStmt->execute(['crew_id' => $previousCrewId]);
                $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
                if ($cnt === 0) {
                    $cUpdate = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                    $cuStmt = $this->db->prepare($cUpdate);
                    $cuStmt->execute(['crew_id' => $previousCrewId]);
                }
            }

            $this->db->commit();
            return [
                'success'   => true,
                'task_id'   => $task['task_id'] ?? $task['report_id'],
                'report_id' => $task['report_id'],
                'district'  => $task['district'] ?? 'Incident Site'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::returnTaskToOpenPool: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Flag incident as Invalid / No Fault Found with clear-site photo proof -> transitions to PRE_INVALID.
     */
    public function flagInvalidTask(string $taskId, string $crewId, string $photoUrl, string $notes): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch report ID
            $fetchSql = "SELECT t.task_id, t.report_id, r.user_id as reporter_id
                         FROM maintenance_tasks t
                         JOIN damage_reports r ON t.report_id = r.report_id
                         WHERE t.task_id = :task_id OR t.report_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $info = $fetchStmt->fetch();

            if (!$info) {
                throw new Exception("Task record not found for ID {$taskId}.");
            }

            // 1. Update task with clear-site proof & findings
            $taskSql = "UPDATE maintenance_tasks 
                        SET flag_invalid_photo_url = :photo,
                            flag_invalid_notes = :notes
                        WHERE task_id = :real_task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'        => $photoUrl,
                'notes'        => $notes,
                'real_task_id' => $info['task_id']
            ]);

            // 2. Mark report PRE_INVALID (waiting for admin validation)
            $repSql = "UPDATE damage_reports SET status = 'PRE_INVALID' WHERE report_id = :report_id";
            $repStmt = $this->db->prepare($repSql);
            $repStmt->execute(['report_id' => $info['report_id']]);

            // 3. Sync crew availability: if 0 active in-progress jobs, set to AVAILABLE
            $crewCheck = "SELECT COUNT(*) as cnt 
                          FROM maintenance_tasks t 
                          JOIN damage_reports r ON t.report_id = r.report_id 
                          WHERE t.assigned_crew_id = :crew_id AND r.status = 'IN_PROGRESS'";
            $cStmt = $this->db->prepare($crewCheck);
            $cStmt->execute(['crew_id' => $crewId]);
            $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
            if ($cnt === 0) {
                $crewSql = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                $crewStmt = $this->db->prepare($crewSql);
                $crewStmt->execute(['crew_id' => $crewId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::flagInvalidTask: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all unassigned damage reports in the prioritization queue, sorted by Pscore descending.
     */
    public function getUnassignedQueue(): array
    {
        $sql = "SELECT r.report_id, r.category, r.required_skill, r.description,
                       r.latitude, r.longitude, r.photo_url, r.upvote_count,
                       r.priority_score, r.status, r.district, r.created_at,
                       u.full_name as reporter_name,
                       COALESCE(tp.reputation_score, 100) as reporter_reputation,
                       t.task_id
                FROM damage_reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN tourist_profiles tp ON r.user_id = tp.user_id
                LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                WHERE r.status = 'OPEN_FOR_CLAIM'
                ORDER BY r.priority_score DESC, r.created_at ASC";

        $stmt = $this->db->query($sql);
        $reports = $stmt->fetchAll();

        foreach ($reports as &$rpt) {
            $rpt['priority_score'] = (float)$rpt['priority_score'];
            $rpt['upvote_count'] = (int)$rpt['upvote_count'];
            $rpt['reporter_reputation'] = (int)$rpt['reporter_reputation'];
            $rpt['hazard_weight'] = ReportDAO::getCategoryWeight($rpt['category']);
        }

        return $reports;
    }

    /**
     * Retrieve all maintenance crews fleet status with trade skills, base locations, and live workload.
     */
    public function getAllCrewsFleet(): array
    {
        $sql = "SELECT u.user_id, u.full_name, u.email,
                       mc.skill_tags, mc.availability_status, mc.performance_score,
                       mc.current_latitude, mc.current_longitude, mc.station_region,
                       (
                           SELECT COUNT(*) 
                           FROM maintenance_tasks t 
                           JOIN damage_reports r ON t.report_id = r.report_id 
                           WHERE t.assigned_crew_id = u.user_id AND r.status = 'IN_PROGRESS'
                       ) as active_tasks_count,
                       (
                           SELECT COUNT(*) 
                           FROM maintenance_tasks t 
                           JOIN damage_reports r ON t.report_id = r.report_id 
                           WHERE t.assigned_crew_id = u.user_id AND r.status = 'PENDING_ACCEPTANCE'
                       ) as pending_tasks_count
                FROM users u
                JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                WHERE u.role = 'MAINTENANCE_CREW' AND u.is_active = TRUE
                ORDER BY u.full_name ASC";

        $stmt = $this->db->query($sql);
        $crews = $stmt->fetchAll();

        foreach ($crews as &$crew) {
            $crew['skill_tags'] = UserDAO::parsePgArray($crew['skill_tags']);
            $crew['performance_score'] = (float)$crew['performance_score'];
            $crew['current_latitude'] = (float)$crew['current_latitude'];
            $crew['current_longitude'] = (float)$crew['current_longitude'];
            $crew['active_tasks_count'] = (int)$crew['active_tasks_count'];
            $crew['pending_tasks_count'] = (int)$crew['pending_tasks_count'];

            // Sync computed availability: Must have 0 in-progress AND 0 pending acceptance
            if ($crew['active_tasks_count'] > 0) {
                $crew['computed_status'] = 'BUSY';
            } elseif ($crew['pending_tasks_count'] > 0) {
                $crew['computed_status'] = 'PENDING_ACCEPTANCE';
            } else {
                $crew['computed_status'] = 'AVAILABLE';
            }
        }

        return $crews;
    }

    /**
     * Execute Automated Spatial Dispatch Algorithm (Module 2 Engine).
     * Matches unassigned high-priority reports to the closest qualified AVAILABLE maintenance crew.
     * Rule: A crew can only be assigned if they have 0 in-progress tasks AND 0 pending acceptance tasks.
     */
    public function runAutoDispatch(): array
    {
        $queue = $this->getUnassignedQueue();
        $crews = $this->getAllCrewsFleet();

        $assignedCrewIdsInBatch = [];
        $matchResults = [];
        $assignedCount = 0;
        $unassignedCount = 0;

        foreach ($queue as $report) {
            $repLat = (float)$report['latitude'];
            $repLng = (float)$report['longitude'];
            $requiredSkill = strtoupper($report['required_skill'] ?? 'OTHER');

            $bestCandidate = null;
            $bestScore = -1.0;
            $bestDistanceKm = 0.0;

            foreach ($crews as $crew) {
                $crewId = $crew['user_id'];

                // 1. Must be AVAILABLE (0 in-progress AND 0 pending tasks) and not assigned yet in this batch run
                if ($crew['computed_status'] !== 'AVAILABLE' || in_array($crewId, $assignedCrewIdsInBatch)) {
                    continue;
                }

                // 2. Skill eligibility check
                $hasSkill = in_array($requiredSkill, $crew['skill_tags']) 
                         || in_array('OTHER', $crew['skill_tags']) 
                         || empty($crew['skill_tags']); // empty skill crew can take general jobs

                if (!$hasSkill) {
                    continue;
                }

                // 3. Compute Haversine distance in km
                $crewLat = (float)$crew['current_latitude'];
                $crewLng = (float)$crew['current_longitude'];
                $distKm = \App\Utils\GeoUtil::haversineDistanceKm($crewLat, $crewLng, $repLat, $repLng);

                // Task assignment restriction: Max 30km distance between report and crew
                if ($distKm > 30.0) {
                    continue;
                }

                // 4. Calculate Spatial Suitability Score: S = Performance / (Distance + 0.1)
                $perfScore = (float)$crew['performance_score'];
                $suitabilityScore = $perfScore / ($distKm + 0.1);

                if ($suitabilityScore > $bestScore) {
                    $bestScore = $suitabilityScore;
                    $bestCandidate = $crew;
                    $bestDistanceKm = $distKm;
                }
            }

            if ($bestCandidate !== null) {
                // Execute database assignment
                try {
                    $this->db->beginTransaction();

                    $assignedCrewId = $bestCandidate['user_id'];

                    // Check if task record exists, if so update, if not insert
                    $checkTask = "SELECT task_id FROM maintenance_tasks WHERE report_id = :rep_id";
                    $cStmt = $this->db->prepare($checkTask);
                    $cStmt->execute(['rep_id' => $report['report_id']]);
                    $existingTask = $cStmt->fetch();

                    if ($existingTask) {
                        $updateTask = "UPDATE maintenance_tasks 
                                       SET assigned_crew_id = :crew_id,
                                           assigned_at = CURRENT_TIMESTAMP,
                                           sla_expires_at = CURRENT_TIMESTAMP + interval '24 hours'
                                       WHERE task_id = :task_id";
                        $uStmt = $this->db->prepare($updateTask);
                        $uStmt->execute([
                            'crew_id' => $assignedCrewId,
                            'task_id' => $existingTask['task_id']
                        ]);
                        $taskId = $existingTask['task_id'];
                    } else {
                        $insertTask = "INSERT INTO maintenance_tasks 
                                       (report_id, assigned_crew_id, assigned_at, sla_expires_at) 
                                       VALUES (:rep_id, :crew_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + interval '24 hours')
                                       RETURNING task_id";
                        $iStmt = $this->db->prepare($insertTask);
                        $iStmt->execute([
                            'rep_id'  => $report['report_id'],
                            'crew_id' => $assignedCrewId
                        ]);
                        $taskId = $iStmt->fetch()['task_id'];
                    }

                    // Update report status to PENDING_ACCEPTANCE
                    $updateRep = "UPDATE damage_reports SET status = 'PENDING_ACCEPTANCE' WHERE report_id = :rep_id";
                    $rStmt = $this->db->prepare($updateRep);
                    $rStmt->execute(['rep_id' => $report['report_id']]);

                    $this->db->commit();

                    $assignedCrewIdsInBatch[] = $assignedCrewId;
                    $assignedCount++;

                    $matchResults[] = [
                        'report_id'          => $report['report_id'],
                        'task_id'            => $taskId,
                        'category'           => $report['category'],
                        'required_skill'     => $requiredSkill,
                        'priority_score'     => (float)$report['priority_score'],
                        'district'           => $report['district'],
                        'status'             => 'ASSIGNED',
                        'matched_crew_id'    => $assignedCrewId,
                        'matched_crew_name'  => $bestCandidate['full_name'],
                        'distance_km'        => round($bestDistanceKm, 2),
                        'suitability_score'  => round($bestScore, 1)
                    ];
                } catch (Exception $e) {
                    $this->db->rollBack();
                    error_log("Dispatch error on report {$report['report_id']}: " . $e->getMessage());
                }
            } else {
                $unassignedCount++;
                $matchResults[] = [
                    'report_id'         => $report['report_id'],
                    'category'          => $report['category'],
                    'required_skill'    => $requiredSkill,
                    'priority_score'    => (float)$report['priority_score'],
                    'district'          => $report['district'],
                    'status'            => 'SKIPPED',
                    'reason'            => "No available crew matching trade skill: {$requiredSkill}"
                ];
            }
        }

        return [
            'total_evaluated'   => count($queue),
            'assigned_count'    => $assignedCount,
            'unassigned_count'  => $unassignedCount,
            'matches'           => $matchResults,
            'timestamp'         => date('c')
        ];
    }

    /**
     * Sweeps tasks in PENDING_ACCEPTANCE whose 24-hour SLA window has passed.
     * Automatically reverts them back to OPEN_FOR_CLAIM so they can be dispatched or claimed again.
     */
    public function sweepExpiredPendingSlaTasks(): int
    {
        try {
            // Find all expired pending tasks
            $sql = "SELECT t.task_id, t.report_id, t.assigned_crew_id
                    FROM maintenance_tasks t
                    JOIN damage_reports r ON t.report_id = r.report_id
                    WHERE r.status = 'PENDING_ACCEPTANCE'
                      AND t.sla_expires_at IS NOT NULL
                      AND t.sla_expires_at < CURRENT_TIMESTAMP";
            $stmt = $this->db->query($sql);
            $expiredTasks = $stmt->fetchAll();

            if (empty($expiredTasks)) {
                return 0;
            }

            $count = 0;
            foreach ($expiredTasks as $task) {
                // Update maintenance_tasks
                $uTask = "UPDATE maintenance_tasks
                          SET assigned_crew_id = NULL,
                              sla_expires_at = NULL,
                              decline_reason = '24h SLA Acceptance Window Expired (Auto-Reverted to Open Pool)'
                          WHERE task_id = :task_id";
                $uStmt = $this->db->prepare($uTask);
                $uStmt->execute(['task_id' => $task['task_id']]);

                // Update damage_reports
                $uRep = "UPDATE damage_reports
                         SET status = 'OPEN_FOR_CLAIM'
                         WHERE report_id = :report_id";
                $rStmt = $this->db->prepare($uRep);
                $rStmt->execute(['report_id' => $task['report_id']]);

                // Check previous crew availability
                if (!empty($task['assigned_crew_id'])) {
                    $crewCheck = "SELECT COUNT(*) as cnt FROM maintenance_tasks t JOIN damage_reports r ON t.report_id = r.report_id WHERE t.assigned_crew_id = :crew_id AND r.status IN ('IN_PROGRESS', 'PENDING_ACCEPTANCE')";
                    $cStmt = $this->db->prepare($crewCheck);
                    $cStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                    $cnt = (int)($cStmt->fetch()['cnt'] ?? 0);
                    if ($cnt === 0) {
                        $cUpdate = "UPDATE maintenance_crews SET availability_status = 'AVAILABLE' WHERE crew_id = :crew_id";
                        $cuStmt = $this->db->prepare($cUpdate);
                        $cuStmt->execute(['crew_id' => $task['assigned_crew_id']]);
                    }
                }

                $count++;
            }

            return $count;
        } catch (Exception $e) {
            error_log("Error in sweepExpiredPendingSlaTasks: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieve complete Admin Task Control Dashboard dataset including counts, active fleet, and task list.
     */
    public function getAdminTasksOverview(?string $statusFilter = null, ?string $search = null, ?string $categoryFilter = null, ?string $skillFilter = null): array
    {
        // 0. Auto-sweep expired pending assignments back to Open Pool
        $this->sweepExpiredPendingSlaTasks();

        // 1. Calculate status counts across all damage reports
        $countSql = "SELECT r.status, COUNT(*) as cnt FROM damage_reports r GROUP BY r.status";
        $countStmt = $this->db->query($countSql);
        $rawCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $counts = [
            'open_pool'           => (int)($rawCounts['OPEN_FOR_CLAIM'] ?? 0),
            'pending_acceptance'  => (int)($rawCounts['PENDING_ACCEPTANCE'] ?? 0),
            'in_progress'         => (int)($rawCounts['IN_PROGRESS'] ?? 0),
            'precomplete'         => (int)($rawCounts['PRECOMPLETE'] ?? 0),
            'dropped'             => (int)($rawCounts['DROPPED'] ?? 0),
            'pre_invalid'         => (int)($rawCounts['PRE_INVALID'] ?? 0),
            'closed'              => (int)(($rawCounts['COMPLETED'] ?? 0) + ($rawCounts['INVALID_RESOLVED'] ?? 0)),
            'total_all'           => array_sum($rawCounts)
        ];

        // 2. Build task query with filters
        $sql = "SELECT r.report_id, r.category, r.required_skill, r.description,
                       r.latitude, r.longitude, r.photo_url, r.upvote_count,
                       r.priority_score, r.status as report_status, r.district, r.created_at as report_created_at,
                       u.full_name as reporter_name, u.email as reporter_email,
                       t.task_id, t.assigned_crew_id, t.assigned_at, t.sla_expires_at,
                       t.completion_photo_url, t.resolution_notes, t.decline_reason,
                       t.drop_reason, t.flag_invalid_photo_url, t.flag_invalid_notes,
                       t.admin_return_reason, t.completed_at,
                       cu.full_name as assigned_crew_name, cu.email as assigned_crew_email,
                       mc.skill_tags as crew_skill_tags, mc.performance_score as crew_performance,
                       mc.availability_status as crew_availability_raw,
                       mc.current_latitude as crew_latitude, mc.current_longitude as crew_longitude
                FROM damage_reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                LEFT JOIN users cu ON t.assigned_crew_id = cu.user_id
                LEFT JOIN maintenance_crews mc ON t.assigned_crew_id = mc.crew_id
                WHERE 1=1";

        $params = [];

        // Status tab filtering
        if (!empty($statusFilter) && strtoupper($statusFilter) !== 'ALL') {
            $upperStatus = strtoupper($statusFilter);
            if ($upperStatus === 'CLOSED') {
                $sql .= " AND r.status IN ('COMPLETED', 'INVALID_RESOLVED')";
            } elseif ($upperStatus === 'OPEN_POOL' || $upperStatus === 'OPEN_FOR_CLAIM') {
                $sql .= " AND r.status = 'OPEN_FOR_CLAIM'";
            } else {
                $sql .= " AND r.status = :status_filter";
                $params['status_filter'] = $upperStatus;
            }
        }

        // Category filtering
        if (!empty($categoryFilter) && strtoupper($categoryFilter) !== 'ALL') {
            $sql .= " AND r.category = :cat_filter";
            $params['cat_filter'] = strtoupper($categoryFilter);
        }

        // Skill filtering
        if (!empty($skillFilter) && strtoupper($skillFilter) !== 'ALL') {
            $sql .= " AND r.required_skill = :skill_filter";
            $params['skill_filter'] = strtoupper($skillFilter);
        }

        // Text search filtering (description, district, reporter, crew, or task code)
        if (!empty($search)) {
            $sql .= " AND (
                LOWER(r.description) LIKE :search 
                OR LOWER(r.district) LIKE :search 
                OR LOWER(u.full_name) LIKE :search 
                OR LOWER(cu.full_name) LIKE :search
                OR LOWER(CAST(r.report_id AS TEXT)) LIKE :search
                OR LOWER(CAST(t.task_id AS TEXT)) LIKE :search
            )";
            $params['search'] = '%' . strtolower(trim($search)) . '%';
        }

        $sql .= " ORDER BY r.priority_score DESC, r.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $nowTs = time();
        $tasks = [];
        $seq = 1;

        foreach ($rows as $row) {
            $taskId = $row['task_id'] ?: $row['report_id'];
            $repLat = (float)$row['latitude'];
            $repLng = (float)$row['longitude'];

            // Elapsed time for IN_PROGRESS tasks (calculated from acceptance / assigned_at timestamp)
            $elapsedSec = 0;
            $elapsedFormatted = '--';
            if ($row['report_status'] === 'IN_PROGRESS' && !empty($row['assigned_at'])) {
                $assignedTs = strtotime($row['assigned_at']);
                $elapsedSec = max(0, $nowTs - $assignedTs);
                $days = floor($elapsedSec / 86400);
                $hours = floor(($elapsedSec % 86400) / 3600);
                $mins = floor(($elapsedSec % 3600) / 60);

                if ($days > 0) {
                    $elapsedFormatted = sprintf("%dd %dh in progress", $days, $hours);
                } elseif ($hours > 0) {
                    $elapsedFormatted = sprintf("%dh %02dm in progress", $hours, $mins);
                } elseif ($mins > 0) {
                    $elapsedFormatted = sprintf("%dm in progress", $mins);
                } else {
                    $elapsedFormatted = "Just started (< 1m)";
                }
            }

            // SLA countdown calculation strictly for PENDING_ACCEPTANCE
            $slaRemainingSec = 0;
            $slaFormatted = '--';
            if ($row['report_status'] === 'PENDING_ACCEPTANCE' && !empty($row['sla_expires_at'])) {
                $slaExpiresTs = strtotime($row['sla_expires_at']);
                $slaRemainingSec = $slaExpiresTs - $nowTs;
                if ($slaRemainingSec > 0) {
                    $hours = floor($slaRemainingSec / 3600);
                    $mins = floor(($slaRemainingSec % 3600) / 60);
                    $slaFormatted = sprintf("%02dh %02dm left", $hours, $mins);
                } else {
                    $slaFormatted = 'SLA EXPIRED';
                }
            }

            // Distance from assigned crew if available
            $distFromCrewKm = null;
            if (!empty($row['crew_latitude']) && !empty($row['crew_longitude'])) {
                $distFromCrewKm = round(\App\Utils\GeoUtil::haversineDistanceKm((float)$row['crew_latitude'], (float)$row['crew_longitude'], $repLat, $repLng), 2);
            }

            $crewSkills = !empty($row['crew_skill_tags']) ? UserDAO::parsePgArray($row['crew_skill_tags']) : [];

            $tasks[] = [
                'task_id'                 => $taskId,
                'task_code'               => sprintf("TSK-%03d", $seq++),
                'report_id'               => $row['report_id'],
                'category'                => $row['category'],
                'required_skill'          => $row['required_skill'],
                'description'             => $row['description'],
                'latitude'                => $repLat,
                'longitude'               => $repLng,
                'district'                => $row['district'] ?: 'Kuala Lumpur',
                'photo_url'               => $row['photo_url'] ?: 'https://images.unsplash.com/photo-1541888946425-d0fbb18086f6?auto=format&fit=crop&w=600&q=80',
                'upvote_count'            => (int)$row['upvote_count'],
                'priority_score'          => (float)$row['priority_score'],
                'status'                  => $row['report_status'],
                'report_created_at'       => $row['report_created_at'],
                'reporter_name'           => $row['reporter_name'] ?: 'Citizen Reporter',
                'reporter_email'          => $row['reporter_email'] ?: 'citizen@ecoasset.org',
                'assigned_crew_id'        => $row['assigned_crew_id'],
                'assigned_crew_name'      => $row['assigned_crew_name'],
                'assigned_crew_email'     => $row['assigned_crew_email'],
                'assigned_crew_skills'    => $crewSkills,
                'assigned_crew_perf'      => $row['crew_performance'] !== null ? (float)$row['crew_performance'] : null,
                'assigned_crew_dist_km'   => $distFromCrewKm,
                'assigned_at'             => $row['assigned_at'],
                'elapsed_time_seconds'    => $elapsedSec,
                'elapsed_formatted'       => $elapsedFormatted,
                'sla_expires_at'          => $row['sla_expires_at'],
                'sla_remaining_seconds'   => $slaRemainingSec,
                'sla_formatted'           => $slaFormatted,
                'completion_photo_url'    => $row['completion_photo_url'],
                'resolution_notes'        => $row['resolution_notes'],
                'decline_reason'          => $row['decline_reason'],
                'drop_reason'             => $row['drop_reason'],
                'flag_invalid_photo_url'  => $row['flag_invalid_photo_url'],
                'flag_invalid_notes'      => $row['flag_invalid_notes'],
                'admin_return_reason'     => $row['admin_return_reason'],
                'completed_at'            => $row['completed_at']
            ];
        }

        // 3. Fetch Fleet Status
        $fleet = $this->getAllCrewsFleet();

        return [
            'counts'    => $counts,
            'tasks'     => $tasks,
            'fleet'     => $fleet,
            'timestamp' => date('c')
        ];
    }

    /**
     * Preview single-task algorithm auto-dispatch recommendation.
     * Evaluates all available qualified crews (0 in-progress & 0 pending, matching skill, <= 30km).
     */
    public function previewSingleTaskAutoDispatch(string $taskIdOrReportId): array
    {
        // 1. Fetch task details
        $task = $this->getTaskById($taskIdOrReportId);
        if (!$task) {
            // Try fetching by report_id fallback
            $sql = "SELECT r.*, t.task_id 
                    FROM damage_reports r 
                    LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id 
                    WHERE r.report_id = :id OR t.task_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $taskIdOrReportId]);
            $task = $stmt->fetch();
        }

        if (!$task) {
            return [
                'success' => false,
                'error'   => 'Task or report not found.'
            ];
        }

        $repLat = (float)$task['latitude'];
        $repLng = (float)$task['longitude'];
        $requiredSkill = strtoupper($task['required_skill'] ?? 'OTHER');

        // 2. Fetch fleet
        $crews = $this->getAllCrewsFleet();

        $candidates = [];
        $bestCandidate = null;
        $bestScore = -1.0;
        $bestDistanceKm = 0.0;

        foreach ($crews as $crew) {
            $crewId = $crew['user_id'];

            // 1. Skill check
            $hasSkill = in_array($requiredSkill, $crew['skill_tags']) 
                     || in_array('OTHER', $crew['skill_tags']) 
                     || empty($crew['skill_tags']);

            // 2. Distance calculation
            $crewLat = (float)$crew['current_latitude'];
            $crewLng = (float)$crew['current_longitude'];
            $distKm = \App\Utils\GeoUtil::haversineDistanceKm($crewLat, $crewLng, $repLat, $repLng);

            // 3. Suitability score: S = Performance / (Distance + 0.1)
            $perfScore = (float)$crew['performance_score'];
            $suitabilityScore = $perfScore / ($distKm + 0.1);

            $isEligible = ($crew['computed_status'] === 'AVAILABLE') && $hasSkill && ($distKm <= 30.0);

            $crewEntry = [
                'crew_id'           => $crew['user_id'],
                'full_name'         => $crew['full_name'],
                'email'             => $crew['email'],
                'skill_tags'        => $crew['skill_tags'],
                'has_skill'         => $hasSkill,
                'computed_status'   => $crew['computed_status'],
                'active_tasks'      => $crew['active_tasks_count'],
                'pending_tasks'     => $crew['pending_tasks_count'],
                'distance_km'       => round($distKm, 2),
                'performance_score' => $perfScore,
                'suitability_score' => round($suitabilityScore, 1),
                'station_region'    => $crew['station_region'] ?: 'Kuala Lumpur',
                'is_eligible'       => $isEligible
            ];

            if ($isEligible && $suitabilityScore > $bestScore) {
                $bestScore = $suitabilityScore;
                $bestCandidate = $crewEntry;
                $bestDistanceKm = $distKm;
            }

            $candidates[] = $crewEntry;
        }

        // Sort all candidates by suitability score DESC
        usort($candidates, function ($a, $b) {
            return $b['suitability_score'] <=> $a['suitability_score'];
        });

        if ($bestCandidate !== null) {
            return [
                'success'           => true,
                'matched'           => true,
                'task_id'           => $task['task_id'] ?? $task['report_id'],
                'report_id'         => $task['report_id'],
                'category'          => $task['category'],
                'required_skill'    => $requiredSkill,
                'priority_score'    => (float)$task['priority_score'],
                'best_candidate'    => $bestCandidate,
                'all_candidates'    => $candidates
            ];
        } else {
            // Determine friendly reason
            $reason = "No available maintenance crew with matching trade skill '{$requiredSkill}' within 30km operating radius.";
            $hasAnyMatchingSkill = false;
            foreach ($crews as $c) {
                if (in_array($requiredSkill, $c['skill_tags'])) {
                    $hasAnyMatchingSkill = true;
                    break;
                }
            }
            if ($hasAnyMatchingSkill) {
                $reason = "Qualified {$requiredSkill} technicians are currently BUSY with active work orders or pending tasks. You can manually dispatch as an Add-On Assignment.";
            }

            return [
                'success'           => true,
                'matched'           => false,
                'task_id'           => $task['task_id'] ?? $task['report_id'],
                'report_id'         => $task['report_id'],
                'category'          => $task['category'],
                'required_skill'    => $requiredSkill,
                'priority_score'    => (float)$task['priority_score'],
                'reason'            => $reason,
                'all_candidates'    => $candidates
            ];
        }
    }

    /**
     * Retrieve all active technicians with dynamic distance calculated from a specific task location.
     */
    public function getCrewsFleetForTask(string $taskIdOrReportId): array
    {
        $task = $this->getTaskById($taskIdOrReportId);
        if (!$task) {
            $sql = "SELECT r.*, t.task_id FROM damage_reports r LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id WHERE r.report_id = :id OR t.task_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $taskIdOrReportId]);
            $task = $stmt->fetch();
        }

        $repLat = (float)($task['latitude'] ?? 3.139003);
        $repLng = (float)($task['longitude'] ?? 101.686855);
        $requiredSkill = strtoupper($task['required_skill'] ?? 'OTHER');

        $crews = $this->getAllCrewsFleet();

        foreach ($crews as &$crew) {
            $crewLat = (float)$crew['current_latitude'];
            $crewLng = (float)$crew['current_longitude'];
            $distKm = \App\Utils\GeoUtil::haversineDistanceKm($crewLat, $crewLng, $repLat, $repLng);

            $hasSkill = in_array($requiredSkill, $crew['skill_tags']) 
                     || in_array('OTHER', $crew['skill_tags']) 
                     || empty($crew['skill_tags']);

            $perfScore = (float)$crew['performance_score'];
            $suitabilityScore = $perfScore / ($distKm + 0.1);

            $isCurrentAssignee = (!empty($task['assigned_crew_id']) && $crew['user_id'] === $task['assigned_crew_id']);

            $crew['distance_km'] = round($distKm, 2);
            $crew['has_skill'] = $hasSkill;
            $crew['suitability_score'] = round($suitabilityScore, 1);
            $crew['is_addon'] = ($crew['computed_status'] !== 'AVAILABLE');
            $crew['is_current_assignee'] = $isCurrentAssignee;
        }

        // Sort so that Available + matching skill appear first, then sorted by distance
        usort($crews, function ($a, $b) {
            // Keep current assignee or available near top
            if ($a['is_current_assignee'] && !$b['is_current_assignee']) return -1;
            if (!$a['is_current_assignee'] && $b['is_current_assignee']) return 1;
            if ($a['computed_status'] === 'AVAILABLE' && $b['computed_status'] !== 'AVAILABLE') return -1;
            if ($a['computed_status'] !== 'AVAILABLE' && $b['computed_status'] === 'AVAILABLE') return 1;
            return $a['distance_km'] <=> $b['distance_km'];
        });

        return [
            'task_id'          => $task['task_id'] ?? $task['report_id'],
            'report_id'        => $task['report_id'],
            'required_skill'   => $requiredSkill,
            'category'         => $task['category'] ?? 'CRITICAL_SAFETY',
            'assigned_crew_id' => $task['assigned_crew_id'] ?? null,
            'latitude'         => $repLat,
            'longitude'        => $repLng,
            'district'         => $task['district'] ?? 'Kuala Lumpur',
            'crews'            => $crews
        ];
    }

    /**
     * Manual or algorithm-confirmed crew assignment / reassignment.
     * Transitions task to PENDING_ACCEPTANCE with a 24-hour SLA window for acceptance.
     */
    public function manualAssignTask(string $taskIdOrReportId, string $crewId, ?string $actorAdminId = null, bool $isAddOn = false): array
    {
        try {
            $this->db->beginTransaction();

            // 1. Identify task and report
            $checkSql = "SELECT t.task_id, t.report_id, t.assigned_crew_id, r.status, cu.full_name as previous_crew_name, r.required_skill
                         FROM damage_reports r
                         LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                         LEFT JOIN users cu ON t.assigned_crew_id = cu.user_id
                         WHERE r.report_id = :id OR t.task_id = :id";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute(['id' => $taskIdOrReportId]);
            $record = $stmt->fetch();

            if (!$record) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Task or report not found.'];
            }

            // Guard against reassigning to the exact same currently assigned crew
            if (!empty($record['assigned_crew_id']) && $record['assigned_crew_id'] === $crewId) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error'   => 'This task is already assigned to ' . ($record['previous_crew_name'] ?: 'this technician') . '. Please select a different technician to reassign.'
                ];
            }

            // 2. Fetch new crew info
            $crewSql = "SELECT u.user_id, u.full_name, u.email, mc.availability_status
                        FROM users u
                        JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                        WHERE u.user_id = :crew_id";
            $cStmt = $this->db->prepare($crewSql);
            $cStmt->execute(['crew_id' => $crewId]);
            $crew = $cStmt->fetch();

            if (!$crew) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Selected maintenance technician not found.'];
            }

            // 3. Upsert maintenance_tasks row
            $taskId = $record['task_id'];
            if (!empty($taskId)) {
                $updateTask = "UPDATE maintenance_tasks 
                               SET assigned_crew_id = :crew_id,
                                   assigned_at = CURRENT_TIMESTAMP,
                                   sla_expires_at = CURRENT_TIMESTAMP + interval '24 hours',
                                   decline_reason = NULL,
                                   drop_reason = NULL
                               WHERE task_id = :task_id";
                $uStmt = $this->db->prepare($updateTask);
                $uStmt->execute([
                    'crew_id' => $crewId,
                    'task_id' => $taskId
                ]);
            } else {
                $insertTask = "INSERT INTO maintenance_tasks 
                               (report_id, assigned_crew_id, assigned_at, sla_expires_at) 
                               VALUES (:rep_id, :crew_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + interval '24 hours')
                               RETURNING task_id";
                $iStmt = $this->db->prepare($insertTask);
                $iStmt->execute([
                    'rep_id'  => $record['report_id'],
                    'crew_id' => $crewId
                ]);
                $taskId = $iStmt->fetch()['task_id'];
            }

            // 4. Update damage_reports status to PENDING_ACCEPTANCE
            $updateRep = "UPDATE damage_reports SET status = 'PENDING_ACCEPTANCE' WHERE report_id = :rep_id";
            $rStmt = $this->db->prepare($updateRep);
            $rStmt->execute(['rep_id' => $record['report_id']]);

            $this->db->commit();

            return [
                'success'            => true,
                'task_id'            => $taskId,
                'report_id'          => $record['report_id'],
                'status'             => 'PENDING_ACCEPTANCE',
                'assigned_crew_id'   => $crewId,
                'assigned_crew_name' => $crew['full_name'],
                'assigned_crew_email'=> $crew['email'],
                'sla_hours'          => 24,
                'is_addon'           => $isAddOn
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in TaskDAO::manualAssignTask: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}


