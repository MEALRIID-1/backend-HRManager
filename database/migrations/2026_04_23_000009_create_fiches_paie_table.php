<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_paie', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->integer('mois');
            $table->integer('annee');
            $table->decimal('montant', 10, 2);
            $table->string('etat');
            $table->timestamps();

            $table->foreign('employe_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('employe_id');
            $table->index('etat');
            $table->index(['mois', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiches_paie');
    }
};