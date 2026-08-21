<?php

namespace App\Utils;

class GeoUtil
{
    const EARTH_RADIUS_KM = 6371.0;
    const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Calculate Haversine distance between two coordinates in kilometers.
     */
    public static function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * asin(min(1.0, sqrt($a)));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Calculate Haversine distance between two coordinates in meters.
     */
    public static function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return self::haversineDistanceKm($lat1, $lon1, $lat2, $lon2) * 1000.0;
    }

    /**
     * Verify if EXIF GPS coordinates match the dropped pin within a max tolerance radius (default 50m).
     */
    public static function isWithinTolerance(float $lat1, float $lon1, float $lat2, float $lon2, float $maxMeters = 50.0): bool
    {
        $distance = self::haversineDistanceMeters($lat1, $lon1, $lat2, $lon2);
        return $distance <= $maxMeters;
    }

    /**
     * Parse EXIF GPS coordinates from PHP exif_read_data array.
     */
    public static function parseExifCoordinates(array $exif): ?array
    {
        if (!isset($exif['GPSLatitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitude'], $exif['GPSLongitudeRef'])) {
            return null;
        }

        $lat = self::getGpsCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
        $lng = self::getGpsCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }

    private static function getGpsCoordinate(array $coordinate, string $hemi): ?float
    {
        for ($i = 0; $i < 3; $i++) {
            $part = explode('/', $coordinate[$i]);
            if (count($part) === 1) {
                $coordinate[$i] = (float)$part[0];
            } elseif (count($part) === 2) {
                if ((float)$part[1] == 0) return null;
                $coordinate[$i] = (float)$part[0] / (float)$part[1];
            } else {
                return null;
            }
        }

        $degrees = $coordinate[0] + ($coordinate[1] / 60.0) + ($coordinate[2] / 3600.0);
        return ($hemi == 'S' || $hemi == 'W') ? $degrees * -1 : $degrees;
    }
}
