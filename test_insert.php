<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $id = DB::table('users')->insertGetId([
        'name' => 'Test User',
        'email' => 'test987@hrmanager.com',
        'password' => bcrypt('password123'),
        'telephone' => '+33 6 12 34 56 78',
        'date_embauche' => '2020-01-15',
        'est_actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "SUCCESS: User inserted with ID: $id" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "TRACE: " . $e->getTraceAsString() . PHP_EOL;
}
