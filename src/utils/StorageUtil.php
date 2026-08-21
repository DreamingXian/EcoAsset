<?php

namespace App\Utils;

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
}
