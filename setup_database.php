<?php
require_once __DIR__ . '/src/config/Database.php';

use App\Config\Database;

header('Content-Type: application/json');

try {
  $pdo = Database::getConnection();
  $sql = file_get_contents(__DIR__ . '/database.sql');

  if (!$sql) {
    throw new Exception("database.sql file not found or empty.");
  }

  $pdo->exec($sql);

  // Query all tables created
  $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;");
  $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

  echo json_encode([
    "status" => "SUCCESS",
    "message" => "Database schema initialized successfully!",
    "tables" => $tables
  ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "status" => "FAILED",
    "error" => $e->getMessage()
  ], JSON_PRETTY_PRINT);
}
