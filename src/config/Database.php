<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
  private static ?PDO $instance = null;

  public static function getConnection(): PDO
  {
    if (self::$instance === null) {
      $host = getenv('DB_HOST') ?: 'aws-0-ap-southeast-1.pooler.supabase.com';
      $port = getenv('DB_PORT') ?: '6543';
      $db   = getenv('DB_NAME') ?: 'postgres';
      $user = getenv('DB_USER') ?: 'postgres.wwypceildoytilmogpxm';
      $pass = getenv('DB_PASS') ?: 'Abc123VIP888ok';

      // sslmode=require is mandatory for connecting to Supabase PostgreSQL
      $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

      try {
        self::$instance = new PDO($dsn, $user, $pass, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => true,
          PDO::ATTR_PERSISTENT => true,
          PDO::ATTR_TIMEOUT => 5
        ]);
      } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
          "status" => "error",
          "message" => "Database connection failed: " . $e->getMessage()
        ]);
        exit;
      }
    }
    return self::$instance;
  }
}
