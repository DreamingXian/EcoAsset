<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$results = ['test_name' => 'Signup & Registration Verification', 'cases' => []];

$suffix = time() . '_' . rand(100, 999);
$newEmail = "amanda_{$suffix}@ecoasset.my";
$password = "Secret123!";
$fullName = "Amanda Wong";

try {
    // 1. Create a brand new tourist
    $newUserId = $userDAO->createUser($newEmail, $password, $fullName, 'TOURIST', ['reputation_score' => 100]);
    $createdUser = $userDAO->getUserById($newUserId);
    $results['cases']['1_new_tourist_created'] = ($createdUser && $createdUser['role'] === 'TOURIST' && $createdUser['reputation_score'] == 100);

    // 2. Check duplicate email detection
    $duplicateUser = $userDAO->getUserByEmail($newEmail);
    $results['cases']['2_duplicate_email_detected'] = ($duplicateUser !== null && $duplicateUser['is_active'] == true);

    // 3. Check deactivated user detection on signup
    $deactivatedUser = $userDAO->getUserByEmail('byebye@email.com');
    $results['cases']['3_deactivated_user_detected'] = ($deactivatedUser !== null && ($deactivatedUser['is_active'] === false || $deactivatedUser['is_active'] === 0));

    $allPassed = !in_array(false, array_values($results['cases']), true);
    $results['all_passed'] = $allPassed;

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    $results['all_passed'] = false;
}

echo json_encode($results, JSON_PRETTY_PRINT);
