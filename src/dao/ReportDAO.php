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
     * Critical Safety = 10, Operational = 7, Aesthetic = 4
     */
    public static function getCategoryWeight(string $category): float
    {
        return match (strtoupper($category)) {
            'CRITICAL_SAFETY' => 10.0,
            'OPERATIONAL'     => 7.0,
            'AESTHETIC'       => 4.0,
            default           => 1.0,
        };
    }

    /**
     * Calculate Priority Score (Pscore)
     * Formula: Pscore = (Wcat * 0.5) + (Dzone * 0.3) + (log10(Ucount + 1) * 2.0)
     */
    public static function computePScore(string $category, int $dZone, int $uCount): float
    {
        $wCat = self::getCategoryWeight($category);
        $score = ($wCat * 0.5) + ($dZone * 0.3) + (log10($uCount + 1) * 2.0);
        return round($score, 2);
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
            // 1. Calculate local hotspot density (Dzone within 200m)
            $dZone = $this->countNearbyUnresolvedReports($report->getLatitude(), $report->getLongitude(), 200.0);

            // 2. Compute initial Pscore
            $pScore = self::computePScore(
                $report->getCategory()->value,
                $dZone,
                $report->getUpvoteCount()
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
                'upvotes'        => $report->getUpvoteCount(),
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

            // 1. Check if already upvoted
            if ($this->hasUserUpvoted($reportId, $userId)) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'User has already upvoted this incident'];
            }

            // 2. Insert into report_upvotes
            $insertUpvote = "INSERT INTO report_upvotes (report_id, user_id) VALUES (:report_id, :user_id)";
            $stmt = $this->db->prepare($insertUpvote);
            $stmt->execute(['report_id' => $reportId, 'user_id' => $userId]);

            // 3. Increment upvote count & fetch current data
            $updateCount = "UPDATE damage_reports 
                            SET upvote_count = upvote_count + 1 
                            WHERE report_id = :report_id 
                            RETURNING category, latitude, longitude, upvote_count";
            $stmt = $this->db->prepare($updateCount);
            $stmt->execute(['report_id' => $reportId]);
            $reportData = $stmt->fetch();

            if (!$reportData) {
                throw new Exception("Report not found");
            }

            // 4. Recalculate Dzone & Pscore
            $dZone = $this->countNearbyUnresolvedReports(
                (float)$reportData['latitude'],
                (float)$reportData['longitude'],
                200.0,
                $reportId
            );

            $newPScore = self::computePScore(
                $reportData['category'],
                $dZone,
                (int)$reportData['upvote_count']
            );

            // 5. Update priority_score
            $updateScore = "UPDATE damage_reports SET priority_score = :pscore WHERE report_id = :report_id";
            $stmt = $this->db->prepare($updateScore);
            $stmt->execute(['pscore' => $newPScore, 'report_id' => $reportId]);

            $this->db->commit();

            return [
                'success'        => true,
                'report_id'      => $reportId,
                'upvote_count'   => (int)$reportData['upvote_count'],
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
