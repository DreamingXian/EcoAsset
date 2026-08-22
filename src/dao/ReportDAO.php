<?php

namespace App\DAO;

use App\Config\Database;
use App\Models\DamageReport;
use App\Models\IssueCategory;
use App\Models\SkillCategory;
use App\Models\ReportStatus;
use PDO;
use Exception;

class ReportDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Map category to hazard weight (Wcat)
     * Critical Safety = 10.0, Major Blockage = 7.0, Minor Defect = 4.0, Aesthetic = 2.0
     */
    public static function getCategoryWeight(string $category): float
    {
        return match (strtoupper($category)) {
            'CRITICAL_SAFETY' => 10.0,
            'MAJOR_BLOCKAGE'  => 7.0,
            'OPERATIONAL'     => 7.0, // Backward compatibility alias
            'MINOR_DEFECT'    => 4.0,
            'AESTHETIC'       => 2.0,
            default           => 1.0,
        };
    }

    /**
     * Calculate Priority Score (Pscore) with Reporter Reputation Trust Factor
     * Formula: Pscore = [(Wcat * 0.5) + (Dzone * 0.3) + (log10(Ucount + 1) * 2.0)] * (Reputation / 100.0)
     * Default reputation is 100 (ratio 1.0). Low-reputation accounts with fake/invalid reports are deprioritized.
     */
    public static function computePScore(string $category, int $dZone, int $uCount, int $reputationScore = 100): float
    {
        $wCat = self::getCategoryWeight($category);
        $rawScore = ($wCat * 0.5) + ($dZone * 0.3) + (log10($uCount + 1) * 2.0);
        
        // Reputation ratio: 100 rep -> 1.0x, 60 rep -> 0.6x, 20 rep -> 0.2x (clamped min 0.1x)
        $reputationRatio = max(0.1, $reputationScore / 100.0);
        $finalScore = $rawScore * $reputationRatio;

        return round($finalScore, 2);
    }

    /**
     * Fetch reputation score of a reporter (defaults to 100).
     */
    public function getReporterReputation(?string $userId): int
    {
        if (empty($userId)) return 100;
        $stmt = $this->db->prepare("SELECT reputation_score FROM tourist_profiles WHERE user_id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['reputation_score'] : 100;
    }

    /**
     * Count unresolved damage reports within 200m radius (Dzone calculation).
     */
    public function countNearbyUnresolvedReports(float $latitude, float $longitude, float $radiusMeters = 200.0, ?string $excludeReportId = null): int
    {
        // 1 deg lat ~ 111.045 km, 1 deg lon ~ 111.045 * cos(lat) km
        $sql = "SELECT COUNT(*) as density_count
                FROM damage_reports
                WHERE status NOT IN ('COMPLETED', 'INVALID_RESOLVED')
                  AND (:exclude_id::UUID IS NULL OR report_id != :exclude_id::UUID)
                  AND (
                    6371000 * acos(
                      LEAST(1.0, GREATEST(-1.0,
                        cos(radians(:lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians(:lng)) +
                        sin(radians(:lat)) * sin(radians(latitude))
                      ))
                    )
                  ) <= :radius";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lat'        => $latitude,
            'lng'        => $longitude,
            'radius'     => $radiusMeters,
            'exclude_id' => $excludeReportId
        ]);

        $res = $stmt->fetch();
        return (int)($res['density_count'] ?? 0);
    }

    /**
     * Ingest a new damage report into the database and calculate baseline Pscore.
     */
    public function createReport(DamageReport $report): ?string
    {
        try {
            // 1. Ensure initial upvote is 1 (representing creator)
            $initialUpvotes = max(1, $report->getUpvoteCount());
            $report->setUpvoteCount($initialUpvotes);

            // 2. Fetch reporter's reputation trust score
            $repScore = $this->getReporterReputation($report->getUserId());

            // 3. Calculate local hotspot density (Dzone within 200m)
            $dZone = $this->countNearbyUnresolvedReports($report->getLatitude(), $report->getLongitude(), 200.0);

            // 4. Compute initial Pscore (weighted by reputation)
            $pScore = self::computePScore(
                $report->getCategory()->value,
                $dZone,
                $initialUpvotes,
                $repScore
            );
            $report->setPriorityScore($pScore);

            $sql = "INSERT INTO damage_reports 
                    (user_id, category, required_skill, description, latitude, longitude, photo_url, exif_timestamp, upvote_count, priority_score, status, district) 
                    VALUES (:user_id, :category, :required_skill, :description, :lat, :lng, :photo_url, :exif_time, :upvotes, :pscore, :status, :district) 
                    RETURNING report_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id'        => $report->getUserId(),
                'category'       => $report->getCategory()->value,
                'required_skill' => $report->getRequiredSkill()->value,
                'description'    => $report->getDescription(),
                'lat'            => $report->getLatitude(),
                'lng'            => $report->getLongitude(),
                'photo_url'      => $report->getPhotoUrl(),
                'exif_time'      => $report->getExifTimestamp(),
                'upvotes'        => $initialUpvotes,
                'pscore'         => $pScore,
                'status'         => $report->getStatus()->value,
                'district'       => $report->getDistrict()
            ]);

            $result = $stmt->fetch();
            $newReportId = $result['report_id'] ?? null;

            if ($newReportId) {
                $report->setReportId($newReportId);

                // Auto-create associated maintenance task in pending state
                $taskSql = "INSERT INTO maintenance_tasks (report_id) VALUES (:report_id) ON CONFLICT (report_id) DO NOTHING";
                $taskStmt = $this->db->prepare($taskSql);
                $taskStmt->execute(['report_id' => $newReportId]);
            }

            return $newReportId;
        } catch (Exception $e) {
            error_log("Error in ReportDAO::createReport: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch single report by UUID.
     */
    public function getReportById(string $reportId): ?array
    {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email,
                       t.task_id, t.assigned_crew_id, t.assigned_at, t.sla_expires_at,
                       cu.full_name as assigned_crew_name
                FROM damage_reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                LEFT JOIN users cu ON t.assigned_crew_id = cu.user_id
                WHERE r.report_id = :report_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['report_id' => $reportId]);
        $data = $stmt->fetch();
        return $data ?: null;
    }

    /**
     * Fetch all active incident pins for the public interactive map.
     */
    public function getActiveReports(?float $lat = null, ?float $lng = null, ?float $radiusKm = null): array
    {
        $sql = "SELECT r.report_id, r.user_id, r.category, r.required_skill, r.description,
                       r.latitude, r.longitude, r.photo_url, r.exif_timestamp,
                       r.upvote_count, r.priority_score, r.status, r.district, r.created_at,
                       u.full_name as reporter_name
                FROM damage_reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.status NOT IN ('COMPLETED', 'INVALID_RESOLVED')";

        $params = [];

        if ($lat !== null && $lng !== null && $radiusKm !== null) {
            $sql .= " AND (
                        6371 * acos(
                          LEAST(1.0, GREATEST(-1.0,
                            cos(radians(:lat)) * cos(radians(r.latitude)) * cos(radians(r.longitude) - radians(:lng)) +
                            sin(radians(:lat)) * sin(radians(r.latitude))
                          ))
                        )
                      ) <= :radius";
            $params['lat'] = $lat;
            $params['lng'] = $lng;
            $params['radius'] = $radiusKm;
        }

        $sql .= " ORDER BY r.priority_score DESC, r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Check if a specific user has already upvoted a report.
     */
    public function hasUserUpvoted(string $reportId, string $userId): bool
    {
        $sql = "SELECT 1 FROM report_upvotes WHERE report_id = :report_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'report_id' => $reportId,
            'user_id'   => $userId
        ]);
        return (bool)$stmt->fetch();
    }

    /**
     * Register an upvote, prevent duplicates, and dynamically recalculate Pscore.
     */
    public function addUpvote(string $reportId, string $userId): array
    {
        try {
            $this->db->beginTransaction();

            // 1. Fetch current report data and check if author is attempting to upvote
            $checkStmt = $this->db->prepare("SELECT user_id, category, latitude, longitude, upvote_count FROM damage_reports WHERE report_id = :id");
            $checkStmt->execute(['id' => $reportId]);
            $reportData = $checkStmt->fetch();

            if (!$reportData) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Report not found'];
            }

            if ($reportData['user_id'] === $userId) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'You are the creator of this report and already counted as 1 upvote.'];
            }

            // 2. Check if already upvoted
            if ($this->hasUserUpvoted($reportId, $userId)) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'User has already upvoted this incident'];
            }

            // 3. Insert into report_upvotes
            $insertUpvote = "INSERT INTO report_upvotes (report_id, user_id) VALUES (:report_id, :user_id)";
            $stmt = $this->db->prepare($insertUpvote);
            $stmt->execute(['report_id' => $reportId, 'user_id' => $userId]);

            // 4. Increment upvote count & fetch updated count
            $updateCount = "UPDATE damage_reports 
                            SET upvote_count = upvote_count + 1 
                            WHERE report_id = :report_id 
                            RETURNING category, latitude, longitude, upvote_count";
            $stmt = $this->db->prepare($updateCount);
            $stmt->execute(['report_id' => $reportId]);
            $updatedData = $stmt->fetch();

            if (!$updatedData) {
                throw new Exception("Report update failed");
            }

            // 5. Recalculate Dzone & Pscore (weighted by reporter's reputation)
            $dZone = $this->countNearbyUnresolvedReports(
                (float)$updatedData['latitude'],
                (float)$updatedData['longitude'],
                200.0,
                $reportId
            );

            $repScore = $this->getReporterReputation($reportData['user_id']);

            $newPScore = self::computePScore(
                $updatedData['category'],
                $dZone,
                (int)$updatedData['upvote_count'],
                $repScore
            );

            $updateScore = "UPDATE damage_reports SET priority_score = :pscore WHERE report_id = :report_id";
            $stmt = $this->db->prepare($updateScore);
            $stmt->execute([
                'pscore'    => $newPScore,
                'report_id' => $reportId
            ]);

            $this->db->commit();

            return [
                'success'        => true,
                'upvote_count'   => (int)$updatedData['upvote_count'],
                'priority_score' => $newPScore
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in ReportDAO::addUpvote: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update report status.
     */
    public function updateReportStatus(string $reportId, string $status): bool
    {
        $sql = "UPDATE damage_reports SET status = :status WHERE report_id = :report_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status'    => strtoupper($status),
            'report_id' => $reportId
        ]);
    }

    /**
     * Get Prioritized Maintenance Queue for Admin & Dispatcher.
     */
    public function getPrioritizedQueue(?string $district = null, ?string $category = null): array
    {
        $sql = "SELECT r.report_id, r.category, r.required_skill, r.description,
                       r.latitude, r.longitude, r.upvote_count, r.priority_score,
                       r.status, r.district, r.created_at, r.photo_url,
                       t.task_id, t.assigned_crew_id, t.assigned_at, t.sla_expires_at,
                       u.full_name as reporter_name,
                       cu.full_name as assigned_crew_name
                FROM damage_reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id
                LEFT JOIN users cu ON t.assigned_crew_id = cu.user_id
                WHERE r.status NOT IN ('COMPLETED', 'INVALID_RESOLVED')";

        $params = [];

        if (!empty($district) && $district !== 'All Districts') {
            $sql .= " AND r.district = :district";
            $params['district'] = $district;
        }

        if (!empty($category)) {
            $sql .= " AND r.category = :category";
            $params['category'] = strtoupper($category);
        }

        $sql .= " ORDER BY r.priority_score DESC, r.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
