<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByMatricule(string $matricule): ?User;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): User;

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getManagers(): Collection;

    /**
     * @return array<string>
     */
    public function getDepartements(): array;

    public function syncRoles(User $user, array $roleIds): void;

    public function countByStatut(string $statut): int;
}
