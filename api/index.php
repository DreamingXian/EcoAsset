<?php
require_once __DIR__ . '/../src/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\ReportController;
use App\Controllers\CrewController;
use App\Controllers\TaskController;
use App\Controllers\AdminController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalise path (handle subfolder hosting or direct API access)
// e.g. /api/index.php/api/v1/auth/login or /api/v1/auth/login
$cleanUri = preg_replace('#^/api/index\.php#', '', $uri);
if (empty($cleanUri)) {
    $cleanUri = '/';
}

// -------------------------------------------------------------
// Router Definitions
// -------------------------------------------------------------

// 1. Auth Routes
if ($method === 'POST' && ($cleanUri === '/api/v1/auth/login' || $uri === '/api/v1/auth/login')) {
    (new AuthController())->login();
} elseif ($method === 'POST' && in_array($cleanUri, ['/api/v1/auth/signup', '/api/v1/auth/register'])) {
    (new AuthController())->register();
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/auth/forgot-password' || $uri === '/api/v1/auth/forgot-password')) {
    (new AuthController())->forgotPassword();
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/auth/reset-password' || $uri === '/api/v1/auth/reset-password')) {
    (new AuthController())->resetPassword();
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/auth/me' || $uri === '/api/v1/auth/me')) {
    (new AuthController())->me();

// 2. Damage Report Routes
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/reports' || $uri === '/api/v1/reports')) {
    (new ReportController())->createReport();
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/reports' || $uri === '/api/v1/reports')) {
    (new ReportController())->getActiveReports();
} elseif ($method === 'POST' && preg_match('#^/api/v1/reports/([a-f0-9\-]+)/upvote$#i', $cleanUri, $m)) {
    (new ReportController())->upvoteReport($m[1]);
} elseif ($method === 'GET' && preg_match('#^/api/v1/reports/([a-f0-9\-]+)$#i', $cleanUri, $m)) {
    (new ReportController())->getReportById($m[1]);

// 3. Citizen Activity History & User Profile Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/user/activity' || $uri === '/api/v1/user/activity')) {
    (new ReportController())->getUserActivity();
} elseif (($method === 'PUT' || $method === 'POST') && ($cleanUri === '/api/v1/user/profile' || $uri === '/api/v1/user/profile')) {
    (new AuthController())->updateProfile();

// 4. Maintenance Crew Profile & Capabilities Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/crew/profile' || $uri === '/api/v1/crew/profile')) {
    (new CrewController())->getProfile();
} elseif (($method === 'PUT' || $method === 'POST') && ($cleanUri === '/api/v1/crew/profile' || $uri === '/api/v1/crew/profile')) {
    (new CrewController())->updateProfile();

// 5. Module 2: Automated Dispatch Prioritization Engine Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/dispatch/queue' || $uri === '/api/v1/dispatch/queue')) {
    (new TaskController())->getDispatchQueue();
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/dispatch/run' || $uri === '/api/v1/dispatch/run')) {
    (new TaskController())->triggerAutoDispatch();

// 6. Module 3: Maintenance Tasks & Work Orders Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/tasks/my-tasks' || $uri === '/api/v1/tasks/my-tasks')) {
    (new TaskController())->getMyTasks();
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/tasks/open-pool' || $uri === '/api/v1/tasks/open-pool')) {
    (new TaskController())->getOpenPool();
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/accept$#i', $cleanUri, $m)) {
    (new TaskController())->acceptTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/decline$#i', $cleanUri, $m)) {
    (new TaskController())->declineTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/claim$#i', $cleanUri, $m)) {
    (new TaskController())->claimTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/complete$#i', $cleanUri, $m)) {
    (new TaskController())->completeTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/drop$#i', $cleanUri, $m)) {
    (new TaskController())->dropTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/tasks/([a-f0-9\-]+)/flag-invalid$#i', $cleanUri, $m)) {
    (new TaskController())->flagInvalid($m[1]);
// 7. Module 3: Admin Task Control Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/admin/tasks' || $uri === '/api/v1/admin/tasks')) {
    (new AdminController())->getTasks();
} elseif ($method === 'GET' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/candidates$#i', $cleanUri, $m)) {
    (new AdminController())->getCrewCandidatesForTask($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/auto-dispatch-preview$#i', $cleanUri, $m)) {
    (new AdminController())->previewSingleTaskAutoDispatch($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/assign$#i', $cleanUri, $m)) {
    (new AdminController())->assignCrew($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/approve-complete$#i', $cleanUri, $m)) {
    (new AdminController())->approveCompletion($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/reject-complete$#i', $cleanUri, $m)) {
    (new AdminController())->rejectCompletion($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/return-open-pool$#i', $cleanUri, $m)) {
    (new AdminController())->returnToOpenPool($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/approve-invalid$#i', $cleanUri, $m)) {
    (new AdminController())->approveInvalid($m[1]);
} elseif ($method === 'POST' && preg_match('#^/api/v1/admin/tasks/([a-f0-9\-]+)/reject-invalid$#i', $cleanUri, $m)) {
    (new AdminController())->rejectInvalid($m[1]);
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/admin/tasks/batch-dispatch' || $uri === '/api/v1/admin/tasks/batch-dispatch')) {
    (new AdminController())->runBatchDispatch();

// 8. Module 5: Admin User Management Routes
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/admin/users' || $uri === '/api/v1/admin/users')) {
    (new AdminController())->getUsers();
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/admin/users' || $uri === '/api/v1/admin/users')) {
    (new AdminController())->createUser();
} elseif (($method === 'PATCH' || $method === 'POST') && preg_match('#^/api/v1/admin/users/([a-f0-9\-]+)/status$#i', $cleanUri, $m)) {
    (new AdminController())->updateStatus($m[1]);
} elseif (($method === 'PUT' || $method === 'POST') && preg_match('#^/api/v1/admin/users/([a-f0-9\-]+)$#i', $cleanUri, $m)) {
    (new AdminController())->updateUser($m[1]);

// 9. API Welcome / Root Route
} elseif ($cleanUri === '/' || $cleanUri === '/api' || $cleanUri === '/api/v1') {

    echo json_encode([
        "name" => "EcoAsset REST API",
        "version" => "1.0.0",
        "status" => "online",
        "timestamp" => date('c'),
        "endpoints" => [
            "POST /api/v1/auth/login"           => "User Authentication & JWT Issuance",
            "POST /api/v1/auth/signup"          => "Tourist Registration & JWT Issuance",
            "POST /api/v1/auth/forgot-password" => "Password Reset Token Dispatch",
            "GET  /api/v1/auth/me"              => "Session Profile Inspection (requires Bearer token)",
            "GET  /api/v1/reports"              => "Damage Reports List",
            "POST /api/v1/reports"              => "Submit Damage Report (Photo + EXIF check)",
            "POST /api/v1/reports/{id}/upvote"  => "Upvote Damage Report Pin",
            "GET  /api/v1/user/activity"        => "User Report & Upvote Activity History"
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "error" => "Endpoint not found",
        "requested_uri" => $uri,
        "clean_uri" => $cleanUri,
        "method" => $method
    ], JSON_PRETTY_PRINT);
}
