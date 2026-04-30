<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('validations', function(Blueprint $table) {
    $table->string('statut')->nullable()->change();
    $table->string('commentaire')->nullable()->change();
});

echo "Done!\n";