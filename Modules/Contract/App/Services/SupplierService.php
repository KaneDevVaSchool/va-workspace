<?php

namespace Modules\Contract\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Contract\App\Enums\ContractEnums;
use Modules\Contract\App\Models\Contract;
use Modules\Contract\App\Models\ContractAuditLog;
use Modules\Contract\App\Models\Supplier;
use Modules\Contract\App\Models\SupplierDocument;
use Modules\Contract\App\Models\SupplierDocumentRequirement;
use Modules\Contract\App\Models\SupplierDocumentType;
use Modules\Contract\App\Models\SupplierDocumentVersion;
use Modules\Contract\App\Models\SupplierGroup;
use Modules\Contract\App\Models\SupplierType;
use Modules\Identity\App\Models\Department;

class SupplierService
{
    public function canManage(User $user, ?Supplier $supplier = null): bool
    {
        if ($user->isSuperAdmin() || $user->allows('contract.*')) {
            return true;
        }

        if (! $user->allows('contract.manage_department')) {
            return false;
        }

        if ($supplier === null) {
            return $user->department_id !== null;
        }

        return $supplier->department_id !== null && (int) $supplier->department_id === (int) $user->department_id;
    }

    /** @return list<int>|null null means global scope. */
    public function departmentScopeFor(User $viewer): ?array
    {
        if ($viewer->isSuperAdmin() || $viewer->allows('contract.*') || $viewer->allows('dashboard.view_company')) {
            return null;
        }

        return $viewer->department_id !== null ? [(int) $viewer->department_id] : [];
    }

    public function options(User $viewer): array
    {
        $departmentScope = $this->departmentScopeFor($viewer);

        return [
            'supplier_statuses' => $this->optionMap(ContractEnums::SUPPLIER_STATUS_LABELS),
            'document_statuses' => $this->optionMap([
                'complete' => 'Đầy đủ',
                'missing' => 'Thiếu hồ sơ',
                'expiring_soon' => 'Sắp hết hạn',
                'needs_supplement' => 'Yêu cầu bổ sung',
                'pending_review' => 'Chờ kiểm tra',
            ]),
            'contract_statuses' => $this->optionMap([
                'has_effective' => 'Có HĐ hiệu lực',
                'no_effective' => 'Chưa có HĐ hiệu lực',
                'expiring_soon' => 'HĐ sắp hết hạn',
            ]),
            'types' => SupplierType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
            'groups' => SupplierGroup::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'supplier_type_id', 'name'])
                ->values(),
            'departments' => $this->departmentOptions($departmentScope),
            'owners' => $this->ownerOptions($departmentScope),
            'next_code' => $this->nextCode(),
            'can_manage' => $this->canManage($viewer),
            'can_choose_department' => $departmentScope === null,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        $scope = $this->departmentScopeFor($viewer);
        $query = Supplier::query()->with(['type', 'group', 'department', 'owner', 'documents.type']);
        $this->applyDepartmentScope($query, $scope);
        $this->applyFilters($query, $filters);

