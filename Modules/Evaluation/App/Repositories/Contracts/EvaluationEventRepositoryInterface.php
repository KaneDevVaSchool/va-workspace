<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationEvent;

interface EvaluationEventRepositoryInterface
{
    public function find(int $id): ?EvaluationEvent;

    /**
     * @param  array{user_id?: int, status?: string, from?: string, to?: string, q?: string}  $filters
     * @return Collection<int, EvaluationEvent>
     */
    public function allByDepartment(int $departmentId, array $filters = []): Collection;

    /**
     * Cùng bộ lọc với allByDepartment nhưng phân trang ở máy chủ — phòng ban
     * vài nghìn ghi nhận thì không tải hết về trình duyệt được.
     *
     * @param  array{user_id?: int, status?: string, from?: string, to?: string, q?: string}  $filters
     */
    public function paginateByDepartment(
        int $departmentId,
        array $filters,
        int $perPage,
        int $page,
    ): LengthAwarePaginator;

    /**
     * Sự kiện đã duyệt của một nhân sự trong khoảng ngày — nguồn điểm cộng /
     * trừ hành vi khi tính điểm kỳ báo cáo.
     *
     * @return Collection<int, EvaluationEvent>
     */
    public function approvedForUserInPeriod(
        int $departmentId,
        int $userId,
        string $from,
        string $to,
    ): Collection;

    /**
     * Sự kiện đã duyệt của cả phòng ban trong khoảng ngày, gom theo user_id —
     * tránh N+1 khi tính điểm toàn phòng.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, Collection<int, EvaluationEvent>>
     */
    public function approvedForDepartmentInPeriod(
        int $departmentId,
        array $userIds,
        string $from,
        string $to,
    ): Collection;

    /**
     * Đã có ghi nhận y hệt chưa — cùng nhân sự, tiêu chí, mức, ngày và công
     * việc.
     *
     * Dùng để CẢNH BÁO chứ không để chặn: một hành vi hoàn toàn có thể lặp lại
     * trong cùng một ngày và đáng được ghi nhận nhiều lần. Chỉ là khi bấm
     * nhanh hai lần thì người dùng cần biết mình vừa tạo bản thứ hai.
     */
    public function existsSimilar(
        int $departmentId,
        int $userId,
        int $criterionId,
        string $levelCode,
        string $occurredAt,
        ?int $taskId,
    ): bool;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): EvaluationEvent;

    /** @param  array<string, mixed>  $data */
    public function update(EvaluationEvent $event, array $data): EvaluationEvent;

    public function delete(EvaluationEvent $event): void;
}
