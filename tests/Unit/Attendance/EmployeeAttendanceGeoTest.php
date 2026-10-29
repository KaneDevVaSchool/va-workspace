<?php

namespace Tests\Unit\Attendance;

use Modules\Attendance\App\Services\EmployeeAttendanceGeo;
use PHPUnit\Framework\TestCase;

class EmployeeAttendanceGeoTest extends TestCase
{
    public function test_within_tolerance_when_inside_radius(): void
    {
        $context = [
            'gps_enforced' => true,
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'gps_tolerance_meters' => 120,
        ];

        $this->assertTrue(EmployeeAttendanceGeo::isWithinTolerance($context, 10.762700, 106.660200));
    }

    public function test_outside_tolerance_when_far(): void
    {
        $context = [
            'gps_enforced' => true,
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'gps_tolerance_meters' => 50,
        ];

        $this->assertFalse(EmployeeAttendanceGeo::isWithinTolerance($context, 10.770000, 106.670000));
    }
}
