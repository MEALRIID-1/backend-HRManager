<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $rh = User::create([
            'name' => 'RH User',
            'email' => 'rh@example.com',
            'password' => Hash::make('password'),
        ]);
        $rh->assignRole('rh');

        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole('manager');

        $employe = User::create([
            'name' => 'Employe Demo',
            'email' => 'employe@example.com',
            'password' => Hash::make('password'),
        ]);
        $employe->assignRole('employe');
    }
}