<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Checking users in database ===\n\n";

$users = User::all();

foreach ($users as $user) {
    echo "Email: {$user->email}\n";
    echo "Name: {$user->nom} {$user->prenom}\n";
    echo "Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
    echo "Password hash: " . substr($user->mot_de_passe, 0, 20) . "...\n";
    echo "Role: " . $user->roles->pluck('name')->implode(', ') . "\n";
    echo "---\n";
}

echo "\n=== Available roles in database ===\n\n";

$roles = \App\Models\Role::all();
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}, Description: {$role->description}\n";
}

echo "\n=== Updating passwords and assigning roles ===\n\n";

// Update passwords and assign roles for test users
$testUsers = [
    'admin@hrmanager.com' => ['password' => 'Admin@2024!', 'role' => 'Administrateur'],
    'rh@hrmanager.com' => ['password' => 'Rh@2024!', 'role' => 'Ressources Humaines'],
    'manager@hrmanager.com' => ['password' => 'Manager@2024!', 'role' => 'Manager'],
    'employe@hrmanager.com' => ['password' => 'Employe@2024!', 'role' => 'Employé'],
];

foreach ($testUsers as $email => $data) {
    $user = User::where('email', $email)->first();
    if ($user) {
        $user->mot_de_passe = bcrypt($data['password']);
        $user->is_active = true;
        $user->save();

        // Assign role
        $role = \App\Models\Role::where('name', $data['role'])->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
            echo "Updated password and assigned role '{$data['role']}' for {$email}\n";
        } else {
            echo "Updated password for {$email}, but role '{$data['role']}' not found\n";
        }
    } else {
        echo "User not found: {$email}\n";
    }
}

echo "\n=== Testing password verification ===\n\n";

// Test password verification
$testUser = User::where('email', 'admin@hrmanager.com')->first();
if ($testUser) {
    $testPassword = 'Admin@2024!';
    $isValid = Hash::check($testPassword, $testUser->mot_de_passe);
    echo "Password verification for admin@hrmanager.com: " . ($isValid ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Password hash: {$testUser->mot_de_passe}\n";
    echo "Test password: {$testPassword}\n";
}

echo "\n=== Done ===\n";
