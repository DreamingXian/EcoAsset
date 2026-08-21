<?php

namespace App\Utils;

use Exception;

class StorageUtil
{
    private static string $bucketName = 'ecoasset-media';
    private static string $supabaseUrl = 'https://wwypceildoytilmogpxm.supabase.co';

    const FOLDER_REPORTS = 'reports';
    const FOLDER_COMPLETIONS = 'completions';
    const FOLDER_FLAGS = 'flags';

    /**
     * Get the public URL for a file stored in Supabase Storage.
     */
    public static function getPublicUrl(string $folder, string $filename): string
    {
        $base = rtrim(getenv('SUPABASE_URL') ?: self::$supabaseUrl, '/');
        $bucket = getenv('SUPABASE_BUCKET') ?: self::$bucketName;
        return "{$base}/storage/v1/object/public/{$bucket}/{$folder}/{$filename}";
    }

    /**
     * Helper to generate a unique filename with timestamp and extension.
     */
    public static function generateFilename(string $prefix, string $extension = 'jpg'): string
    {
        return $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ltrim($extension, '.');
    }

    /**
     * Upload base64 encoded image to Supabase Storage via REST API.
     */
    public static function uploadBase64Image(string $base64Data, string $folder, string $filename): string
    {
        // 1. Strip base64 header if present (e.g. "data:image/jpeg;base64,...")
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $mimeType = 'image/' . strtolower($type[1]);
        } else {
            $mimeType = 'image/jpeg';
        }

        $binaryData = base64_decode($base64Data);
        if ($binaryData === false) {
            throw new Exception("Invalid base64 image data.");
        }

        $base = rtrim(getenv('SUPABASE_URL') ?: self::$supabaseUrl, '/');
        $bucket = getenv('SUPABASE_BUCKET') ?: self::$bucketName;
        $apiKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: (getenv('SUPABASE_ANON_KEY') ?: 'sb_secret_BezfadlXvJSTlICUe31hbQ_mrlzl05v');

        $uploadUrl = "{$base}/storage/v1/object/{$bucket}/{$folder}/{$filename}";

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $binaryData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'apikey: ' . $apiKey,
                'Content-Type: ' . $mimeType,
                'x-upsert: true'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return self::getPublicUrl($folder, $filename);
        }

        // Return public URL if upload was accepted or fallback
        error_log("Supabase storage upload status (HTTP {$httpCode}): {$response} / {$curlError}");
        return self::getPublicUrl($folder, $filename);
    }
}
