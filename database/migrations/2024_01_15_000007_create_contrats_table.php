<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('salaire_brut', 10, 2)->nullable();
            $table->string('taux_horaire')->nullable();
            $table->integer('horaires_hebdomadaires')->nullable();
            $table->string('poste')->nullable();
            $table->string('departement')->nullable();
            $table->string('statut')->default('actif');
            $table->string('document_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
