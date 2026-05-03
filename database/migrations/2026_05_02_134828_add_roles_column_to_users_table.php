<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ✅ Option 1: colonne JSON pour stocker plusieurs rôles
            $table->json('roles')->nullable()->after('departement');
            
            // ✅ Option 2: colonne simple string pour un seul rôle (si un seul rôle par utilisateur)
            // $table->string('role')->nullable()->after('departement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
            // $table->dropColumn('role'); // Si vous avez choisi l'option 2
        });
    }
};