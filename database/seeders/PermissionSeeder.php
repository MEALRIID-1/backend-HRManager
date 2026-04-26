<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::create(['name' => 'view employees']);
        Permission::create(['name' => 'create employees']);
        Permission::create(['name' => 'edit employees']);
        Permission::create(['name' => 'delete employees']);
        Permission::create(['name' => 'view contracts']);
        Permission::create(['name' => 'create contracts']);
        Permission::create(['name' => 'edit contracts']);
        Permission::create(['name' => 'view leaves']);
        Permission::create(['name' => 'approve leaves']);
        Permission::create(['name' => 'view payroll']);
        Permission::create(['name' => 'generate reports']);
    }
}