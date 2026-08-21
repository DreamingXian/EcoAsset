<?php

namespace App\DAO;

use App\Config\Database;
use PDO;

class AnalyticsDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Top Metrics Ribbon KPIs (Total Reported, Active Queue, Avg Resolution Time, Active Crews)
     */
    public function getKpiSummary(?string $district = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $params = [];
        $whereReport = "1=1";

        if (!empty($district) && $district !== 'All Districts') {
            $whereReport .= " AND district = :district";
            $params['district'] = $district;
        }

        if (!empty($startDate)) {
            $whereReport .= " AND created_at >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($endDate)) {
            $whereReport .= " AND created_at <= :end_date";
            $params['end_date'] = $endDate;
        }

        // 1. Total Reported Issues
        $totalStmt = $this->db->prepare("SELECT COUNT(*) as total_reported FROM damage_reports WHERE {$whereReport}");
        $totalStmt->execute($params);
        $totalReported = (int)($totalStmt->fetch()['total_reported'] ?? 0);

        // 2. Active Maintenance Queue
        $activeStmt = $this->db->prepare("SELECT COUNT(*) as active_queue FROM damage_reports WHERE status NOT IN ('COMPLETED', 'INVALID_RESOLVED') AND {$whereReport}");
        $activeStmt->execute($params);
        $activeQueue = (int)($activeStmt->fetch()['active_queue'] ?? 0);

        // 3. Average Resolution Time (in hours)
        $avgSql = "SELECT AVG(EXTRACT(EPOCH FROM (t.completed_at - r.created_at)) / 3600.0) as avg_resolution_hours
                   FROM maintenance_tasks t
                   JOIN damage_reports r ON t.report_id = r.report_id
                   WHERE r.status = 'COMPLETED' AND t.completed_at IS NOT NULL AND {$whereReport}";
        $avgStmt = $this->db->prepare($avgSql);
        $avgStmt->execute($params);
        $avgResolutionHours = (float)($avgStmt->fetch()['avg_resolution_hours'] ?? 0.0);
        $avgResolutionDays = round($avgResolutionHours / 24.0, 1);

        // 4. Crew Statistics
        $crewTotalStmt = $this->db->query("SELECT COUNT(*) as total_crews, 
                                                  SUM(CASE WHEN availability_status = 'AVAILABLE' THEN 1 ELSE 0 END) as available_crews,
                                                  SUM(CASE WHEN availability_status = 'BUSY' THEN 1 ELSE 0 END) as busy_crews
                                           FROM maintenance_crews mc
                                           JOIN users u ON mc.crew_id = u.user_id
                                           WHERE u.is_active = TRUE");
        $crewStats = $crewTotalStmt->fetch();

        return [
            'total_reported'       => $totalReported,
            'active_queue'         => $activeQueue,
            'avg_resolution_hours' => round($avgResolutionHours, 1),
            'avg_resolution_days'  => $avgResolutionDays,
            'total_crews'          => (int)($crewStats['total_crews'] ?? 0),
            'available_crews'      => (int)($crewStats['available_crews'] ?? 0),
            'busy_crews'           => (int)($crewStats['busy_crews'] ?? 0)
        ];
    }

    /**
     * Hotspot Frequency grouped by District.
     */
    public function getHotspotFrequencyByDistrict(): array
    {
        $sql = "SELECT COALESCE(district, 'Unspecified') as district, 
                       COUNT(*) as total_issues,
                       SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as resolved_issues,
                       SUM(CASE WHEN status NOT IN ('COMPLETED', 'INVALID_RESOLVED') THEN 1 ELSE 0 END) as pending_issues
                FROM damage_reports
                GROUP BY district
                ORDER BY total_issues DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Category Distribution.
     */
    public function getIssuesByCategory(): array
    {
        $sql = "SELECT category, COUNT(*) as count 
                FROM damage_reports 
                GROUP BY category 
                ORDER BY count DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Crew Workload and Performance Table.
     */
    public function getCrewWorkloadAndAvailability(): array
    {
        $sql = "SELECT u.user_id as crew_id, u.full_name, u.email,
                       mc.availability_status, mc.performance_score, mc.skill_tags, mc.station_region,
                       COUNT(CASE WHEN r.status IN ('PENDING_ACCEPTANCE', 'IN_PROGRESS') THEN 1 END) as active_tasks_count,
                       COUNT(CASE WHEN r.status = 'COMPLETED' THEN 1 END) as completed_tasks_count
                FROM users u
                JOIN maintenance_crews mc ON u.user_id = mc.crew_id
                LEFT JOIN maintenance_tasks t ON mc.crew_id = t.assigned_crew_id
                LEFT JOIN damage_reports r ON t.report_id = r.report_id
                WHERE u.is_active = TRUE
                GROUP BY u.user_id, u.full_name, u.email, mc.availability_status, mc.performance_score, mc.skill_tags, mc.station_region
                ORDER BY active_tasks_count DESC, mc.performance_score DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Public Community Impact Summary (UC-14b for Tourists & Rangers).
     */
    public function getPublicCommunityImpactSummary(): array
    {
        // 1. Issues resolved this month
        $monthSql = "SELECT COUNT(*) as solved_this_month
                     FROM damage_reports
                     WHERE status = 'COMPLETED'
                       AND created_at >= date_trunc('month', CURRENT_DATE)";
        $monthStmt = $this->db->query($monthSql);
        $solvedMonth = (int)($monthStmt->fetch()['solved_this_month'] ?? 0);

        // 2. Total active repairs
        $activeSql = "SELECT COUNT(*) as active_repairs
                      FROM damage_reports
                      WHERE status IN ('PENDING_ACCEPTANCE', 'IN_PROGRESS', 'OPEN_FOR_CLAIM')";
        $activeStmt = $this->db->query($activeSql);
        $activeRepairs = (int)($activeStmt->fetch()['active_repairs'] ?? 0);

        // 3. Top resolved districts
        $topDistrictsSql = "SELECT district, COUNT(*) as resolved_count
                            FROM damage_reports
                            WHERE status = 'COMPLETED'
                            GROUP BY district
                            ORDER BY resolved_count DESC
                            LIMIT 5";
        $distStmt = $this->db->query($topDistrictsSql);
        $topDistricts = $distStmt->fetchAll();

        return [
            'total_solved_month'   => $solvedMonth,
            'active_repairs'       => $activeRepairs,
            'top_resolved_regions' => $topDistricts
        ];
    }
}
