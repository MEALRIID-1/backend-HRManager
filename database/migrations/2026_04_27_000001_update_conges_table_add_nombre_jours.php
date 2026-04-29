<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter le champ nombre_jours à la table conges
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conges', function (Blueprint $table) {
            $table->integer('nombre_jours')->nullable()->after('date_fin');
        });
    }

    public function down(): void
    {
        Schema::table('conges', function (Blueprint $table) {
            $table->dropColumn('nombre_jours');
        });
    }
};
