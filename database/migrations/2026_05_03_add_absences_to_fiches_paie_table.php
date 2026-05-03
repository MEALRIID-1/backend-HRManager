<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('fiches_paie', function (Blueprint $table) {
            if (!Schema::hasColumn('fiches_paie', 'absences')) {
                $table->decimal('absences', 8, 2)->default(0)->after('heures_supplementaires');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiches_paie', function (Blueprint $table) {
            if (Schema::hasColumn('fiches_paie', 'absences')) {
                $table->dropColumn('absences');
            }
        });
    }
};
