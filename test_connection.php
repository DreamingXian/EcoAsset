<?php
header('Content-Type: application/json');

// 1. Connection Parameters (Using Direct Connection String)
$host = 'db.wwypceildoytilmogpxm.supabase.co';
$port = '5432';
$dbname = 'postgres';
$user = 'postgres';
$password = 'Abc123VIP888ok'; // Replace with your real Supabase password

// 2. Data Source Name (DSN)
$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

try {
  // 3. Attempt Connection
  $pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5
  ]);

  // 4. Test Query: Check PostgreSQL version and current database
  $stmt = $pdo->query("SELECT current_database() AS db_name, version() AS pg_version;");
  $result = $stmt->fetch();

  // 5. Test Query: Count existing tables in public schema
  $tableStmt = $pdo->query("SELECT count(*) AS table_count FROM information_schema.tables WHERE table_schema = 'public';");
  $tableResult = $tableStmt->fetch();

  echo json_encode([
    "status" => "SUCCESS",
    "message" => "Successfully connected to Supabase PostgreSQL!",
    "database" => $result['db_name'],
    "tables_found" => (int)$tableResult['table_count'],
    "postgres_version" => $result['pg_version']
  ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    "status" => "FAILED",
    "error_message" => $e->getMessage(),
    "hint" => "Check that your password is correct and 'extension=pdo_pgsql' is enabled in php.ini."
  ], JSON_PRETTY_PRINT);
}
