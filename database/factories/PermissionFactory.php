<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = fake()->randomElement(['employes', 'conges', 'contrats', 'fiches-paie', 'rapports', 'parametres']);
        $action = fake()->randomElement(['view', 'create', 'update', 'delete', 'validate']);
        $slug = "{$module}.{$action}";

        return [
            'nom' => ucfirst($action) . ' ' . ucfirst($module),
            'slug' => $slug,
            'module' => $module,
        ];
    }
}
