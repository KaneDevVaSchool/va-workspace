<?php

namespace Tests\Unit\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Tests\TestCase;

class HrmEmployeeSearchPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.hrm.database' => null]);
        Cache::forget('hrm:directory:snapshot:v1');
    }

    /** @param list<array<string, mixed>> $employees */
    private function mirrorWith(array $employees): HrmEmployeeDatabaseMirrorService
    {
        $this->mock(HrmEmployeeDirectory::class, function ($mock) use ($employees): void {
            $mock->shouldReceive('load')->andReturn(['employees' => $employees, 'org_units' => []]);
        });

        return app(HrmEmployeeDatabaseMirrorService::class);
    }

    /** @return array<string, mixed> */
    private function employee(int $n, string $name, array $extra = []): array
    {
        return array_merge([
            'uuid' => "emp-{$n}",
            'full_name' => $name,
            'company_email' => "nv{$n}@vaschools.edu.vn",
            'code' => sprintf('NV%03d', $n),
            'status' => 'active',
        ], $extra);
    }

    public function test_search_ignores_accents_case_and_word_order(): void
    {
        $mirror = $this->mirrorWith([
            $this->employee(1, 'Nguyễn Đức Khoa'),
            $this->employee(2, 'Trần Thị Lan'),
        ]);

        foreach (['nguyen duc khoa', 'KHOA nguyễn', 'đức'] as $needle) {
            $page = $mirror->mappedSearchPage($needle, 20);
            $this->assertSame(['Nguyễn Đức Khoa'], array_column($page['users'], 'name'), $needle);
        }
    }

    public function test_name_matches_come_before_matches_by_email_or_department(): void
    {
        $mirror = $this->mirrorWith([
            $this->employee(1, 'An Bình', ['department_name' => 'Phòng Lan']),
            $this->employee(2, 'Lan Chi'),
        ]);

        $page = $mirror->mappedSearchPage('lan', 20);

        $this->assertSame(['Lan Chi', 'An Bình'], array_column($page['users'], 'name'));
    }

    public function test_pages_cover_every_employee_exactly_once(): void
    {
        $employees = [];
        foreach (range(1, 45) as $n) {
            $employees[] = $this->employee($n, sprintf('Giáo viên %02d', $n));
        }
        $mirror = $this->mirrorWith($employees);

        $seen = [];
        $offset = 0;
        do {
            $page = $mirror->mappedSearchPage('', 20, $offset);
            $seen = array_merge($seen, array_column($page['users'], 'id'));
            $offset += 20;
        } while ($page['has_more']);

        $this->assertCount(45, $seen);
        $this->assertCount(45, array_unique($seen));
        $this->assertFalse($page['has_more']);
    }
}
