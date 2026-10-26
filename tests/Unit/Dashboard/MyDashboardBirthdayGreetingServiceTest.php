<?php

namespace Tests\Unit\Dashboard;

use Illuminate\Support\Carbon;
use Mockery;
use Modules\Dashboard\App\Services\MyDashboardBirthdayGreetingService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Tests\TestCase;

class MyDashboardBirthdayGreetingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_returns_everyone_whose_birthday_is_today(): void
    {
        Carbon::setTestNow('2026-03-15 09:00:00');
        config(['database.connections.hrm.database' => 'hrm_test']);

        $directory = Mockery::mock(HrmEmployeeDirectory::class);
        $directory->shouldReceive('upcomingBirthdays')
            ->once()
            ->with(1)
            ->andReturn([
                [
                    'hrm_employee_uuid' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Nguyễn Văn An',
                    'department_name' => 'Tiểu học',
                    'next_birthday' => '2026-03-15',
                ],
                [
                    'hrm_employee_uuid' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'Trần Thị Bình',
                    'department_name' => null,
                    'next_birthday' => '2026-03-16',
                ],
            ]);

        $service = new MyDashboardBirthdayGreetingService($directory);
        $people = $service->status()['people'];

        $this->assertCount(1, $people);
        $this->assertSame('Nguyễn Văn An', $people[0]['name']);
        $this->assertSame('Tiểu học', $people[0]['department_name']);
    }

    public function test_returns_empty_when_hrm_is_not_configured(): void
    {
        config(['database.connections.hrm.database' => null]);

        $directory = Mockery::mock(HrmEmployeeDirectory::class);
        $directory->shouldNotReceive('upcomingBirthdays');

        $service = new MyDashboardBirthdayGreetingService($directory);

        $this->assertSame(['people' => []], $service->status());
    }
}
