<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->string('niveau');
            $table->unsignedBigInteger('conge_id');
            $table->unsignedBigInteger('valideur_id');
            $table->string('statut');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('conge_id')->references('id')->on('conges')->onDelete('cascade');
            $table->foreign('valideur_id')->references('id')->on('users')->onDelete('restrict');

            $table->index('niveau');
            $table->index('conge_id');
            $table->index('valideur_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};