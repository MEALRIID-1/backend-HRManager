<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Add softDeletes to notifications table if not exists
        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'deleted_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add softDeletes to conges table if not exists
        if (Schema::hasTable('conges') && !Schema::hasColumn('conges', 'deleted_at')) {
            Schema::table('conges', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add softDeletes to contrats table if not exists
        if (Schema::hasTable('contrats') && !Schema::hasColumn('contrats', 'deleted_at')) {
            Schema::table('contrats', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add softDeletes to employes table if not exists
        if (Schema::hasTable('employes') && !Schema::hasColumn('employes', 'deleted_at')) {
            Schema::table('employes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'deleted_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('conges') && Schema::hasColumn('conges', 'deleted_at')) {
            Schema::table('conges', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('contrats') && Schema::hasColumn('contrats', 'deleted_at')) {
            Schema::table('contrats', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('employes') && Schema::hasColumn('employes', 'deleted_at')) {
            Schema::table('employes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
