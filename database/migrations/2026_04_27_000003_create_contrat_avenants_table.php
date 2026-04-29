<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrat_avenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrat_id');
            $table->string('type_modification'); // salaire, type, date_fin, fonction, autre
            $table->json('ancienne_valeur')->nullable();
            $table->json('nouvelle_valeur')->nullable();
            $table->text('motif')->nullable();
            $table->date('date_effet');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('contrat_id')
                ->references('id')
                ->on('contrats')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('contrat_id');
            $table->index('type_modification');
            $table->index('date_effet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_avenants');
    }
};
