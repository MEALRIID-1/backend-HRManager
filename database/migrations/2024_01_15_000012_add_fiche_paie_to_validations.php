<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('validations', function (Blueprint $table) {
            $table->foreignId('fiche_paie_id')->nullable()->constrained('fiches_paie')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('validations', function (Blueprint $table) {
            $table->dropForeign(['fiche_paie_id']);
            $table->dropColumn('fiche_paie_id');
        });
    }
};
