<?php
require_once __DIR__ . '/src/Config/Database.php';
require_once __DIR__ . '/src/dao/UserDAO.php';

use App\dao\UserDAO;

header('Content-Type: application/json');

$userDao = new UserDAO();

// 1. Create a test user
$testEmail = "test_" . time() . "@ecoasset.my";
$createdId = $userDao->createUser($testEmail, "SecurePass123!", "Test User", "TOURIST");

// 2. Fetch the created user
$fetchedUser = $createdId ? $userDao->getUserById($createdId) : null;

echo json_encode([
  "create_success" => (bool)$createdId,
  "created_user_id" => $createdId,
  "fetched_record" => $fetchedUser
], JSON_PRETTY_PRINT);
