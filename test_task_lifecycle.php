<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\DAO\ReportDAO;
use App\DAO\TaskDAO;
use App\Models\DamageReport;
use App\Models\IssueCategory;
use App\Models\SkillCategory;
use App\Models\ReportStatus;

header('Content-Type: application/json');

$userDao = new UserDAO();
$reportDao = new ReportDAO();
$taskDao = new TaskDAO();

$suffix = time() . '_' . rand(100, 999);
$touristId = $userDao->createUser("claim_tourist_{$suffix}@ecoasset.my", 'Pass1234!', 'Claim Tourist', 'TOURIST');
$crew1Id = $userDao->createUser("crew1_{$suffix}@ecoasset.my", 'Pass1234!', 'Crew One', 'MAINTENANCE_CREW', ['skill_tags' => ['STRUCTURAL']]);
$crew2Id = $userDao->createUser("crew2_{$suffix}@ecoasset.my", 'Pass1234!', 'Crew Two', 'MAINTENANCE_CREW', ['skill_tags' => ['STRUCTURAL']]);

$report = new DamageReport(
    $touristId,
    IssueCategory::OPERATIONAL,
    'Blocked walkway with fallen tree branch',
    3.15, 101.71,
    'http://test.com/photo.jpg',
    date('Y-m-d H:i:s'),
    SkillCategory::STRUCTURAL,
    0, 0.0,
    ReportStatus::OPEN_FOR_CLAIM
);
$reportId = $reportDao->createReport($report);
$reportDao->updateReportStatus($reportId, 'OPEN_FOR_CLAIM');
$saved = $reportDao->getReportById($reportId);
$taskId = $saved['task_id'];

// 1. Crew 1 claims task
$claim1 = $taskDao->claimTask($taskId, $crew1Id);

// 2. Crew 2 attempts to claim same task (should fail/block concurrent claim)
$claim2 = $taskDao->claimTask($taskId, $crew2Id);

// 3. Crew 1 drops task due to heavy equipment required (-5 penalty)
$drop = $taskDao->dropTask($taskId, $crew1Id, 'Requires heavy crane machinery');
$crew1 = $userDao->getUserById($crew1Id);

// 4. Crew 2 now claims the released open task
$claim2AfterDrop = $taskDao->claimTask($taskId, $crew2Id);

// 5. Crew 2 flags task as invalid after inspection (-20 reputation penalty to reporter)
$flag = $taskDao->flagInvalidTask($taskId, $crew2Id, 'http://test.com/clear_site.jpg', 'Site is clear, no branches found.');
$tourist = $userDao->getUserById($touristId);
$finalReport = $reportDao->getReportById($reportId);

echo json_encode([
    'claim1_success' => $claim1['success'],
    'claim2_blocked' => !$claim2['success'],
    'drop_success' => $drop,
    'crew1_performance_score' => (float)$crew1['performance_score'], // expected 95.00
    'claim2_after_drop_success' => $claim2AfterDrop['success'],
    'flag_invalid_success' => $flag,
    'tourist_reputation_score' => (int)$tourist['reputation_score'], // expected 80
    'final_report_status' => $finalReport['status'] // expected INVALID_RESOLVED
], JSON_PRETTY_PRINT);
