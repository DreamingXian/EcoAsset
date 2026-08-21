<?php

namespace App\Utils;

class ResponseUtil
{
    /**
     * Send standard JSON response.
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send standard success response.
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Send standard error response.
     */
    public static function error(string $message, int $statusCode = 400, $details = null): void
    {
        $payload = [
            'status' => 'error',
            'message' => $message
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        self::json($payload, $statusCode);
    }
}
