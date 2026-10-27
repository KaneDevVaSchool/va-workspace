<?php

namespace Modules\DonTu\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\DonTu\App\Models\DonTuWorkflow;
use Modules\DonTu\App\Models\DonTuWorkflowForm;
use Modules\DonTu\App\Models\DonTuWorkflowStep;

/**
 * Quy trình nghỉ việc HN.QT.05: BMTT01 + BM.01 (khởi tạo) + BM.02 (bàn giao).
 */
class DonTuWorkflowSeeder extends Seeder
{
    private const WORKFLOW_CODE = 'QT_NGHI_VIEC_HN_QT_05';

    private const FORM_KEY_RESIGNATION = 'don_xin_nghi_viec';

    private const FORM_KEY_HANDOVER = 'bien_ban_ban_giao';

    public function run(): void
    {
        if (DonTuWorkflow::query()->where('code', self::WORKFLOW_CODE)->exists()) {
            return;
        }

        $statusSetId = DB::table('don_tu_status_sets')->where('code', 'BMTT01')->value('id');
        $bm01Id = DB::table('don_tu_form_templates')->where('code', 'BM01_HN_QT_05')->value('id');
        $bm02Id = DB::table('don_tu_form_templates')->where('code', 'BM02_HN_QT_05')->value('id');

        if (! $statusSetId || ! $bm01Id || ! $bm02Id) {
            $this->command?->warn(
                'DonTuWorkflowSeeder: thiếu BMTT01 hoặc mẫu BM01/BM02 — chạy DonTuStatusSetSeeder và DonTuFormTemplateSeeder trước.',
            );

            return;
        }

        DB::transaction(function () use ($statusSetId, $bm01Id, $bm02Id) {
            $workflow = DonTuWorkflow::query()->create([
                'code' => self::WORKFLOW_CODE,
                'name' => 'Quy trình nghỉ việc (HN.QT.05)',
                'description' => 'Đơn xin nghỉ việc BM.01, duyệt Trưởng đơn vị · HCNS · TGĐ, biên bản bàn giao BM.02, HCNS đóng thủ tục.',
                'status_set_id' => $statusSetId,
                'department_id' => null,
                'is_financial' => false,
                'is_active' => true,
                'initiator_policy' => 'staff',
                'closure_actor_policy' => 'role',
                'closure_role' => 'hcns',
                'created_by' => null,
            ]);

            DonTuWorkflowForm::query()->create([
                'workflow_id' => $workflow->id,
                'form_template_id' => $bm01Id,
                'form_key' => self::FORM_KEY_RESIGNATION,
                'sequence' => 1,
                'is_start' => true,
            ]);

            DonTuWorkflowForm::query()->create([
                'workflow_id' => $workflow->id,
                'form_template_id' => $bm02Id,
                'form_key' => self::FORM_KEY_HANDOVER,
                'sequence' => 2,
                'is_start' => false,
            ]);

            $steps = [
                [
                    'sort_order' => 1,
                    'name' => 'Trưởng đơn vị phê duyệt',
                    'status_group_code' => 'PENDING_UNIT_HEAD',
                    'step_type' => 'approve',
                    'target_form_keys' => [],
                    'assignee_policy' => 'role',
                    'assignee_role' => 'truong_don_vi',
                    'sla_hours' => 48,
                    'allow_reject' => true,
                    'allow_request_changes' => true,
                    'blocks_cancel' => false,
                ],
                [
                    'sort_order' => 2,
                    'name' => 'Phòng HCNS phê duyệt',
                    'status_group_code' => 'PENDING_HCNS',
                    'step_type' => 'approve',
                    'target_form_keys' => [],
                    'assignee_policy' => 'role',
                    'assignee_role' => 'hcns',
                    'sla_hours' => 48,
                    'allow_reject' => true,
                    'allow_request_changes' => true,
                    'blocks_cancel' => false,
                ],
                [
                    'sort_order' => 3,
                    'name' => 'Tổng giám đốc phê duyệt',
                    'status_group_code' => 'PENDING_CEO',
                    'step_type' => 'approve',
                    'target_form_keys' => [],
                    'assignee_policy' => 'role',
                    'assignee_role' => 'tong_giam_doc',
                    'sla_hours' => 72,
                    'allow_reject' => true,
                    'allow_request_changes' => false,
                    'blocks_cancel' => true,
                ],
                [
                    'sort_order' => 4,
                    'name' => 'Hoàn thiện biên bản bàn giao BM.02',
                    'status_group_code' => 'PENDING_HANDOVER',
                    'step_type' => 'form_completion',
                    'target_form_keys' => [self::FORM_KEY_HANDOVER],
                    'assignee_policy' => 'requester',
                    'assignee_role' => null,
                    'sla_hours' => 120,
                    'allow_reject' => false,
                    'allow_request_changes' => false,
                    'blocks_cancel' => false,
                ],
                [
                    'sort_order' => 5,
                    'name' => 'HCNS xác nhận hoàn tất thủ tục',
                    'status_group_code' => 'PENDING_HCNS_PROCEDURE',
                    'step_type' => 'closure',
                    'target_form_keys' => [],
                    'assignee_policy' => 'role',
                    'assignee_role' => 'hcns',
                    'sla_hours' => 168,
                    'allow_reject' => false,
                    'allow_request_changes' => false,
                    'blocks_cancel' => false,
                ],
            ];

            foreach ($steps as $row) {
                DonTuWorkflowStep::query()->create([
                    'workflow_id' => $workflow->id,
                    ...$row,
                ]);
            }
        });
    }
}
