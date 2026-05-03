<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validations', function (Blueprint $table) {
            // ✅ Ajouter la colonne decision
            $table->enum('decision', ['approuve', 'refuse'])->after('niveau');
        });
    }

    public function down(): void
    {
        Schema::table('validations', function (Blueprint $table) {
            $table->dropColumn('decision');
        });
    }
};