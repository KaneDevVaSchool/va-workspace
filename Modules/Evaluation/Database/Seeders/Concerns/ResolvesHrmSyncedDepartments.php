<?php

namespace Modules\Evaluation\Database\Seeders\Concerns;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmWorkspaceConstraint;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;

/**
 * Chọn phòng ban mục tiêu khi seed tiêu chí — khớp ràng buộc VA-HRM (active + uuid).
 */
trait ResolvesHrmSyncedDepartments
{
    /**
     * @param  list<string>  $hrmExternalCodes  Mã phần mềm danh mục HRM (departments.external_code)
     * @param  list<string>  $legacyDepartmentCodes  departments.code khi chưa cấu HRM (local/dev)
     * @return Collection<int, Department>
     */
    protected function resolveSeedTargetDepartments(
        array $hrmExternalCodes,
        array $legacyDepartmentCodes = [],
        ?string $companyCode = null,
    ): Collection {
        if (HrmDepartmentSyncService::isConfigured()) {
            app(HrmDepartmentSyncService::class)->syncDepartmentsFromHrm();

            $codes = array_values(array_unique(array_filter(array_map(
                fn (string $code): string => strtoupper(trim($code)),
                $hrmExternalCodes,
            ))));

            if ($codes === []) {
                $this->seedLineWarn('Thiếu mã phòng ban HRM (external_code).');

                return collect();
            }

            $query = Department::query()
                ->whereNotNull('hrm_org_unit_uuid')
                ->where('is_active', true)
                ->where(function ($builder) use ($codes): void {
                    foreach ($codes as $code) {
                        $builder->orWhereRaw('UPPER(TRIM(external_code)) = ?', [$code]);
                    }
                });

            if ($companyCode !== null && trim($companyCode) !== '') {
                $companyId = Company::query()->where('code', $companyCode)->value('id');
                if ($companyId === null) {
                    $this->seedLineWarn("Không tìm thấy công ty code «{$companyCode}».");

                    return collect();
                }
                $query->where('company_id', $companyId);
            }

            $constraint = app(HrmWorkspaceConstraint::class);

            return $query->orderBy('id')->get()->filter(function (Department $department) use ($constraint): bool {
                return $constraint->validateDepartmentIds([(int) $department->id]) === null;
            })->values();
        }

        if ($legacyDepartmentCodes === []) {
            $this->seedLineWarn('HRM chưa cấu hình và không có code phòng ban legacy — bỏ qua seed.');

            return collect();
        }

        $this->seedLineWarn('HRM chưa cấu hình — seed theo code phòng ban legacy (chỉ phù hợp local/dev).');

        return Department::query()
            ->whereIn('code', $legacyDepartmentCodes)
            ->orderBy('id')
            ->get();
    }

    /**
     * Một phòng ban / pháp nhân — thử external_code theo thứ tự ưu tiên (vd. VM_PCN rồi CN).
     *
     * @param  array<string, list<string>>  $companyExternalCodesPriority  VM => [VM_PCN, CN], …
     * @return Collection<int, Department>
     */
    protected function resolveOneHrmDepartmentPerCompany(array $companyExternalCodesPriority): Collection
    {
        $picked = collect();

        foreach ($companyExternalCodesPriority as $companyCode => $externalCodes) {
            $candidates = $this->resolveSeedTargetDepartments($externalCodes, [], (string) $companyCode);
            $department = $this->pickPreferredDepartment($candidates, $externalCodes);

            if ($department === null) {
                $this->seedLineWarn(sprintf(
                    'Không tìm phòng ban HRM cho pháp nhân «%s» (mã: %s).',
                    $companyCode,
                    implode(', ', $externalCodes),
                ));

                continue;
            }

            $picked->push($department);
        }

        return $picked->values();
    }

    /**
     * @param  Collection<int, Department>  $candidates
     * @param  list<string>  $preferredExternalCodes
     */
    private function pickPreferredDepartment(Collection $candidates, array $preferredExternalCodes): ?Department
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        foreach ($preferredExternalCodes as $code) {
            $normalized = strtoupper(trim($code));
            $match = $candidates->first(
                fn (Department $department): bool => strtoupper(trim((string) ($department->external_code ?? ''))) === $normalized,
            );
            if ($match !== null) {
                return $match;
            }
        }

        return $candidates->sortByDesc('id')->first();
    }

    protected function seedLineWarn(string $message): void
    {
        if (property_exists($this, 'command') && $this->command instanceof Command) {
            $this->command->warn($message);
        }
    }
}
