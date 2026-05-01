<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_paie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('periode');
            $table->date('date_emission');
            $table->decimal('salaire_brut', 10, 2);
            $table->decimal('salaire_net', 10, 2);
            $table->decimal('heures_travaillees', 8, 2)->nullable();
            $table->decimal('heures_supplementaires', 8, 2)->nullable();
            $table->decimal('montant_heures_sup', 10, 2)->nullable();
            $table->decimal('prime_anciennete', 10, 2)->nullable();
            $table->decimal('prime_productivite', 10, 2)->nullable();
            $table->decimal('prime_autres', 10, 2)->nullable();
            $table->decimal('total_cotisations', 10, 2)->nullable();
            $table->decimal('total_retenues', 10, 2)->nullable();
            $table->string('document_path')->nullable();
            $table->string('statut')->default('brouillon');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiches_paie');
    }
};
