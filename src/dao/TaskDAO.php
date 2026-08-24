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
                  AND r.status IN ('PENDING_ACCEPTANCE', 'IN_PROGRESS', 'PRECOMPLETE', 'DROPPED', 'PRE_INVALID', 'COMPLETED')
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
            $fetchSql = "SELECT assigned_crew_id, report_id FROM maintenance_tasks WHERE task_id = :task_id";
            $fetchStmt = $this->db->prepare($fetchSql);
            $fetchStmt->execute(['task_id' => $taskId]);
            $task = $fetchStmt->fetch();

            if (!$task) {
                throw new Exception("Task not found.");
            }

            // 1. Update task record with completion proof (completed_at remains NULL until admin approval)
            $taskSql = "UPDATE maintenance_tasks 
                        SET completion_photo_url = :photo,
                            resolution_notes = :notes
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'   => $photoUrl,
                'notes'   => $notes,
                'task_id' => $taskId
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
     * Flag incident as Invalid / No Fault Found with clear-site photo proof -> transitions to PRE_INVALID.
     */
    public function flagInvalidTask(string $taskId, string $crewId, string $photoUrl, string $notes): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch report ID
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

            // 1. Update task with clear-site proof & findings
            $taskSql = "UPDATE maintenance_tasks 
                        SET flag_invalid_photo_url = :photo,
                            flag_invalid_notes = :notes
                        WHERE task_id = :task_id";
            $taskStmt = $this->db->prepare($taskSql);
            $taskStmt->execute([
                'photo'   => $photoUrl,
                'notes'   => $notes,
                'task_id' => $taskId
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
}

