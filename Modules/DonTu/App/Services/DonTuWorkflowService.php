<?php

namespace Modules\DonTu\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\DonTu\App\Models\DonTuFormTemplate;
use Modules\DonTu\App\Models\DonTuStatusSet;
use Modules\DonTu\App\Models\DonTuWorkflow;
use Modules\DonTu\App\Models\DonTuWorkflowForm;
use Modules\DonTu\App\Models\DonTuWorkflowStep;

class DonTuWorkflowService
{
    private const STEP_TYPES = [
        'receive', 'approve', 'form_completion', 'payment', 'closure', 'task',
    ];

    public function listForUser(User $user, ?int $departmentId): Collection
    {
        $deptId = $departmentId ?? (int) ($user->department_id ?? 0);

        return DonTuWorkflow::query()
            ->with(['statusSet:id,code,name', 'forms'])
            ->withCount('steps')
            ->where(function ($q) use ($deptId) {
                $q->whereNull('department_id');
                if ($deptId > 0) {
                    $q->orWhere('department_id', $deptId);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (DonTuWorkflow $w) => $this->presentSummary($w));
    }

    public function find(int $id): ?DonTuWorkflow
    {
        return DonTuWorkflow::query()
            ->with(['statusSet.statuses', 'forms.formTemplate', 'steps'])
            ->find($id);
    }

    public function presentSummary(DonTuWorkflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'code' => $workflow->code,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'status_set_id' => $workflow->status_set_id,
            'status_set_code' => $workflow->statusSet?->code,
            'status_set_name' => $workflow->statusSet?->name,
            'department_id' => $workflow->department_id,
            'is_financial' => $workflow->is_financial,
            'is_active' => $workflow->is_active,
            'initiator_policy' => $workflow->initiator_policy,
            'closure_actor_policy' => $workflow->closure_actor_policy,
            'closure_role' => $workflow->closure_role,
            'step_count' => $workflow->steps_count ?? $workflow->steps()->count(),
            'form_count' => $workflow->forms?->count() ?? 0,
            'updated_at' => $workflow->updated_at?->toIso8601String(),
        ];
    }

    public function presentDetail(DonTuWorkflow $workflow): array
    {
        return [
            ...$this->presentSummary($workflow),
            'forms' => $workflow->forms->map(fn (DonTuWorkflowForm $f) => [
                'id' => $f->id,
                'form_key' => $f->form_key,
                'sequence' => $f->sequence,
                'is_start' => $f->is_start,
                'form_template_id' => $f->form_template_id,
                'form_template_name' => $f->formTemplate?->name,
                'form_template_code' => $f->formTemplate?->code,
            ])->values()->all(),
            'steps' => $workflow->steps->map(fn (DonTuWorkflowStep $s) => [
                'id' => $s->id,
                'sort_order' => $s->sort_order,
                'name' => $s->name,
                'status_group_code' => $s->status_group_code,
                'step_type' => $s->step_type,
                'target_form_keys' => $s->target_form_keys ?? [],
                'assignee_policy' => $s->assignee_policy,
                'assignee_role' => $s->assignee_role,
                'assignee_user_id' => $s->assignee_user_id,
                'sla_hours' => $s->sla_hours,
                'sign_mode' => $s->sign_mode,
                'workspace_task' => $s->workspace_task,
                'allow_reject' => $s->allow_reject,
                'allow_request_changes' => $s->allow_request_changes,
                'blocks_cancel' => $s->blocks_cancel,
            ])->values()->all(),
            'status_codes' => $workflow->statusSet?->statuses
                ?->pluck('code')
                ->values()
                ->all() ?? [],
        ];
    }

    public function create(User $user, array $data): DonTuWorkflow
    {
        $setId = (int) ($data['status_set_id'] ?? 0);
        if (! DonTuStatusSet::query()->whereKey($setId)->exists()) {
            throw ValidationException::withMessages(['status_set_id' => 'Bộ trạng thái không hợp lệ.']);
        }

        $code = $this->normalizeCode($data['code'] ?? $data['name'] ?? 'QT');
        if (DonTuWorkflow::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã quy trình đã tồn tại.']);
        }

        $deptId = array_key_exists('department_id', $data) ? $data['department_id'] : $user->department_id;

        return DonTuWorkflow::query()->create([
            'code' => $code,
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'status_set_id' => $setId,
            'department_id' => $deptId,
            'is_financial' => (bool) ($data['is_financial'] ?? false),
            'is_active' => false,
            'initiator_policy' => $data['initiator_policy'] ?? 'staff',
            'closure_actor_policy' => $data['closure_actor_policy'] ?? 'requester',
            'closure_role' => $data['closure_role'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function updateMeta(DonTuWorkflow $workflow, array $data): DonTuWorkflow
    {
        if (isset($data['name'])) {
            $workflow->name = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $workflow->description = $data['description'];
        }
        if (isset($data['initiator_policy'])) {
            $workflow->initiator_policy = $data['initiator_policy'];
        }
        if (isset($data['closure_actor_policy'])) {
            $workflow->closure_actor_policy = $data['closure_actor_policy'];
        }
        if (array_key_exists('closure_role', $data)) {
            $workflow->closure_role = $data['closure_role'];
        }
        if (array_key_exists('is_financial', $data)) {
            $workflow->is_financial = (bool) $data['is_financial'];
        }
        if (array_key_exists('is_active', $data)) {
            $workflow->is_active = (bool) $data['is_active'];
        }

        $workflow->save();

        return $workflow->fresh(['statusSet', 'forms.formTemplate', 'steps']);
    }

    public function syncForms(DonTuWorkflow $workflow, array $forms): DonTuWorkflow
    {
        $normalized = [];
        foreach ($forms as $row) {
            $templateId = (int) ($row['form_template_id'] ?? 0);
            if (! DonTuFormTemplate::query()->whereKey($templateId)->exists()) {
                throw ValidationException::withMessages(['forms' => 'Mẫu biểu không tồn tại.']);
            }
            $key = $row['form_key'] ?? '';
            if ($key === '') {
                throw ValidationException::withMessages(['forms' => 'Thiếu form_key.']);
            }
            $normalized[] = [
                'form_template_id' => $templateId,
                'form_key' => $key,
                'sequence' => (int) ($row['sequence'] ?? 1),
                'is_start' => (bool) ($row['is_start'] ?? false),
            ];
        }

        DB::transaction(function () use ($workflow, $normalized) {
            DonTuWorkflowForm::query()->where('workflow_id', $workflow->id)->delete();
            foreach ($normalized as $row) {
                DonTuWorkflowForm::query()->create([
                    'workflow_id' => $workflow->id,
                    ...$row,
                ]);
            }
        });

        return $workflow->fresh(['forms.formTemplate', 'steps', 'statusSet']);
    }

    public function syncSteps(DonTuWorkflow $workflow, array $steps): DonTuWorkflow
    {
        $codes = $workflow->statusSet?->statuses?->pluck('code')->all() ?? [];
        $normalized = [];
        $order = 0;

        foreach ($steps as $row) {
            $order++;
            $stepType = $row['step_type'] ?? 'approve';
            if (! in_array($stepType, self::STEP_TYPES, true)) {
                throw ValidationException::withMessages(['steps' => "Loại bước không hỗ trợ: {$stepType}"]);
            }
            $group = $row['status_group_code'] ?? '';
            if ($group !== '' && $codes !== [] && ! in_array($group, $codes, true)) {
                throw ValidationException::withMessages([
                    'steps' => "Mã trạng thái «{$group}» không thuộc bộ của quy trình.",
                ]);
            }

            $normalized[] = [
                'sort_order' => (int) ($row['sort_order'] ?? $order),
                'name' => trim((string) ($row['name'] ?? 'Bước '.$order)),
                'status_group_code' => $group,
                'step_type' => $stepType,
                'target_form_keys' => $row['target_form_keys'] ?? [],
                'assignee_policy' => $row['assignee_policy'] ?? 'role',
                'assignee_role' => $row['assignee_role'] ?? null,
                'assignee_user_id' => $row['assignee_user_id'] ?? null,
                'sla_hours' => isset($row['sla_hours']) ? (int) $row['sla_hours'] : null,
                'sign_mode' => $row['sign_mode'] ?? 'online',
                'workspace_task' => (bool) ($row['workspace_task'] ?? false),
                'allow_reject' => array_key_exists('allow_reject', $row) ? (bool) $row['allow_reject'] : true,
                'allow_request_changes' => array_key_exists('allow_request_changes', $row)
                    ? (bool) $row['allow_request_changes']
                    : true,
                'blocks_cancel' => (bool) ($row['blocks_cancel'] ?? false),
            ];
        }

        DB::transaction(function () use ($workflow, $normalized) {
            DonTuWorkflowStep::query()->where('workflow_id', $workflow->id)->delete();
            foreach ($normalized as $row) {
                DonTuWorkflowStep::query()->create([
                    'workflow_id' => $workflow->id,
                    ...$row,
                ]);
            }
        });

        return $workflow->fresh(['forms.formTemplate', 'steps', 'statusSet']);
    }

    public function destroy(DonTuWorkflow $workflow): void
    {
        if ($workflow->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'Tắt quy trình trước khi xóa.',
            ]);
        }

        $workflow->delete();
    }

    private function normalizeCode(string $raw): string
    {
        $code = Str::upper(Str::slug($raw, '_'));
        $code = preg_replace('/[^A-Z0-9_]/', '', $code) ?: 'QT';

        return Str::limit($code, 60, '');
    }
}
