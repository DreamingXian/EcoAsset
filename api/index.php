<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Basic Router
if ($uri === '/api/v1/auth/login' && $method === 'POST') {
  // Call AuthController->login()
  echo json_encode(["message" => "Login endpoint reached"]);
} elseif ($uri === '/api/v1/reports' && $method === 'GET') {
  // Call ReportController->getActiveReports()
  echo json_encode(["message" => "Get reports endpoint reached"]);
} else {
  http_response_code(404);
  echo json_encode(["error" => "Endpoint not found"]);
}
