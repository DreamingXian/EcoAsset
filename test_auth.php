<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Utils\JWTUtil;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$results = [
    'test_name' => 'Authentication & Login Verification',
    'cases' => []
];

// 1. Create a test active user & a test deactivated user for testing
$suffix = time() . '_' . rand(100, 999);
$activeEmail = "active_user_{$suffix}@ecoasset.my";
$deactivatedEmail = "byebye_{$suffix}@email.com";
$password = "Secret123!";

try {
    // 1.1 Create active user
    $activeUserId = $userDAO->createUser($activeEmail, $password, "Active Test User", "MAINTENANCE_CREW", [
        'skill_tags' => ['STRUCTURAL'],
        'station_region' => 'Kuala Lumpur'
    ]);

    // 1.2 Create user to deactivate
    $deactivatedUserId = $userDAO->createUser($deactivatedEmail, $password, "Deactivated User", "TOURIST");
    
    // Deactivate user in DB
    $db = \App\Config\Database::getConnection();
    $db->prepare("UPDATE users SET is_active = FALSE WHERE user_id = :id")->execute(['id' => $deactivatedUserId]);

    // 2. Test Case 1: Valid Login
    $user = $userDAO->getUserByEmail($activeEmail);
    $validPass = password_verify($password, $user['password_hash']);
    $results['cases']['1_valid_credentials'] = ($user && $validPass && $user['is_active'] == true);

    // 3. Test Case 2: Wrong Password
    $wrongPass = password_verify("WrongPassword!", $user['password_hash']);
    $results['cases']['2_wrong_password_rejected'] = ($wrongPass === false);

    // 4. Test Case 3: Deactivated User Detection
    $deactUser = $userDAO->getUserByEmail($deactivatedEmail);
    $isDeactivated = ($deactUser && ($deactUser['is_active'] === false || $deactUser['is_active'] === 0));
    $results['cases']['3_deactivated_account_detected'] = $isDeactivated;

    // 5. Test Case 4: Non-existent Email
    $nonExistent = $userDAO->getUserByEmail("nonexistent_{$suffix}@domain.com");
    $results['cases']['4_non_existent_user_null'] = ($nonExistent === null);

    // 6. Test Case 5: JWT Generation and Verification
    $token = JWTUtil::generateToken([
        'user_id' => $user['user_id'],
        'email' => $user['email'],
        'role' => $user['role']
    ]);
    $decoded = JWTUtil::validateToken($token);
    $results['cases']['5_jwt_issuance_and_validation'] = ($decoded && $decoded['user_id'] === $user['user_id'] && $decoded['role'] === 'MAINTENANCE_CREW');

    $allPassed = !in_array(false, array_values($results['cases']), true);
    $results['all_passed'] = $allPassed;

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    $results['all_passed'] = false;
}

echo json_encode($results, JSON_PRETTY_PRINT);
