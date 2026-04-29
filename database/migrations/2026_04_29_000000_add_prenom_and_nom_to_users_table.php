<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'prenom')) {
                $table->string('prenom')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'nom')) {
                $table->string('nom')->nullable()->after('prenom');
            }
        });

        if (Schema::hasColumn('users', 'surname')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('surname');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nom')) {
                $table->dropColumn('nom');
            }

            if (Schema::hasColumn('users', 'prenom')) {
                $table->dropColumn('prenom');
            }
        });
    }
};