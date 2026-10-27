<?php

namespace Modules\DonTu\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\DonTu\App\Models\DonTuStatus;
use Modules\DonTu\App\Models\DonTuStatusSet;
use Modules\DonTu\App\Models\DonTuTransition;

class DonTuStatusCatalogService
{
    public function listSets(): Collection
    {
        return DonTuStatusSet::query()
            ->withCount(['statuses', 'workflows'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (DonTuStatusSet $set) => $this->presentSetSummary($set));
    }

    public function findSet(int $id): ?array
    {
        $set = DonTuStatusSet::query()
            ->withCount('workflows')
            ->with([
                'statuses',
                'transitions',
                'workflows' => fn ($q) => $q->select('id', 'status_set_id', 'code', 'name', 'is_active')->orderBy('name'),
            ])
            ->find($id);

        if ($set === null) {
            return null;
        }

        return $this->presentSetDetail($set);
    }

    public function findSetByCode(string $code): ?array
    {
        $set = DonTuStatusSet::query()
            ->with(['statuses', 'transitions'])
            ->where('code', $code)
            ->first();

        if ($set === null) {
            return null;
        }

        return $this->presentSetDetail($set);
    }

    public function addStatus(int $setId, array $data): array
    {
        $set = DonTuStatusSet::query()->find($setId);
        if ($set === null) {
            throw ValidationException::withMessages([
                'status_set_id' => 'Không tìm thấy nhóm trạng thái.',
            ]);
        }

        $label = trim((string) ($data['label'] ?? ''));
        $group = (string) ($data['group_kind'] ?? '');
        $from = (string) ($data['from_code'] ?? '');
        $action = (string) ($data['action_key'] ?? '');
        $actor = (string) ($data['actor_policy'] ?? '');

        $known = $set->statuses()->pluck('code')->all();
        if ($from !== 'NONE' && ! in_array($from, $known, true)) {
            throw ValidationException::withMessages([
                'from_code' => 'Trạng thái phía trước không có trong nhóm này.',
            ]);
        }

        $terminal = null;
        if ($group === 'terminal') {
            $terminal = (string) ($data['terminal_kind'] ?? '');
            if (! in_array($terminal, ['success', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'terminal_kind' => 'Chọn kết quả khi hồ sơ kết thúc.',
                ]);
            }
        }

        $code = $this->uniqueStatusCode($set->id, $label);
        $sort = ((int) $set->statuses()->max('sort_order')) + 1;

        $duplicate = DonTuTransition::query()
            ->where('status_set_id', $set->id)
            ->where('from_code', $from)
            ->where('action_key', $action)
            ->where('to_code', $code)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'action_key' => 'Cách đổi này đã có.',
            ]);
        }

        DB::transaction(function () use ($set, $code, $label, $group, $terminal, $sort, $from, $action, $actor) {
            DonTuStatus::query()->create([
                'status_set_id' => $set->id,
                'code' => $code,
                'label' => $label,
                'group_kind' => $group,
                'terminal_kind' => $terminal,
                'sort_order' => $sort,
            ]);
            DonTuTransition::query()->create([
                'status_set_id' => $set->id,
                'from_code' => $from,
                'to_code' => $code,
                'action_key' => $action,
                'actor_policy' => $actor,
                'guard_json' => null,
            ]);
        });

        return $this->findSet($set->id);
    }

    private function uniqueStatusCode(int $setId, string $label): string
    {
        $base = Str::upper(Str::slug($label, '_'));
        $base = preg_replace('/[^A-Z0-9_]/', '', $base) ?: 'TRANG_THAI';
        $base = Str::limit($base, 56, '');
        $code = $base;
        $n = 2;
        while (DonTuStatus::query()->where('status_set_id', $setId)->where('code', $code)->exists()) {
            $code = $base.'_'.$n;
            $n++;
        }

        return Str::limit($code, 64, '');
    }

    private function presentSetSummary(DonTuStatusSet $set): array
    {
        return [
            'id' => $set->id,
            'code' => $set->code,
            'name' => $set->name,
            'description' => $set->description,
            'is_system' => $set->is_system,
            'status_count' => $set->statuses_count ?? $set->statuses()->count(),
            'workflow_count' => $set->workflows_count ?? $set->workflows()->count(),
        ];
    }

    private function presentSetDetail(DonTuStatusSet $set): array
    {
        return [
            ...$this->presentSetSummary($set),
            'statuses' => $set->statuses->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'label' => $s->label,
                'group_kind' => $s->group_kind,
                'terminal_kind' => $s->terminal_kind,
                'sort_order' => $s->sort_order,
            ])->values()->all(),
            'transitions' => $set->transitions->map(fn ($t) => [
                'id' => $t->id,
                'from_code' => $t->from_code,
                'to_code' => $t->to_code,
                'action_key' => $t->action_key,
                'actor_policy' => $t->actor_policy,
                'guard_json' => $t->guard_json,
            ])->values()->all(),
            'workflows' => $set->workflows->map(fn ($w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'is_active' => $w->is_active,
            ])->values()->all(),
        ];
    }
}
