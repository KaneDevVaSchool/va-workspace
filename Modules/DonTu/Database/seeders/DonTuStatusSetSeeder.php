<?php

namespace Modules\DonTu\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DonTuStatusSetSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultMatrix();
        $this->seedLeaveSet();
        $this->seedMissionSet();
    }

    private function seedDefaultMatrix(): void
    {
        if (DB::table('don_tu_status_sets')->where('code', 'STATUS_SET_DEFAULT')->exists()) {
            return;
        }

        $now = now();
        $setId = DB::table('don_tu_status_sets')->insertGetId([
            'code' => 'STATUS_SET_DEFAULT',
            'name' => 'Trạng thái hồ sơ mặc định',
            'description' => 'Luồng đầy đủ cho hầu hết loại đơn nội bộ.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $statuses = [
            ['code' => 'DRAFT', 'label' => 'Nháp', 'group_kind' => 'main', 'terminal_kind' => 'draft', 'sort_order' => 1],
            ['code' => 'PENDING_HANDLING', 'label' => 'Chờ xử lý', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 2],
            ['code' => 'PENDING_APPROVAL', 'label' => 'Chờ duyệt', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 3],
            ['code' => 'PENDING_SUPPLEMENTAL_FORM', 'label' => 'Chờ bổ sung phiếu', 'group_kind' => 'conditional', 'terminal_kind' => null, 'sort_order' => 4],
            ['code' => 'PENDING_PAYMENT', 'label' => 'Chờ chi trả', 'group_kind' => 'conditional', 'terminal_kind' => null, 'sort_order' => 5],
            ['code' => 'PENDING_CLOSURE_CONFIRMATION', 'label' => 'Chờ xác nhận hoàn tất', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 6],
            ['code' => 'COMPLETED', 'label' => 'Hoàn thành', 'group_kind' => 'terminal', 'terminal_kind' => 'success', 'sort_order' => 7],
            ['code' => 'REJECTED', 'label' => 'Từ chối', 'group_kind' => 'terminal', 'terminal_kind' => 'failed', 'sort_order' => 8],
            ['code' => 'CANCELLED', 'label' => 'Đã hủy', 'group_kind' => 'terminal', 'terminal_kind' => 'failed', 'sort_order' => 9],
        ];

        $this->insertStatuses($setId, $statuses);

        $transitions = [
            ['from_code' => 'NONE', 'to_code' => 'DRAFT', 'action_key' => 'SAVE_DRAFT', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'NONE', 'to_code' => 'PENDING_HANDLING', 'action_key' => 'SUBMIT', 'actor_policy' => 'requester', 'guard_json' => json_encode(['form_complete' => true])],
            ['from_code' => 'DRAFT', 'to_code' => 'PENDING_HANDLING', 'action_key' => 'SUBMIT', 'actor_policy' => 'requester', 'guard_json' => json_encode(['form_complete' => true])],
            ['from_code' => 'PENDING_HANDLING', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'ACCEPT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['step_type' => 'receive'])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['has_next_approve' => true])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'PENDING_SUPPLEMENTAL_FORM', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['next_step' => 'form_completion'])],
            ['from_code' => 'PENDING_SUPPLEMENTAL_FORM', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'SUPPLEMENT', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'PENDING_PAYMENT', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['next_step' => 'payment'])],
            ['from_code' => 'PENDING_PAYMENT', 'to_code' => 'PENDING_CLOSURE_CONFIRMATION', 'action_key' => 'CONFIRM_PAYMENT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['unc_required' => true])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'PENDING_CLOSURE_CONFIRMATION', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['no_specialist_steps_left' => true])],
            ['from_code' => 'PENDING_CLOSURE_CONFIRMATION', 'to_code' => 'COMPLETED', 'action_key' => 'CLOSE', 'actor_policy' => 'closure_actor', 'guard_json' => null],
            ['from_code' => 'PENDING_HANDLING', 'to_code' => 'REJECTED', 'action_key' => 'REJECT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['reason_min' => 10])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'REJECTED', 'action_key' => 'REJECT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['reason_min' => 10])],
            ['from_code' => 'PENDING_PAYMENT', 'to_code' => 'REJECTED', 'action_key' => 'REJECT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['reason_min' => 10])],
            ['from_code' => 'PENDING_HANDLING', 'to_code' => 'CANCELLED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'CANCELLED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'PENDING_SUPPLEMENTAL_FORM', 'to_code' => 'CANCELLED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'REQUEST_CHANGES', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['revise_open' => true])],
        ];

        $this->insertTransitions($setId, $transitions);
    }

    private function seedLeaveSet(): void
    {
        if (DB::table('don_tu_status_sets')->where('code', 'BMTT01')->exists()) {
            return;
        }

        $now = now();
        $setId = DB::table('don_tu_status_sets')->insertGetId([
            'code' => 'BMTT01',
            'name' => 'Đơn xin nghỉ phép',
            'description' => 'Luồng ngắn: nháp, gửi duyệt, hoàn tất hoặc từ chối.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $statuses = [
            ['code' => 'DRAFT', 'label' => 'Nháp', 'group_kind' => 'main', 'terminal_kind' => 'draft', 'sort_order' => 1],
            ['code' => 'PENDING_APPROVAL', 'label' => 'Chờ duyệt', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 2],
            ['code' => 'COMPLETED', 'label' => 'Đã duyệt', 'group_kind' => 'terminal', 'terminal_kind' => 'success', 'sort_order' => 3],
            ['code' => 'REJECTED', 'label' => 'Từ chối', 'group_kind' => 'terminal', 'terminal_kind' => 'failed', 'sort_order' => 4],
        ];

        $this->insertStatuses($setId, $statuses);

        $transitions = [
            ['from_code' => 'NONE', 'to_code' => 'DRAFT', 'action_key' => 'SAVE_DRAFT', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'DRAFT', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'SUBMIT', 'actor_policy' => 'requester', 'guard_json' => json_encode(['form_complete' => true])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'COMPLETED', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => null],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'REJECTED', 'action_key' => 'REJECT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['reason_min' => 10])],
            ['from_code' => 'DRAFT', 'to_code' => 'REJECTED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
        ];

        $this->insertTransitions($setId, $transitions);
    }

    private function seedMissionSet(): void
    {
        if (DB::table('don_tu_status_sets')->where('code', 'BMTT02')->exists()) {
            return;
        }

        $now = now();
        $setId = DB::table('don_tu_status_sets')->insertGetId([
            'code' => 'BMTT02',
            'name' => 'Đơn công tác',
            'description' => 'Có bước tiếp nhận trước khi trình duyệt.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $statuses = [
            ['code' => 'DRAFT', 'label' => 'Nháp', 'group_kind' => 'main', 'terminal_kind' => 'draft', 'sort_order' => 1],
            ['code' => 'PENDING_HANDLING', 'label' => 'Chờ tiếp nhận', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 2],
            ['code' => 'PENDING_APPROVAL', 'label' => 'Chờ duyệt', 'group_kind' => 'main', 'terminal_kind' => null, 'sort_order' => 3],
            ['code' => 'COMPLETED', 'label' => 'Hoàn tất', 'group_kind' => 'terminal', 'terminal_kind' => 'success', 'sort_order' => 4],
            ['code' => 'CANCELLED', 'label' => 'Đã hủy', 'group_kind' => 'terminal', 'terminal_kind' => 'failed', 'sort_order' => 5],
        ];

        $this->insertStatuses($setId, $statuses);

        $transitions = [
            ['from_code' => 'NONE', 'to_code' => 'DRAFT', 'action_key' => 'SAVE_DRAFT', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'DRAFT', 'to_code' => 'PENDING_HANDLING', 'action_key' => 'SUBMIT', 'actor_policy' => 'requester', 'guard_json' => json_encode(['form_complete' => true])],
            ['from_code' => 'PENDING_HANDLING', 'to_code' => 'PENDING_APPROVAL', 'action_key' => 'ACCEPT', 'actor_policy' => 'assignee', 'guard_json' => json_encode(['step_type' => 'receive'])],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'COMPLETED', 'action_key' => 'APPROVE', 'actor_policy' => 'assignee', 'guard_json' => null],
            ['from_code' => 'PENDING_HANDLING', 'to_code' => 'CANCELLED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
            ['from_code' => 'PENDING_APPROVAL', 'to_code' => 'CANCELLED', 'action_key' => 'CANCEL', 'actor_policy' => 'requester', 'guard_json' => null],
        ];

        $this->insertTransitions($setId, $transitions);
    }

    /**
     * @param  list<array{code: string, label: string, group_kind: string, terminal_kind: ?string, sort_order: int}>  $statuses
     */
    private function insertStatuses(int $setId, array $statuses): void
    {
        foreach ($statuses as $row) {
            DB::table('don_tu_statuses')->insert([
                'status_set_id' => $setId,
                ...$row,
            ]);
        }
    }

    /**
     * @param  list<array{from_code: string, to_code: string, action_key: string, actor_policy: string, guard_json: ?string}>  $transitions
     */
    private function insertTransitions(int $setId, array $transitions): void
    {
        foreach ($transitions as $row) {
            DB::table('don_tu_transitions')->insert([
                'status_set_id' => $setId,
                'from_code' => $row['from_code'],
                'to_code' => $row['to_code'],
                'action_key' => $row['action_key'],
                'actor_policy' => $row['actor_policy'],
                'guard_json' => $row['guard_json'],
            ]);
        }
    }
}
