<?php
require_once __DIR__ . '/../src/autoload.php';

use App\Controllers\AuthController;

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
if ($method === 'POST' && ($cleanUri === '/api/v1/auth/login' || $uri === '/api/v1/auth/login')) {
    $authController = new AuthController();
    $authController->login();
} elseif ($method === 'POST' && ($cleanUri === '/api/v1/auth/signup' || $uri === '/api/v1/auth/signup' || $cleanUri === '/api/v1/auth/register' || $uri === '/api/v1/auth/register')) {
    $authController = new AuthController();
    $authController->register();
} elseif ($method === 'GET' && ($cleanUri === '/api/v1/auth/me' || $uri === '/api/v1/auth/me')) {
    $authController = new AuthController();
    $authController->me();
} elseif ($cleanUri === '/' || $cleanUri === '/api' || $cleanUri === '/api/v1') {
    // API Welcome / Health Check Info
    echo json_encode([
        "name" => "EcoAsset REST API",
        "version" => "1.0.0",
        "status" => "online",
        "timestamp" => date('c'),
        "endpoints" => [
            "POST /api/v1/auth/login"  => "User Authentication & JWT Issuance",
            "POST /api/v1/auth/signup" => "Tourist Registration & JWT Issuance",
            "GET  /api/v1/auth/me"     => "Session Profile Inspection (requires Bearer token)",
            "GET  /api/v1/reports"     => "Damage Reports List",
            "POST /api/v1/reports"     => "Submit Damage Report"
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "error" => "Endpoint not found",
        "requested_uri" => $uri,
        "method" => $method
    ], JSON_PRETTY_PRINT);
}

