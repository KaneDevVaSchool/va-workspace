<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chấm công nhân viên — Workspace gọi API giờ chấm của HRM thay mặt người đang đăng nhập.
 */
class EmployeeAttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-22 08:15:42'));

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_loads_the_logged_in_employee_punch(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/punches*' => Http::response([
                'data' => [[
                    'work_date' => '2026-10-22',
                    'check_in_time' => '08:02',
                    'check_out_time' => null,
                    'check_in_method' => 'Chấm bằng nút',
                    'check_out_method' => null,
                ]],
            ]),
        ]);

        $this->actingAs($this->employee())
            ->getJson('/api/attendance/punches/today?employee_uuid=someone-else')
            ->assertOk()
            ->assertJsonPath('data.check_in_time', '08:02')
            ->assertJsonPath('data.check_in_method', 'Chấm bằng nút')
            ->assertJsonPath('data.check_out_time', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/attendance/punches')
            && $request['employee_uuid'] === 'emp-1'
            && $request['from'] === '2026-10-22'
            && $request['to'] === '2026-10-22');
    }

    public function test_today_is_empty_when_hrm_has_no_punch(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/punches*' => Http::response(['data' => []]),
        ]);

        $this->actingAs($this->employee())
            ->getJson('/api/attendance/punches/today')
            ->assertOk()
            ->assertJsonPath('data.check_in_time', null)
            ->assertJsonPath('data.check_out_time', null);
    }

    public function test_check_out_keeps_the_existing_check_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 17:30:10'));

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'data' => [[
                        'check_in_time' => '08:02:00',
                        'check_out_time' => null,
                        'check_in_method' => 'Chấm bằng nút',
                        'check_out_method' => null,
                    ]],
                ]);
            }

            return Http::response([
                'data' => [
                    'check_in_time' => '08:02',
                    'check_out_time' => '17:30',
                    'check_in_method' => 'Chấm bằng nút',
                    'check_out_method' => 'GPS',
                ],
            ]);
        });

        $this->actingAs($this->employee())
            ->postJson('/api/attendance/punches', [
                'direction' => 'out',
                'method' => 'GPS',
                'employee_uuid' => 'someone-else',
                'work_date' => '2020-01-01',
                'check_out_time' => '09:00',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Đã gửi giờ chấm công về HRM.')
            ->assertJsonPath('data.check_in_time', '08:02')
            ->assertJsonPath('data.check_out_time', '17:30')
            ->assertJsonPath('data.check_out_method', 'GPS');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/api/v1/attendance/punches')
            && $request['employee_uuid'] === 'emp-1'
            && $request['work_date'] === '2026-10-22'
            && $request['check_in_time'] === '08:02'
            && $request['check_in_method'] === 'Chấm bằng nút'
            && $request['check_out_time'] === '17:30'
            && $request['check_out_method'] === 'GPS');
    }

    public function test_check_in_sends_server_time_and_method_label(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/punches*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['data' => []]);
                }

                return Http::response([
                    'data' => [
                        'check_in_time' => '08:15',
                        'check_out_time' => null,
                        'check_in_method' => 'Chấm bằng nút',
                        'check_out_method' => null,
                    ],
                ]);
            },
        ]);

        $this->actingAs($this->employee())
            ->postJson('/api/attendance/punches', [
                'direction' => 'in',
                'method' => 'Chấm bằng nút',
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => [
                'check_in_time',
                'check_out_time',
                'check_in_method',
                'check_out_method',
            ]]);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            $payload = $request->data();

            return ($payload['check_in_time'] ?? null) === '08:15'
                && ($payload['check_in_method'] ?? null) === 'Chấm bằng nút'
                && ! array_key_exists('check_out_time', $payload)
                && ! array_key_exists('check_out_method', $payload);
        });
    }

    public function test_hrm_down_returns_503(): void
    {
        Http::fake(function () {
            throw new ConnectionException('HRM down');
        });

        $this->actingAs($this->employee())
            ->postJson('/api/attendance/punches', [
                'direction' => 'in',
                'method' => 'GPS',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Không gửi được giờ chấm công về HRM. Thử lại sau.');
    }

    public function test_hrm_validation_error_is_returned_as_422(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/punches*' => Http::response([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Dữ liệu không hợp lệ.',
                    'details' => ['errors' => ['work_date' => ['Ngày chấm công vượt quá 120 ngày.']]],
                ],
            ], 422),
        ]);

        $this->actingAs($this->employee())
            ->getJson('/api/attendance/punches/today')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ngày chấm công vượt quá 120 ngày.');
    }

    public function test_requires_hrm_employee_link(): void
    {
        Http::fake();
        $user = User::factory()->create(['hrm_employee_uuid' => null, 'status' => 'active']);

        $this->actingAs($user)
            ->postJson('/api/attendance/punches', [
                'direction' => 'in',
                'method' => 'Chấm bằng nút',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tài khoản chưa liên kết nhân sự HRM.');

        Http::assertNothingSent();
    }

    public function test_direction_must_be_in_or_out(): void
    {
        Http::fake();

        $this->actingAs($this->employee())
            ->postJson('/api/attendance/punches', [
                'direction' => 'sideways',
                'method' => 'GPS',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');

        Http::assertNothingSent();
    }

    public function test_sync_pushes_local_times_to_hrm_without_changing_them(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 17:30:10'));

        Http::fake([
            'https://hrm.test/api/v1/attendance/punches*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['data' => []]);
                }

                return Http::response([
                    'data' => [
                        'check_in_time' => '08:02',
                        'check_out_time' => '17:05',
                        'check_in_method' => 'Chấm bằng nút',
                        'check_out_method' => 'GPS',
                    ],
                ]);
            },
        ]);

        $this->actingAs($this->employee())
            ->postJson('/api/attendance/punches/sync', [
                'check_in_time' => '08:02',
                'check_out_time' => '17:05',
                'check_in_method' => 'Chấm bằng nút',
                'check_out_method' => 'GPS',
            ])
            ->assertOk()
            ->assertJsonPath('data.check_in_time', '08:02')
            ->assertJsonPath('data.check_out_time', '17:05');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && ($request['check_in_time'] ?? null) === '08:02'
            && ($request['check_out_time'] ?? null) === '17:05'
            && ($request['check_in_method'] ?? null) === 'Chấm bằng nút'
            && ($request['check_out_method'] ?? null) === 'GPS');
    }

    private function employee(): User
    {
        return User::factory()->create([
            'hrm_employee_uuid' => 'emp-1',
            'status' => 'active',
        ]);
    }
}
