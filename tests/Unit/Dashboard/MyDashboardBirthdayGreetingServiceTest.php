<?php

namespace Tests\Unit\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Modules\Dashboard\App\Services\MyDashboardBirthdayGreetingService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Tests\TestCase;

class MyDashboardBirthdayGreetingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_detects_birthday_from_hrm_birthdate(): void
    {
        Carbon::setTestNow('2026-03-15 09:00:00');

        $user = User::factory()->create([
            'name' => 'Nguyễn Văn An',
            'hrm_employee_uuid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $directory = Mockery::mock(HrmEmployeeDirectory::class);
        $directory->shouldReceive('isConfigured')->andReturnTrue();
        $directory->shouldReceive('findEmployeeForUser')
            ->once()
            ->andReturn(['birthdate' => '1990-03-15']);

        $service = new MyDashboardBirthdayGreetingService($directory);

        $result = $service->status($user);

        $this->assertTrue($result['is_birthday_today']);
        $this->assertSame('An', $result['first_name']);
    }

    public function test_not_birthday_when_hrm_missing_birthdate(): void
    {
        Carbon::setTestNow('2026-03-15 09:00:00');

        $user = User::factory()->create(['name' => 'Test User']);

        $directory = Mockery::mock(HrmEmployeeDirectory::class);
        $directory->shouldReceive('isConfigured')->andReturnTrue();
        $directory->shouldReceive('findEmployeeForUser')
            ->once()
            ->andReturn(['birthdate' => null]);

        $service = new MyDashboardBirthdayGreetingService($directory);

        $this->assertFalse($service->status($user)['is_birthday_today']);
    }
}
