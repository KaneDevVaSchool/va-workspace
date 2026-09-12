<?php

namespace Modules\Credential\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Credential\App\Models\CredentialProvider;
use Modules\Credential\App\Repositories\Contracts\CredentialProviderRepositoryInterface;

class CredentialProviderRepository implements CredentialProviderRepositoryInterface
{
    public function all(): Collection
    {
        return CredentialProvider::query()->orderBy('category')->orderBy('name')->get();
    }

    public function find(int $id): ?CredentialProvider
    {
        return CredentialProvider::query()->find($id);
    }

    public function create(array $data): CredentialProvider
    {
        return CredentialProvider::query()->create($data);
    }

    public function update(CredentialProvider $provider, array $data): CredentialProvider
    {
        $provider->update($data);

        return $provider;
    }

    public function delete(CredentialProvider $provider): bool
    {
        return (bool) $provider->delete();
    }

    public function isInUse(CredentialProvider $provider): bool
    {
        return $provider->credentials()->exists();
    }
}
