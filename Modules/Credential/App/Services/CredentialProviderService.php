<?php

namespace Modules\Credential\App\Services;

use Illuminate\Support\Collection;
use Modules\Credential\App\Models\CredentialProvider;
use Modules\Credential\App\Repositories\Contracts\CredentialProviderRepositoryInterface;
use Modules\Identity\App\Services\ActivityLogService;

class CredentialProviderService
{
    public function __construct(
        private readonly CredentialProviderRepositoryInterface $providers,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /** @return Collection<int, CredentialProvider> */
    public function all(): Collection
    {
        return $this->providers->all();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): CredentialProvider
    {
        $provider = $this->providers->create($data);

        $this->activityLogs->record(
            'credential_provider.create',
            "Tạo nhà cung cấp \"{$provider->name}\"",
            subjectType: 'credential_provider',
            subjectId: $provider->id,
        );

        return $provider;
    }

    /** @param  array<string, mixed>  $data */
    public function update(CredentialProvider $provider, array $data): CredentialProvider
    {
        $updated = $this->providers->update($provider, $data);

        $this->activityLogs->record(
            'credential_provider.update',
            "Cập nhật nhà cung cấp \"{$updated->name}\"",
            subjectType: 'credential_provider',
            subjectId: $updated->id,
        );

        return $updated;
    }

    public function delete(CredentialProvider $provider): bool
    {
        if ($this->providers->isInUse($provider)) {
            throw new \RuntimeException('Nhà cung cấp đang được sử dụng bởi ít nhất một tài khoản, không thể xoá.');
        }

        $name = $provider->name;
        $id = $provider->id;
        $result = $this->providers->delete($provider);

        $this->activityLogs->record(
            'credential_provider.delete',
            "Xoá nhà cung cấp \"{$name}\"",
            subjectType: 'credential_provider',
            subjectId: $id,
        );

        return $result;
    }
}
