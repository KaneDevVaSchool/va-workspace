<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Validation\ValidationException;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Bảng tổng hợp đánh giá của cả phòng ban trong một kỳ.
 *
 * Đây là màn hình làm việc chính khi chấm điểm cuối kỳ: mỗi nhân sự một dòng,
 * kèm số liệu công việc, điểm theo từng tiêu chí và điểm tổng. Mở rộng một
 * dòng ra thì thấy từng công việc để ghi nhận ngay tại đó.
 *
 * Lớp này chỉ điều phối — toàn bộ phép tính điểm nằm ở
 * EvaluationScoreComputeService, danh sách nhân sự lấy qua repository của
 * Identity. Không truy vấn Eloquent trực tiếp ở đây.
 */
class EvaluationSummaryService
{
    public function __construct(
        private readonly EvaluationConfigVersionService $versions,
        private readonly EvaluationScoreComputeService $compute,
        private readonly EvaluationEventService $events,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Số liệu cả phòng ban trong kỳ.
     *
     * @return array<string, mixed>
     */
    public function summarize(int $departmentId, string $from, string $to): array
    {
        $version = $this->versionOrFail($departmentId);

        $people = $this->people($departmentId);

        // $asOf = ngày cuối kỳ: một việc chưa xong được coi là trễ hạn hay
        // không phải xét theo mốc cuối kỳ, không phải theo hôm nay — nếu
        // không thì mở lại cùng một kỳ ở hai thời điểm sẽ ra hai kết quả.
        $result = $this->compute->computeForPeople($people, $version, $from, $to, $to);

        return [
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'criteria' => $this->criteriaFor($version, $departmentId),
            'period' => ['from' => $from, 'to' => $to],
            'version_no' => $version->version_no,
        ];
    }

    /**
     * Tính lại đúng một dòng — dùng ngay sau khi ghi nhận / duyệt / xoá một
     * đánh giá, để giao diện thay dòng đó bằng số liệu máy chủ vừa tính thay
     * vì tự cộng trừ lấy (dễ lệch) hoặc tải lại cả bảng (chậm).
     *
     * @return array<string, mixed>|null
     */
    public function rowFor(int $departmentId, int $userId, string $from, string $to): ?array
    {
        $version = $this->versions->activeForDepartment($departmentId);

        if ($version === null) {
            return null;
        }

        $person = collect($this->people($departmentId))
            ->firstWhere('id', $userId);

        if ($person === null) {
            return null;
        }

        return $this->compute->computeForUser(
            $userId,
            (string) $person['name'],
            $version,
            $from,
            $to,
            $to,
        );
    }

    /**
     * Tiêu chí dùng làm cột trên bảng.
     *
     * Lấy từ bản chụp của phiên bản đang áp dụng, KHÔNG lấy cấu hình sống:
     * điểm đang được tính theo bản chụp, nên nếu cột hiển thị theo cấu hình
     * hiện tại thì người dùng sẽ thấy một mức điểm khác với mức máy đang cộng.
     *
     * Bản chụp cũ (chốt trước khi có criteria_snapshot) thì đành lấy danh mục
     * sống — vẫn hơn là bảng trống không cột nào.
     *
     * @return list<array<string, mixed>>
     */
    private function criteriaFor(EvaluationConfigVersion $version, int $departmentId): array
    {
        $snapshot = $version->criteria_snapshot ?? [];

        if (! is_array($snapshot) || $snapshot === []) {
            return $this->events->behaviorCatalog($departmentId);
        }

        $out = [];
        foreach ($snapshot as $criterion) {
            if (! is_array($criterion) || ($criterion['type'] ?? null) !== 'behavior') {
                continue;
            }

            $out[] = [
                'id' => (int) ($criterion['id'] ?? 0),
                'name' => (string) ($criterion['name'] ?? ''),
                'criterion_type_id' => $criterion['criterion_type_id'] ?? null,
                // Bản chụp lưu nhóm tiêu chí ở dạng object lồng
                // (EvaluationCriteriaService::present), còn giao diện chỉ cần
                // tên — lấy phẳng ra cho khớp behaviorCatalog().
                'criterion_type_name' => $criterion['criterion_type']['name'] ?? null,
                'levels' => $this->snapshotLevels($criterion),
            ];
        }

        return $out;
    }

    /**
     * Thang mức của một tiêu chí trong bản chụp, chuẩn hoá giống
     * EvaluationEventService::behaviorCatalog() để giao diện dùng chung một
     * dạng dữ liệu dù nguồn là bản chụp hay danh mục sống.
     *
     * @param  array<string, mixed>  $criterion
     * @return list<array{code: string, label: string, score: float}>
     */
    private function snapshotLevels(array $criterion): array
    {
        $levels = $criterion['levels'] ?? [];
        $out = [];

        foreach (array_values(is_array($levels) ? $levels : []) as $index => $level) {
            if (! is_array($level)) {
                continue;
            }

            $out[] = [
                'code' => EvaluationCriteria::levelKey($level, $index),
                'label' => (string) ($level['label'] ?? ''),
                'score' => (float) ($level['score'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Nhân sự đang hoạt động của phòng ban.
     *
     * @return list<array{id: int, name: string}>
     */
    private function people(int $departmentId): array
    {
        return $this->users
            ->allActiveByDepartment($departmentId)
            ->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()
            ->all();
    }

    private function versionOrFail(int $departmentId): EvaluationConfigVersion
    {
        $version = $this->versions->activeForDepartment($departmentId);

        if ($version === null) {
            throw ValidationException::withMessages([
                'version' => 'Phòng ban chưa chốt khung chấm điểm nào nên chưa tổng hợp được. '
                    .'Vào Khung chấm điểm để chốt phiên bản đầu tiên.',
            ]);
        }

        return $version;
    }
}