        $paginator = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn (Supplier $supplier) => $this->presentListItem($supplier, $viewer));

        return $paginator;
    }

    /** @param array<string, mixed> $filters */
    public function statusCounts(array $filters, User $viewer): array
    {
        $scope = $this->departmentScopeFor($viewer);
        $result = ['all' => 0];
        foreach (ContractEnums::SUPPLIER_STATUSES as $status) {
            $result[$status] = 0;
        }

        $base = Supplier::query();
        $this->applyDepartmentScope($base, $scope);
        $this->applyFilters($base, array_diff_key($filters, ['status' => true]));

        $result['all'] = (clone $base)->count();
        $rows = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        foreach (ContractEnums::SUPPLIER_STATUSES as $status) {
            $result[$status] = (int) ($rows[$status] ?? 0);
        }

        return $result;
    }

    public function find(int $id, User $viewer): ?Supplier
    {
        $query = Supplier::query()->with([
            'type',
            'group',
            'department',
            'owner',
            'confirmer',
            'contacts',
            'bankAccounts',
            'documents.type',
            'documents.versions.uploader',
            'contracts' => fn ($q) => $q->orderByDesc('starts_at')->limit(8),
        ]);
        $this->applyDepartmentScope($query, $this->departmentScopeFor($viewer));

        return $query->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): Supplier
    {
        return DB::transaction(function () use ($data, $creator) {
            $payload = $this->supplierPayload($data, $creator);
            $payload['code'] = $payload['code'] ?? $this->nextCode();
            $payload['status'] = ContractEnums::SUPPLIER_DRAFT;
            $payload['created_by'] = $creator->id;
            $payload['updated_by'] = $creator->id;

            $supplier = Supplier::query()->create($payload);
            $this->syncContacts($supplier, $data['contacts'] ?? []);
            $this->syncBankAccounts($supplier, $data['bank_accounts'] ?? []);
            $this->audit('supplier', $supplier->id, $supplier->code, 'supplier.create', null, null, null, false, "Tạo nhà cung cấp {$supplier->code}", $creator);

            return $supplier->fresh(['type', 'group', 'department', 'owner', 'contacts', 'bankAccounts', 'documents.type']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Supplier $supplier, array $data, User $updater): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $updater) {
            $before = $supplier->only(['name', 'tax_code', 'legal_name', 'representative_name', 'representative_title']);
            $payload = $this->supplierPayload($data, $updater, $supplier);
            $payload['updated_by'] = $updater->id;
            unset($payload['code']);
            $supplier->update($payload);
            $this->syncContacts($supplier, $data['contacts'] ?? []);
            $this->syncBankAccounts($supplier, $data['bank_accounts'] ?? []);
            $supplier->refresh();
            $this->auditSensitiveChanges($supplier, $before, $updater);

            return $supplier->fresh(['type', 'group', 'department', 'owner', 'contacts', 'bankAccounts', 'documents.type']);
        });
    }

    public function delete(Supplier $supplier, User $actor): void
    {
        if ($supplier->status !== ContractEnums::SUPPLIER_DRAFT || $supplier->contracts()->exists()) {
            throw new \InvalidArgumentException('Chỉ xoá được NCC ở trạng thái Nháp và chưa phát sinh hợp đồng.');
        }

        $code = $supplier->code;
        $id = $supplier->id;
        $supplier->delete();
        $this->audit('supplier', $id, $code, 'supplier.delete', null, null, null, false, "Xoá nhà cung cấp {$code}", $actor);
    }

    public function changeStatus(Supplier $supplier, string $status, string $reason, User $actor): Supplier
    {
        if (! in_array($status, ContractEnums::SUPPLIER_STATUSES, true)) {
            throw new \InvalidArgumentException('Trạng thái NCC không hợp lệ.');
        }

        $old = $supplier->status;
        $payload = ['status' => $status, 'status_reason' => $reason, 'updated_by' => $actor->id];
        if ($status === ContractEnums::SUPPLIER_ACTIVE && $supplier->confirmed_at === null) {
            $payload['confirmed_by'] = $actor->id;
            $payload['confirmed_at'] = now();
            $payload['cooperation_started_at'] = now()->toDateString();
        }
        $supplier->update($payload);
        $this->audit('supplier', $supplier->id, $supplier->code, 'supplier.status', 'status', $old, $status, false, $reason, $actor);

        return $supplier->fresh(['type', 'group', 'department', 'owner', 'contacts', 'bankAccounts', 'documents.type']);
    }

    /** @param array<string, mixed> $data */
    public function uploadDocument(Supplier $supplier, array $data, User $actor): SupplierDocument
    {
        return DB::transaction(function () use ($supplier, $data, $actor) {
            $documentTypeId = $data['document_type_id'] ?? null;
            $document = SupplierDocument::query()
                ->where('supplier_id', $supplier->id)
                ->when($documentTypeId, fn ($q) => $q->where('document_type_id', $documentTypeId))
                ->when(! $documentTypeId, fn ($q) => $q->where('custom_name', $data['custom_name'] ?? null))
                ->first();

            if (! $document) {
                $document = SupplierDocument::query()->create([
                    'supplier_id' => $supplier->id,
                    'document_type_id' => $documentTypeId,
                    'custom_name' => $data['custom_name'] ?? null,
                ]);
            }

            $version = ((int) $document->current_version) + 1;
            $file = $data['file'] ?? null;
            $path = $file ? $file->store("contract/suppliers/{$supplier->id}", 'public') : null;

            SupplierDocumentVersion::query()->create([
                'supplier_document_id' => $document->id,
                'version' => $version,
                'original_name' => $file?->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file?->getClientMimeType(),
                'size_bytes' => $file?->getSize(),
                'status' => ContractEnums::DOCUMENT_PROVIDED,
                'uploader_note' => $data['uploader_note'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            $document->update([
                'status' => ContractEnums::DOCUMENT_PROVIDED,
                'current_version' => $version,
                'document_number' => $data['document_number'] ?? null,
                'issuer' => $data['issuer'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'expires_at' => ($data['no_expiry'] ?? false) ? null : ($data['expires_at'] ?? null),
                'no_expiry' => (bool) ($data['no_expiry'] ?? false),
                'uploader_note' => $data['uploader_note'] ?? null,
                'uploaded_by' => $actor->id,
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $this->audit('supplier_document', $document->id, $supplier->code, 'supplier_document.upload', null, null, null, false, "Tải hồ sơ {$this->documentName($document)}", $actor);

            return $document->fresh(['type', 'versions.uploader']);
        });
    }

    /** @param array<string, mixed> $data */
    public function reviewDocument(SupplierDocument $document, array $data, User $actor): SupplierDocument
    {
        $old = $document->status;
        $status = $data['status'];
        if (! in_array($status, [ContractEnums::DOCUMENT_VALID, ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT, ContractEnums::DOCUMENT_INVALID], true)) {
            throw new \InvalidArgumentException('Kết quả kiểm tra không hợp lệ.');
        }

        $document->update([
            'status' => $status,
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->audit('supplier_document', $document->id, $document->supplier?->code, 'supplier_document.review', 'status', $old, $status, false, $data['review_note'] ?? null, $actor);

        return $document->fresh(['type', 'versions.uploader', 'reviewer']);
    }

    public function presentListItem(Supplier $supplier, User $viewer): array
    {
        $summary = $this->documentSummary($supplier);

        return [
            'id' => $supplier->id,
            'code' => $supplier->code,
            'name' => $supplier->name,
            'short_name' => $supplier->short_name,
            'tax_code' => $supplier->tax_code,
            'formatted_tax_code' => $this->formatTaxCode($supplier->tax_code),
            'type_name' => $supplier->type->name ?? null,
            'group_name' => $supplier->group->name ?? null,
            'department_name' => $supplier->department->name ?? null,
            'owner' => $supplier->owner ? ['id' => $supplier->owner->id, 'name' => $supplier->owner->name, 'email' => $supplier->owner->email] : null,
            'status' => $supplier->status,
            'status_label' => ContractEnums::SUPPLIER_STATUS_LABELS[$supplier->status] ?? $supplier->status,
            'document_summary' => $summary,
            'active_contracts_count' => $this->activeContractsCount($supplier),
            'can_manage' => $this->canManage($viewer, $supplier),
            'can_delete' => $supplier->status === ContractEnums::SUPPLIER_DRAFT && ! $supplier->contracts()->exists() && $this->canManage($viewer, $supplier),
        ];
    }

    public function presentDetail(Supplier $supplier, User $viewer): array
    {
        return [
            ...$this->presentListItem($supplier, $viewer),
            'legal_name' => $supplier->legal_name,
            'trade_name' => $supplier->trade_name,
            'representative_name' => $supplier->representative_name,
            'representative_title' => $supplier->representative_title,
            'registered_address_line' => $supplier->registered_address_line,
            'registered_ward' => $supplier->registered_ward,
            'registered_province' => $supplier->registered_province,
            'transaction_address_same_as_registered' => $supplier->transaction_address_same_as_registered,
            'transaction_address_line' => $supplier->transaction_address_line,
            'transaction_ward' => $supplier->transaction_ward,
            'transaction_province' => $supplier->transaction_province,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'supplier_type_id' => $supplier->supplier_type_id,
            'supplier_group_id' => $supplier->supplier_group_id,
            'department_id' => $supplier->department_id,
            'owner_user_id' => $supplier->owner_user_id,
            'cooperation_started_at' => $supplier->cooperation_started_at?->toDateString(),
            'confirmed_at' => $supplier->confirmed_at?->toIso8601String(),
            'confirmed_by_name' => $supplier->confirmer->name ?? null,
            'contacts' => $supplier->contacts->values()->map(fn ($c) => $c->only(['id', 'contact_type', 'full_name', 'position', 'department', 'email', 'phone', 'is_primary'])),
            'bank_accounts' => $supplier->bankAccounts->values()->map(fn ($b) => $b->only(['id', 'bank_name', 'branch', 'account_holder', 'account_number', 'currency', 'is_default', 'notes'])),
            'documents' => $this->documentChecklist($supplier),
            'contracts' => $supplier->contracts->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'code' => $contract->code,
                'title' => $contract->title,
                'status' => $contract->status,
                'status_label' => ContractEnums::CONTRACT_STATUS_LABELS[$contract->status] ?? $contract->status,
                'ends_at' => $contract->ends_at?->toDateString(),
                'days_left' => ContractEnums::daysLeft($contract->ends_at),
            ])->values(),
            'attention_items' => $this->attentionItems($supplier),
        ];
    }

    public function nextCode(): string
    {
        $max = Supplier::query()
            ->where('code', 'like', 'NCC-%')
            ->selectRaw("MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_code")
            ->value('max_code');

        return 'NCC-'.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function normalizeTaxCode(?string $taxCode): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $taxCode);

        return $normalized !== '' ? $normalized : null;
    }

    public function findDuplicateTaxCode(?string $taxCode, ?int $ignoreId = null, ?User $viewer = null): ?Supplier
    {
        $normalized = $this->normalizeTaxCode($taxCode);
        if ($normalized === null) {
            return null;
        }

        $query = Supplier::query()->where('normalized_tax_code', $normalized);
        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }
        if ($viewer) {
            $this->applyDepartmentScope($query, $this->departmentScopeFor($viewer));
        }

        return $query->first();
    }

    /** @param Builder<Supplier> $query */
    private function applyDepartmentScope(Builder $query, ?array $departmentScope): void
    {
        if ($departmentScope === null) {
            return;
        }

        $query->whereIn('department_id', $departmentScope);
    }

    /** @param Builder<Supplier> $query @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $normalized = $this->normalizeTaxCode($q);
            $query->where(function (Builder $sub) use ($q, $normalized) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('short_name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('tax_code', 'like', "%{$q}%");
                if ($normalized !== null) {
                    $sub->orWhere('normalized_tax_code', 'like', "%{$normalized}%");
                }
            });
        }

        foreach (['status', 'supplier_type_id', 'supplier_group_id', 'department_id', 'owner_user_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (! empty($filters['document_status'])) {
            $this->applyDocumentFilter($query, $filters['document_status']);
        }

        if (! empty($filters['contract_status'])) {
            $this->applyContractFilter($query, $filters['contract_status']);
        }
    }

    /** @param Builder<Supplier> $query */
    private function applyDocumentFilter(Builder $query, string $status): void
    {
        if ($status === 'expiring_soon') {
            $query->whereHas('documents', fn ($q) => $q->whereBetween('expires_at', [now()->toDateString(), now()->addDays(ContractEnums::DEFAULT_EXPIRY_DAYS)->toDateString()]));
        } elseif ($status === 'needs_supplement') {
            $query->whereHas('documents', fn ($q) => $q->where('status', ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT));
        } elseif ($status === 'pending_review') {
            $query->whereHas('documents', fn ($q) => $q->whereIn('status', [ContractEnums::DOCUMENT_PROVIDED, ContractEnums::DOCUMENT_REVIEWING]));
        } elseif ($status === 'missing') {
            $query->where(function (Builder $q) {
                $q->whereDoesntHave('documents')
                    ->orWhereHas('documents', fn ($d) => $d->where('status', ContractEnums::DOCUMENT_NOT_PROVIDED));
            });
        }
    }

    /** @param Builder<Supplier> $query */
    private function applyContractFilter(Builder $query, string $status): void
    {
        if ($status === 'has_effective') {
            $query->whereHas('contracts', fn ($q) => $q->where('status', ContractEnums::CONTRACT_EFFECTIVE));
        } elseif ($status === 'no_effective') {
            $query->whereDoesntHave('contracts', fn ($q) => $q->where('status', ContractEnums::CONTRACT_EFFECTIVE));
        } elseif ($status === 'expiring_soon') {
            $query->whereHas('contracts', fn ($q) => $q
                ->where('status', ContractEnums::CONTRACT_EFFECTIVE)
                ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(ContractEnums::DEFAULT_EXPIRY_DAYS)->toDateString()]));
        }
    }

    private function documentSummary(Supplier $supplier): array
    {
        $checklist = collect($this->documentChecklist($supplier));
        $required = $checklist->where('requirement', 'required');
        $missing = $required->whereIn('status', [ContractEnums::DOCUMENT_NOT_PROVIDED])->count();
        $expiring = $checklist->filter(fn ($d) => $d['days_left'] !== null && $d['days_left'] >= 0 && $d['days_left'] <= ContractEnums::DEFAULT_EXPIRY_DAYS)->count();
        $needsSupplement = $checklist->where('status', ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT)->count();
        $pendingReview = $checklist->whereIn('status', [ContractEnums::DOCUMENT_PROVIDED, ContractEnums::DOCUMENT_REVIEWING])->count();

        return [
            'required_total' => $required->count(),
            'required_valid' => $required->where('status', ContractEnums::DOCUMENT_VALID)->count(),
            'missing_required' => $missing,
            'expiring_soon' => $expiring,
            'needs_supplement' => $needsSupplement,
            'pending_review' => $pendingReview,
            'label' => $this->documentSummaryLabel($missing, $expiring, $needsSupplement, $pendingReview),
        ];
    }

    private function documentChecklist(Supplier $supplier): array
    {
        $supplier->loadMissing('documents.type');
        $documents = $supplier->documents->keyBy('document_type_id');

        return $this->documentTypesFor($supplier)->map(function (SupplierDocumentType $type) use ($supplier, $documents) {
            $document = $documents->get($type->id);
            $status = $document?->status ?? ContractEnums::DOCUMENT_NOT_PROVIDED;
            if ($document?->expires_at && $document->expires_at->isPast() && ! $document->expires_at->isToday()) {
                $status = ContractEnums::DOCUMENT_EXPIRED;
            }
            $daysLeft = ContractEnums::daysLeft($document?->expires_at);

            return [
                'id' => $document?->id,
                'document_type_id' => $type->id,
                'name' => $type->name,
                'group_label' => $type->group_label,
                'requirement' => $this->requirementFor($type, $supplier),
                'has_expiry' => $type->has_expiry,
                'requires_review' => $type->requires_review,
                'status' => $status,
                'status_label' => ContractEnums::DOCUMENT_STATUS_LABELS[$status] ?? $status,
                'current_version' => $document?->current_version ?? 0,
                'issued_at' => $document?->issued_at?->toDateString(),
                'expires_at' => $document?->expires_at?->toDateString(),
                'days_left' => $daysLeft,
                'expiry_tone' => ContractEnums::expiryTone($daysLeft),
                'review_note' => $document?->review_note,
                'versions' => $document?->versions?->map(fn ($v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'original_name' => $v->original_name,
                    'path' => $v->path ? Storage::disk('public')->url($v->path) : null,
                    'size_bytes' => $v->size_bytes,
                    'uploaded_by_name' => $v->uploader->name ?? null,
                    'created_at' => $v->created_at?->toIso8601String(),
                ])->values() ?? [],
            ];
        })->values()->all();
    }

    private function documentTypesFor(Supplier $supplier): Collection
    {
        return SupplierDocumentType::query()
            ->where('is_active', true)
            ->whereHas('requirements', function (Builder $q) use ($supplier) {
                $q->where(function (Builder $rq) use ($supplier) {
                    $rq->whereNull('supplier_type_id')
                        ->orWhere('supplier_type_id', $supplier->supplier_type_id);
                })->where(function (Builder $rq) use ($supplier) {
                    $rq->whereNull('supplier_group_id')
                        ->orWhere('supplier_group_id', $supplier->supplier_group_id);
                });
            })
            ->with('requirements')
            ->orderBy('group_label')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function requirementFor(SupplierDocumentType $type, Supplier $supplier): string
    {
        $match = $type->requirements
            ->sortByDesc(fn ($r) => ($r->supplier_group_id ? 2 : 0) + ($r->supplier_type_id ? 1 : 0))
            ->first(fn ($r) => ($r->supplier_type_id === null || (int) $r->supplier_type_id === (int) $supplier->supplier_type_id)
                && ($r->supplier_group_id === null || (int) $r->supplier_group_id === (int) $supplier->supplier_group_id));

        return $match?->requirement ?? 'optional';
    }

    private function documentSummaryLabel(int $missing, int $expiring, int $needsSupplement, int $pendingReview): string
    {
        $parts = [];
        if ($missing > 0) $parts[] = "Thiếu {$missing}";
        if ($expiring > 0) $parts[] = "Sắp hết hạn {$expiring}";
        if ($needsSupplement > 0) $parts[] = "Yêu cầu bổ sung {$needsSupplement}";
        if ($pendingReview > 0) $parts[] = "Chờ kiểm tra {$pendingReview}";

        return $parts ? implode(' · ', $parts) : 'Đầy đủ';
    }

    private function activeContractsCount(Supplier $supplier): int
    {
        return $supplier->contracts()->where('status', ContractEnums::CONTRACT_EFFECTIVE)->count();
    }

    private function attentionItems(Supplier $supplier): array
    {
        $items = [];
        foreach ($this->documentChecklist($supplier) as $doc) {
            if ($doc['status'] === ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT || $doc['status'] === ContractEnums::DOCUMENT_EXPIRED || ($doc['days_left'] !== null && $doc['days_left'] <= ContractEnums::DEFAULT_EXPIRY_DAYS)) {
                $items[] = [
                    'type' => 'document',
                    'label' => $doc['name'],
                    'description' => $doc['review_note'] ?: $doc['status_label'],
                    'days_left' => $doc['days_left'],
                    'tone' => $doc['expiry_tone'],
                ];
            }
        }

        foreach ($supplier->contracts as $contract) {
            $daysLeft = ContractEnums::daysLeft($contract->ends_at);
            if ($contract->status === ContractEnums::CONTRACT_EFFECTIVE && $daysLeft !== null && $daysLeft <= ContractEnums::DEFAULT_EXPIRY_DAYS) {
                $items[] = [
                    'type' => 'contract',
                    'label' => "{$contract->code} · {$contract->title}",
                    'description' => $contract->ends_at ? 'Hết hạn '.$contract->ends_at->format('d/m/Y') : null,
                    'days_left' => $daysLeft,
                    'tone' => ContractEnums::expiryTone($daysLeft),
                ];
            }
        }

        usort($items, fn ($a, $b) => ($a['days_left'] ?? 9999) <=> ($b['days_left'] ?? 9999));

        return array_slice($items, 0, 8);
    }

    /** @param array<string, mixed> $data */
    private function supplierPayload(array $data, User $user, ?Supplier $supplier = null): array
    {
        $payload = collect($data)->only([
            'code',
            'name',
            'short_name',
            'tax_code',
            'supplier_type_id',
            'supplier_group_id',
            'legal_name',
            'trade_name',
            'representative_name',
            'representative_title',
            'registered_address_line',
            'registered_ward',
            'registered_province',
            'transaction_address_same_as_registered',
            'transaction_address_line',
            'transaction_ward',
            'transaction_province',
            'phone',
            'email',
            'department_id',
            'owner_user_id',
        ])->all();

        $payload['normalized_tax_code'] = $this->normalizeTaxCode($payload['tax_code'] ?? null);
        if ($this->departmentScopeFor($user) !== null) {
            $payload['department_id'] = $user->department_id;
        }
        if (empty($payload['department_id'])) {
            $payload['department_id'] = $supplier?->department_id ?? $user->department_id;
        }
        if (empty($payload['owner_user_id'])) {
            $payload['owner_user_id'] = $supplier?->owner_user_id ?? $user->id;
        }

        return $payload;
    }

    private function syncContacts(Supplier $supplier, array $contacts): void
    {
        $supplier->contacts()->delete();
        foreach (array_values($contacts) as $index => $contact) {
            if (empty($contact['full_name'])) {
                continue;
            }
            $contact['is_primary'] = (bool) ($contact['is_primary'] ?? $index === 0);
            $supplier->contacts()->create(collect($contact)->only(['contact_type', 'full_name', 'position', 'department', 'email', 'phone', 'is_primary'])->all());
        }
    }

    private function syncBankAccounts(Supplier $supplier, array $accounts): void
    {
        $supplier->bankAccounts()->delete();
        foreach (array_values($accounts) as $index => $account) {
            if (empty($account['bank_name']) || empty($account['account_number']) || empty($account['account_holder'])) {
                continue;
            }
            $account['is_default'] = (bool) ($account['is_default'] ?? $index === 0);
            $account['currency'] = $account['currency'] ?? 'VND';
            $supplier->bankAccounts()->create(collect($account)->only(['bank_name', 'branch', 'account_holder', 'account_number', 'currency', 'is_default', 'notes'])->all());
        }
    }

    private function auditSensitiveChanges(Supplier $supplier, array $before, User $actor): void
    {
        $labels = [
            'name' => 'Tên nhà cung cấp',
            'tax_code' => 'Mã số thuế',
            'legal_name' => 'Tên pháp lý',
            'representative_name' => 'Người đại diện',
            'representative_title' => 'Chức vụ người đại diện',
        ];
        foreach ($before as $field => $old) {
            $new = $supplier->{$field};
            if ((string) $old === (string) $new) {
                continue;
            }
            $this->audit('supplier', $supplier->id, $supplier->code, 'supplier.update_field', $field, $old, $new, true, $labels[$field] ?? $field, $actor);
        }
    }

    private function audit(string $subjectType, ?int $subjectId, ?string $subjectCode, string $action, ?string $field, mixed $oldValue, mixed $newValue, bool $sensitive, ?string $description, ?User $actor): void
    {
        ContractAuditLog::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_code' => $subjectCode,
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue === null ? null : (string) $oldValue,
            'new_value' => $newValue === null ? null : (string) $newValue,
            'is_sensitive' => $sensitive,
            'description' => $description,
            'actor_id' => $actor?->id,
        ]);
    }

    private function documentName(SupplierDocument $document): string
    {
        $document->loadMissing('type');

        return $document->type->name ?? $document->custom_name ?? 'hồ sơ';
    }

    private function formatTaxCode(?string $taxCode): ?string
    {
        $digits = $this->normalizeTaxCode($taxCode);
        if ($digits === null || strlen($digits) !== 10) {
            return $taxCode;
        }

        return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7, 3);
    }

    private function optionMap(array $labels): array
    {
        return collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    private function departmentOptions(?array $departmentScope): Collection
    {
        $query = Department::query()->where('is_active', true)->orderBy('name');
        if ($departmentScope !== null) {
            $query->whereIn('id', $departmentScope);
        }

        return $query->get(['id', 'name'])->values();
    }

    private function ownerOptions(?array $departmentScope): Collection
    {
        $query = User::query()->where('status', 'active')->orderBy('name');
        if ($departmentScope !== null) {
            $query->whereIn('department_id', $departmentScope);
        }

        return $query->get(['id', 'name', 'email', 'department_id'])->values();
    }
}
