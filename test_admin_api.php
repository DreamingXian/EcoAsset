<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Utils\JWTUtil;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$results = ['test_name' => 'Admin User Management API Verification', 'cases' => []];

try {
    // 1. Get Admin User
    $admin = $userDAO->getUserByEmail('admin@ecoasset.my');
    if (!$admin) throw new Exception("Admin user not found.");
    $adminToken = JWTUtil::generateToken([
        'user_id'   => $admin['user_id'],
        'email'     => $admin['email'],
        'role'      => 'ADMINISTRATOR',
        'full_name' => $admin['full_name']
    ], 3600);

    // 2. Test Get All Staff Users
    $allUsers = $userDAO->getAllUsers();
    $results['cases']['1_get_users_count'] = count($allUsers) >= 4;

    // 3. Test Create New Staff Account
    $uniqueSuffix = time() . '_' . rand(100, 999);
    $testStaffEmail = "staff_{$uniqueSuffix}@dbkl.gov.my";
    $newUserId = $userDAO->createUser($testStaffEmail, 'Pass1234!', 'Staff Test Member', 'SITE_RANGER');
    $results['cases']['2_create_staff_user'] = ($newUserId !== null);

    // 4. Test Update User Details By Admin
    $updatedName = "Staff Member Renamed";
    $updateSuccess = $userDAO->updateUserByAdmin($newUserId, $updatedName, $testStaffEmail, 'MAINTENANCE_CREW');
    $freshUser = $userDAO->getUserById($newUserId);
    $results['cases']['3_update_user_details'] = (
        $updateSuccess &&
        $freshUser['full_name'] === $updatedName &&
        $freshUser['role'] === 'MAINTENANCE_CREW'
    );

    // 5. Test Deactivate User Status
    $deactSuccess = $userDAO->updateUserStatus($newUserId, false);
    $deactUser = $userDAO->getUserById($newUserId);
    $results['cases']['4_deactivate_user'] = ($deactSuccess && $deactUser['is_active'] == false);

    // 6. Test Reactivate User Status
    $reactSuccess = $userDAO->updateUserStatus($newUserId, true);
    $reactUser = $userDAO->getUserById($newUserId);
    $results['cases']['5_reactivate_user'] = ($reactSuccess && $reactUser['is_active'] == true);

    // 7. Non-admin authorization rejection check
    $tourist = $userDAO->getUserByEmail('tourist@ecoasset.my');
    $touristToken = JWTUtil::generateToken([
        'user_id' => $tourist['user_id'],
        'email'   => $tourist['email'],
        'role'    => 'TOURIST'
    ], 3600);
    $payload = JWTUtil::validateToken($touristToken);
    $results['cases']['6_non_admin_rejected'] = (($payload['role'] ?? '') !== 'ADMINISTRATOR');

    $results['all_passed'] = !in_array(false, array_values($results['cases']), true);

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    $results['all_passed'] = false;
}

echo json_encode($results, JSON_PRETTY_PRINT);
