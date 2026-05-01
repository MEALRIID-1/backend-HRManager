<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Conge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CongeRepositoryInterface
{
    public function findById(int $id): ?Conge;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Conge;

    /**
     * @param array<string, mixed> $data
     */
    public function update(Conge $conge, array $data): Conge;

    public function delete(Conge $conge): bool;

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getEnAttente(): Collection;

    public function getByUserId(int $userId): Collection;

    public function getSoldeConges(int $userId, int $annee): array;

    public function countByStatut(string $statut): int;
}
