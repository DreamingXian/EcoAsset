<?php
/**
 * EcoAsset PSR-4 & Modular Autoloader
 * Automatically maps App\* namespaces to src/* directories with case-insensitivity.
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Map namespace aliases to standard folder names
    $map = [
        'Config\\'      => 'config/',
        'Models\\'      => 'model/',
        'Model\\'       => 'model/',
        'DAO\\'         => 'dao/',
        'Dao\\'         => 'dao/',
        'Controllers\\' => 'controller/',
        'Controller\\'  => 'controller/',
        'Services\\'    => 'service/',
        'Service\\'     => 'service/',
        'Utils\\'       => 'utils/',
        'Util\\'        => 'utils/'
    ];

    $matched = false;
    $filePath = '';

    foreach ($map as $nsPrefix => $dirName) {
        if (str_starts_with($relativeClass, $nsPrefix)) {
            $subClass = substr($relativeClass, strlen($nsPrefix));
            $filePath = $baseDir . $dirName . str_replace('\\', '/', $subClass) . '.php';
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        $filePath = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    }

    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

// Automatically load .env if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

