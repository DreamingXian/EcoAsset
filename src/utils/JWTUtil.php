<?php

namespace App\Utils;

use Exception;

class JWTUtil
{
    private static string $secretKey = 'ecoasset_super_secret_jwt_key_2026';
    private static string $algo = 'sha256';

    public static function setSecret(string $secret): void
    {
        self::$secretKey = $secret;
    }

    private static function getSecret(): string
    {
        return getenv('JWT_SECRET') ?: self::$secretKey;
    }

    /**
     * Generate a signed JWT token with standard claims.
     */
    public static function generateToken(array $payloadData, int $expirySeconds = 604800): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $issuedAt = time();
        $payload = array_merge([
            'iat' => $issuedAt,
            'exp' => $issuedAt + $expirySeconds
        ], $payloadData);

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(self::$algo, $base64Header . '.' . $base64Payload, self::getSecret(), true);
        $base64Signature = self::base64UrlEncode($signature);

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    /**
     * Validate and decode a JWT token. Returns payload array or null if invalid/expired.
     */
    public static function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $expectedSignature = hash_hmac(self::$algo, $base64Header . '.' . $base64Payload, self::getSecret(), true);
        $expectedBase64Signature = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedBase64Signature, $base64Signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null; // Expired or malformed
        }

        return $payload;
    }

    /**
     * Extract Bearer token from HTTP Authorization header.
     */
    public static function getBearerToken(): ?string
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Authenticate request and check for required role.
     */
    public static function requireAuth(?array $allowedRoles = null): ?array
    {
        $token = self::getBearerToken();
        if (!$token) {
            ResponseUtil::error("Unauthorized: Missing Bearer token", 401);
            exit;
        }

        $decoded = self::validateToken($token);
        if (!$decoded) {
            ResponseUtil::error("Unauthorized: Invalid or expired token", 401);
            exit;
        }

        if ($allowedRoles !== null && !in_array($decoded['role'] ?? '', $allowedRoles, true)) {
            ResponseUtil::error("Forbidden: Insufficient permissions for role " . ($decoded['role'] ?? 'unknown'), 403);
            exit;
        }

        return $decoded;
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
