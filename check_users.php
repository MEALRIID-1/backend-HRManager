<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = DB::table('users')->get(['id', 'email', 'name']);
foreach ($users as $user) {
    echo "ID: {$user->id}, Email: {$user->email}, Name: {$user->name}" . PHP_EOL;
}
echo 'Total users: ' . count($users) . PHP_EOL;
