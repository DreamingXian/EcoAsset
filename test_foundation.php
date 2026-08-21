<?php
require_once __DIR__ . '/src/autoload.php';

use App\Config\Database;
use App\Models\UserRole;
use App\Models\SkillCategory;
use App\Models\AvailabilityState;
use App\Models\IssueCategory;
use App\Models\ReportStatus;
use App\Models\DamageReport;
use App\DAO\UserDAO;
use App\DAO\ReportDAO;
use App\DAO\TaskDAO;
use App\DAO\AuditLogDAO;
use App\DAO\AnalyticsDAO;
use App\Utils\JWTUtil;
use App\Utils\GeoUtil;
use App\Utils\StorageUtil;

header('Content-Type: application/json');

$results = [
    'step1_database' => false,
    'step2_jwt_util' => false,
    'step3_geo_util' => false,
    'step4_user_dao' => false,
    'step5_report_dao' => false,
    'step6_task_dao' => false,
    'step7_analytics_dao' => false,
    'step8_audit_dao' => false,
    'errors' => []
];

try {
    // 1. Test Database
    $pdo = Database::getConnection();
    $results['step1_database'] = ($pdo !== null);

    // 2. Test JWT Util
    $testPayload = ['user_id' => '00000000-0000-0000-0000-000000000001', 'role' => 'ADMINISTRATOR', 'email' => 'admin@ecoasset.my'];
    $token = JWTUtil::generateToken($testPayload);
    $decoded = JWTUtil::validateToken($token);
    $results['step2_jwt_util'] = ($decoded && $decoded['role'] === 'ADMINISTRATOR');

    // 3. Test GeoUtil (Haversine & 50m tolerance)
    // KLCC (3.1578, 101.7119) to Pavilion KL (3.1488, 101.7133) ~ 1.0 km
    $distKm = GeoUtil::haversineDistanceKm(3.1578, 101.7119, 3.1488, 101.7133);
    $isNear = GeoUtil::isWithinTolerance(3.15780, 101.71190, 3.15782, 101.71192, 50.0); // very close
    $isFar = GeoUtil::isWithinTolerance(3.1578, 101.7119, 3.1488, 101.7133, 50.0); // ~1km
    $results['step3_geo_util'] = ($distKm > 0.8 && $distKm < 1.3 && $isNear === true && $isFar === false);

    // 4. Test UserDAO (Create 4 actor profiles)
    $userDao = new UserDAO();
    $uniqueSuffix = time() . '_' . rand(100, 999);

    // 4.1 Tourist
    $touristId = $userDao->createUser("tourist_{$uniqueSuffix}@ecoasset.my", "Pass1234!", "Test Tourist", "TOURIST");
    $tourist = $userDao->getUserById($touristId);

    // 4.2 Ranger
    $rangerId = $userDao->createUser("ranger_{$uniqueSuffix}@ecoasset.my", "Pass1234!", "Officer Roslan", "SITE_RANGER", [
        'station_region' => 'Kuala Lumpur',
        'employee_id' => "RNG-{$uniqueSuffix}"
    ]);
    $ranger = $userDao->getUserById($rangerId);

    // 4.3 Crew
    $crewId = $userDao->createUser("crew_{$uniqueSuffix}@ecoasset.my", "Pass1234!", "Tech Ahmad", "MAINTENANCE_CREW", [
        'skill_tags' => ['STRUCTURAL', 'ELECTRICAL'],
        'current_latitude' => 3.1390,
        'current_longitude' => 101.6868,
        'station_region' => 'Kuala Lumpur'
    ]);
    $crew = $userDao->getUserById($crewId);

    $results['step4_user_dao'] = (
        $tourist && $tourist['reputation_score'] == 100 &&
        $ranger && $ranger['ranger_station'] === 'Kuala Lumpur' &&
        $crew && in_array('STRUCTURAL', $crew['skill_tags'])
    );

    // 5. Test ReportDAO (Create Report, Calculate Pscore, Upvote)
    $reportDao = new ReportDAO();
    $report = new DamageReport(
        $touristId,
        IssueCategory::CRITICAL_SAFETY,
        "Broken metal handrail on pedestrian bridge",
        3.157900,
        101.712000,
        StorageUtil::getPublicUrl(StorageUtil::FOLDER_REPORTS, 'test_handrail.jpg'),
        date('Y-m-d H:i:s'),
        SkillCategory::STRUCTURAL,
        0,
        0.0,
        ReportStatus::PENDING_VALIDATION,
        'Kuala Lumpur'
    );

    $reportId = $reportDao->createReport($report);
    $savedReport = $reportDao->getReportById($reportId);
    
    // Test Upvote
    $upvoteRes = $reportDao->addUpvote($reportId, $touristId);
    $duplicateUpvoteRes = $reportDao->addUpvote($reportId, $touristId); // should be blocked
    $upvotedReport = $reportDao->getReportById($reportId);

    $results['step5_report_dao'] = (
        $reportId !== null &&
        $savedReport['priority_score'] > 0 &&
        $upvoteRes['success'] === true &&
        $duplicateUpvoteRes['success'] === false &&
        $upvotedReport['upvote_count'] === 1
    );

    // 6. Test TaskDAO (Manual Assign, Accept, Complete, Reset Crew)
    $taskDao = new TaskDAO();
    $taskId = $savedReport['task_id'];

    // Manual Assign (Admin assigns to technician)
    $assignSuccess = $taskDao->manualAssignTask($taskId, $crewId);

    // Accept task (Technician accepts)
    $acceptSuccess = $taskDao->acceptTask($taskId, $crewId);
    
    // Complete task with photo
    $completeSuccess = $taskDao->completeTask(
        $taskId,
        StorageUtil::getPublicUrl(StorageUtil::FOLDER_COMPLETIONS, 'test_fixed.jpg'),
        "Welded and reinforced handrail with stainless steel bracket."
    );

    $completedTask = $taskDao->getTaskById($taskId);
    $updatedCrew = $userDao->getUserById($crewId);

    $results['step6_task_dao'] = (
        $assignSuccess &&
        $acceptSuccess &&
        $completeSuccess &&
        $completedTask['report_status'] === 'COMPLETED' &&
        $updatedCrew['availability_status'] === 'AVAILABLE'
    );

    // 7. Test AnalyticsDAO
    $analyticsDao = new AnalyticsDAO();
    $kpi = $analyticsDao->getKpiSummary('Kuala Lumpur');
    $hotspots = $analyticsDao->getHotspotFrequencyByDistrict();
    $publicSummary = $analyticsDao->getPublicCommunityImpactSummary();

    $results['step7_analytics_dao'] = (
        isset($kpi['total_reported']) &&
        is_array($hotspots) &&
        isset($publicSummary['total_solved_month'])
    );

    // 8. Test AuditLogDAO
    $auditDao = new AuditLogDAO();
    $auditSuccess = $auditDao->log($crewId, 'TASK_COMPLETED', "Completed repair on report {$reportId}");
    $logs = $auditDao->getRecentLogs(5);
    $results['step8_audit_dao'] = ($auditSuccess && count($logs) > 0);

} catch (Exception $e) {
    $results['errors'][] = $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
}

$allPassed = !in_array(false, [
    $results['step1_database'],
    $results['step2_jwt_util'],
    $results['step3_geo_util'],
    $results['step4_user_dao'],
    $results['step5_report_dao'],
    $results['step6_task_dao'],
    $results['step7_analytics_dao'],
    $results['step8_audit_dao']
], true) && empty($results['errors']);

$results['all_passed'] = $allPassed;

echo json_encode($results, JSON_PRETTY_PRINT);
