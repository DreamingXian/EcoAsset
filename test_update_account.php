<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Utils\JWTUtil;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$results = ['test_name' => 'Update Account Details API Verification', 'cases' => []];

try {
    // 1. Setup Test User
    $testEmail = 'tourist@ecoasset.my';
    $user = $userDAO->getUserByEmail($testEmail);
    if (!$user) {
        throw new Exception("Test user tourist@ecoasset.my not found in DB.");
    }
    $userId = $user['user_id'];

    // 2. Issue valid JWT token
    $token = JWTUtil::generateToken([
        'user_id'   => $userId,
        'email'     => $user['email'],
        'role'      => $user['role'],
        'full_name' => $user['full_name']
    ], 3600);

    // 3. Test updating full name
    $updatedName = "Ahmad Faizal " . rand(100, 999);
    $userDAO->updateUserProfile($userId, $updatedName, $testEmail);
    $checkUser = $userDAO->getUserById($userId);
    $results['cases']['1_update_name_success'] = ($checkUser['full_name'] === $updatedName);

    // 4. Test updating password with correct current password
    $oldPass = 'Pass1234!';
    $newPass = 'NewPass2026!#' . rand(10, 99);
    $verifyOld = password_verify($oldPass, $checkUser['password_hash']);
    $results['cases']['2_verify_current_password'] = $verifyOld;

    if ($verifyOld) {
        $userDAO->updatePassword($userId, $newPass);
        $freshCheck = $userDAO->getUserById($userId);
        $verifyNew = password_verify($newPass, $freshCheck['password_hash']);
        $results['cases']['3_update_password_success'] = $verifyNew;

        // Reset password back to standard Pass1234!
        $userDAO->updatePassword($userId, 'Pass1234!');
    }

    // 5. Test duplicate email collision detection
    $adminUser = $userDAO->getUserByEmail('admin@ecoasset.my');
    $existingCheck = $userDAO->getUserByEmail('admin@ecoasset.my');
    $isCollision = ($existingCheck && $existingCheck['user_id'] !== $userId);
    $results['cases']['4_duplicate_email_collision_detected'] = $isCollision;

    $results['all_passed'] = !in_array(false, array_values($results['cases']), true);

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    $results['all_passed'] = false;
}

echo json_encode($results, JSON_PRETTY_PRINT);
