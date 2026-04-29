<?php
require 'vendor/autoload.php';

echo "Testing Spatie...\n";

try {
    $role = new \Spatie\Permission\Models\Role();
    echo "SUCCESS: Role instance created\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Testing Sanctum...\n";
try {
    // Test Sanctum trait
    echo "HasApiTokens exists: " . (trait_exists('Laravel\Sanctum\HasApiTokens') ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
