<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByMatricule(string $matricule): ?User
    {
        return User::where('matricule', $matricule)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query();

        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (isset($filters['departement'])) {
            $query->where('departement', $filters['departement']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function getManagers(): Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->where('slug', 'manager')
                ->orWhere('slug', 'admin')
                ->orWhere('slug', 'rh');
        })->get();
    }

    public function getDepartements(): array
    {
        return User::distinct()
            ->whereNotNull('departement')
            ->pluck('departement')
            ->toArray();
    }

    public function syncRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
    }

    public function countByStatut(string $statut): int
    {
        return User::where('statut', $statut)->count();
    }
}
