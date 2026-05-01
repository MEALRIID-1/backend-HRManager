<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('conges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('nombre_jours');
            $table->string('statut')->default('en_attente');
            $table->integer('niveau_validation')->default(0);
            $table->text('motif')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('remplacant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges');
    }
};
