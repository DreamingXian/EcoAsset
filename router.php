<?php
/**
 * EcoAsset Local Development Server Router
 * Handles /api requests, static files from public/, and module routing
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. API Route Delegation (/api/index.php/... or /api/v1/...)
if (strpos($uri, '/api') === 0) {
    require __DIR__ . '/api/index.php';
    return true;
}

// 2. Root Redirect to Login
if ($uri === '/' || $uri === '') {
    header("Location: /login.php");
    exit;
}

// 3. Serve from /public folder if requested directly without /public prefix
$publicFilePath = __DIR__ . '/public' . $uri;
if (file_exists($publicFilePath) && !is_dir($publicFilePath)) {
    $ext = pathinfo($publicFilePath, PATHINFO_EXTENSION);
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'html' => 'text/html',
        'php'  => 'text/html'
    ];
    if (isset($mimes[$ext])) {
        header("Content-Type: " . $mimes[$ext]);
    }
    if ($ext === 'php') {
        require $publicFilePath;
        return true;
    }
    readfile($publicFilePath);
    return true;
}

// 4. Default static file fallback
return false;
