<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->string('type');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('raison')->nullable();
            $table->string('etat');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employe_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('employe_id');
            $table->index('etat');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges');
    }
};