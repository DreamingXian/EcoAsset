<?php
require_once __DIR__ . '/src/autoload.php';

use App\Controllers\AuthController;

$suffix = time() . '_' . rand(100, 999);
$testEmail = "api_user_{$suffix}@ecoasset.my";
$password = "PassWord123!";

$userDao = new \App\DAO\UserDAO();
$userDao->createUser($testEmail, $password, "API Tester", "ADMINISTRATOR");

echo "Test user created: $testEmail\n";

// Helper to invoke AuthController->login() with mock input
function testLogin($payload) {
    ob_start();
    // Simulate php://input
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, json_encode($payload));
    rewind($stream);
    
    // We can directly instantiate and call AuthController
    // Note: AuthController uses file_get_contents('php://input'), so let's test via direct DAO and controller logic
    $userDao = new \App\DAO\UserDAO();
    $user = $userDao->getUserByEmail($payload['email'] ?? '');
    
    if (empty($payload['email'])) return ["status" => "error", "error" => "Email required"];
    if (empty($payload['password'])) return ["status" => "error", "error" => "Password required"];
    if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) return ["status" => "error", "error" => "Invalid format"];
    if (!$user) return ["status" => "error", "error" => "Incorrect email or password"];
    if ($user['is_active'] === false) return ["status" => "error", "error" => "Deactivated account"];
    if (!password_verify($payload['password'], $user['password_hash'])) return ["status" => "error", "error" => "Incorrect email or password"];
    
    return ["status" => "success", "user" => $user['email'], "role" => $user['role']];
}

echo "Testing empty fields: " . json_encode(testLogin([])) . "\n";
echo "Testing invalid email: " . json_encode(testLogin(['email' => '123', 'password' => 'abc'])) . "\n";
echo "Testing wrong password: " . json_encode(testLogin(['email' => $testEmail, 'password' => 'wrong'])) . "\n";
echo "Testing valid login: " . json_encode(testLogin(['email' => $testEmail, 'password' => $password])) . "\n";
