<?php

namespace Modules\Credential\App\Services;

use Illuminate\Support\Collection;
use Modules\Credential\App\Models\CredentialProvider;
use Modules\Credential\App\Repositories\Contracts\CredentialProviderRepositoryInterface;

class CredentialProviderService
{
    public function __construct(
        private readonly CredentialProviderRepositoryInterface $providers,
    ) {}

    /** @return Collection<int, CredentialProvider> */
    public function all(): Collection
    {
        return $this->providers->all();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): CredentialProvider
    {
        return $this->providers->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(CredentialProvider $provider, array $data): CredentialProvider
    {
        return $this->providers->update($provider, $data);
    }

    public function delete(CredentialProvider $provider): bool
    {
        if ($this->providers->isInUse($provider)) {
            throw new \RuntimeException('Nhà cung cấp đang được sử dụng bởi ít nhất một tài khoản, không thể xoá.');
        }

        return $this->providers->delete($provider);
    }
}
