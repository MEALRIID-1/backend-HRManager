<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('employe_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('employe_id');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actives');
    }
};