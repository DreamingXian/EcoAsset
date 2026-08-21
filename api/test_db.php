<?php
require_once __DIR__ . '/src/Config/Database.php';

use App\Config\Database;

try {
  $db = Database::getConnection();
  $stmt = $db->query("SELECT version();");
  $version = $stmt->fetchColumn();

  echo json_encode([
    "status" => "success",
    "message" => "Connected successfully to Supabase PostgreSQL!",
    "database_version" => $version
  ]);
} catch (Exception $e) {
  echo json_encode([
    "status" => "error",
    "message" => $e->getMessage()
  ]);
}
