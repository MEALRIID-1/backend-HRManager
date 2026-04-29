<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->text('motif_terminaison')->nullable()->after('etat');
            $table->date('date_terminaison')->nullable()->after('motif_terminaison');
            $table->boolean('est_en_periode_essai')->default(false)->after('date_terminaison');
            $table->integer('duree_periode_essai_jours')->nullable()->after('est_en_periode_essai');
            $table->unsignedBigInteger('created_by')->nullable()->after('duree_periode_essai_jours');
            $table->string('fonction')->nullable()->after('created_by');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by']);
            $table->dropColumn([
                'motif_terminaison',
                'date_terminaison',
                'est_en_periode_essai',
                'duree_periode_essai_jours',
                'created_by',
                'fonction',
            ]);
        });
    }
};
