<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contrat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('validateur_id')->constrained('users');
            $table->string('type');
            $table->string('statut');
            $table->text('commentaire')->nullable();
            $table->timestamp('date_validation');
            $table->integer('niveau')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};
