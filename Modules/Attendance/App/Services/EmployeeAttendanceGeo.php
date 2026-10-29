<?php

namespace Modules\Attendance\App\Services;

/**
 * Khoảng cách WGS84 (haversine) — khớp logic vùng chấm trên HRM.
 */
final class EmployeeAttendanceGeo
{
    public static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6_371_000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /**
     * @param  array<string, mixed>  $context  payload từ HRM check-in-context
     */
    public static function isWithinTolerance(array $context, float $lat, float $lon, ?float $accuracyMeters = null): bool
    {
        if (! ($context['gps_enforced'] ?? false)) {
            return true;
        }

        $centerLat = $context['latitude'] ?? null;
        $centerLon = $context['longitude'] ?? null;
        $tolerance = $context['gps_tolerance_meters'] ?? null;
        if (! is_numeric($centerLat) || ! is_numeric($centerLon) || ! is_numeric($tolerance)) {
            return true;
        }

        $distance = self::distanceMeters((float) $lat, (float) $lon, (float) $centerLat, (float) $centerLon);
        $buffer = $accuracyMeters !== null && $accuracyMeters > 0
            ? min(50.0, (float) $accuracyMeters)
            : 0.0;

        return $distance <= ((float) $tolerance + $buffer);
    }
}
