<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Utils\JWTUtil;

$userDAO = new UserDAO();
$email = 'tourist@ecoasset.my';
$newPass = 'NewPass2026!';

// 1. Generate token
$user = $userDAO->getUserByEmail($email);
$token = JWTUtil::generateToken([
    'user_id' => $user['user_id'],
    'email'   => $user['email'],
    'type'    => 'password_reset'
], 3600);

echo "Token generated: $token\n";

// 2. Validate token
$payload = JWTUtil::validateToken($token);
assert($payload['email'] === $email);

// 3. Update password in DB
$hash = password_hash($newPass, PASSWORD_BCRYPT);
$db = \App\Config\Database::getConnection();
$stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
$stmt->execute(['hash' => $hash, 'id' => $user['user_id']]);

// 4. Test login with new password
$updatedUser = $userDAO->getUserByEmail($email);
$verify = password_verify($newPass, $updatedUser['password_hash']);

echo "Password update test: " . ($verify ? "SUCCESS" : "FAILED") . "\n";
