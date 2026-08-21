<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Utils\JWTUtil;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$results = ['test_name' => 'Forgot Password API & Logic Verification', 'cases' => []];

try {
    // 1. Test Active User
    $activeUser = $userDAO->getUserByEmail('admin@ecoasset.my');
    $results['cases']['1_active_user_found'] = ($activeUser !== null && $activeUser['is_active'] == true);

    // Generate reset token test
    $resetToken = JWTUtil::generateToken([
        'user_id' => $activeUser['user_id'],
        'email' => $activeUser['email'],
        'type' => 'password_reset'
    ], 3600);
    $decoded = JWTUtil::validateToken($resetToken);
    $results['cases']['2_reset_token_issued_valid'] = ($decoded && $decoded['type'] === 'password_reset' && $decoded['email'] === 'admin@ecoasset.my');

    // 2. Test Deactivated User Check
    $deactUser = $userDAO->getUserByEmail('byebye@email.com');
    $results['cases']['3_deactivated_user_identified'] = ($deactUser !== null && ($deactUser['is_active'] === false || $deactUser['is_active'] === 0));

    // 3. Test Non-Existent User Check
    $notFoundUser = $userDAO->getUserByEmail('noaccfound@email.com');
    $results['cases']['4_not_found_user_null'] = ($notFoundUser === null);

    $allPassed = !in_array(false, array_values($results['cases']), true);
    $results['all_passed'] = $allPassed;

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    $results['all_passed'] = false;
}

echo json_encode($results, JSON_PRETTY_PRINT);
