<?php

namespace Modules\Credential\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Credential\App\Models\CredentialProvider;

interface CredentialProviderRepositoryInterface
{
    /** @return Collection<int, CredentialProvider> */
    public function all(): Collection;

    public function find(int $id): ?CredentialProvider;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): CredentialProvider;

    /** @param  array<string, mixed>  $data */
    public function update(CredentialProvider $provider, array $data): CredentialProvider;

    public function delete(CredentialProvider $provider): bool;

    public function isInUse(CredentialProvider $provider): bool;
}
