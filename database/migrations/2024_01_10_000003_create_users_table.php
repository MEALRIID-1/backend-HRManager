<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
            $table->softDeletes();
            $table->string('email', 255)->nullable(false)->unique();
            $table->string('mot_de_passe', 255)->nullable(false);
            $table->string('nom', 100)->nullable(false);
            $table->string('prenom', 100)->nullable(false);
            $table->string('departement', 100)->nullable();
            $table->string('photo_profil', 255)->nullable();
            $table->date('date_embauche')->nullable();
            $table->string('iban', 34)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
