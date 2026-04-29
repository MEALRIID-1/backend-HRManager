<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test de connexion utilisateur
$user = DB::table('users')->where('email', 'admin@hrmanager.com')->first();

if ($user) {
    echo "SUCCESS: User found - ID: {$user->id}, Email: {$user->email}" . PHP_EOL;
    
    // Test du mot de passe
    if (password_verify('password123', $user->password)) {
        echo "SUCCESS: Password verified!" . PHP_EOL;
    } else {
        echo "ERROR: Password mismatch" . PHP_EOL;
    }
} else {
    echo "ERROR: User not found" . PHP_EOL;
}

// Test des rôles
$roles = DB::table('roles')->pluck('name');
echo "Roles available: " . implode(', ', $roles->toArray()) . PHP_EOL;

// Test user_roles
$userRole = DB::table('user_roles')->where('user_id', $user->id ?? 0)->first();
if ($userRole) {
    $roleName = DB::table('roles')->where('id', $userRole->role_id)->value('name');
    echo "User role: {$roleName}" . PHP_EOL;
} else {
    echo "WARNING: User has no role assigned" . PHP_EOL;
}
