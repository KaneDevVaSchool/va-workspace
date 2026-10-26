<?php

namespace Modules\DonTu\App\Services;

use Illuminate\Support\Collection;
use Modules\DonTu\App\Models\DonTuStatusSet;

class DonTuStatusCatalogService
{
    public function listSets(): Collection
    {
        return DonTuStatusSet::query()
            ->withCount('statuses')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (DonTuStatusSet $set) => $this->presentSetSummary($set));
    }

    public function findSet(int $id): ?array
    {
        $set = DonTuStatusSet::query()
            ->with(['statuses', 'transitions'])
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

    private function presentSetSummary(DonTuStatusSet $set): array
    {
        return [
            'id' => $set->id,
            'code' => $set->code,
            'name' => $set->name,
            'description' => $set->description,
            'is_system' => $set->is_system,
            'status_count' => $set->statuses_count ?? $set->statuses()->count(),
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
        ];
    }
}
