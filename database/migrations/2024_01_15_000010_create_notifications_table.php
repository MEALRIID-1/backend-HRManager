<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('titre');
            $table->text('message')->nullable();
            $table->string('action_url')->nullable();
            $table->string('icone')->nullable();
            $table->boolean('lu')->default(false);
            $table->timestamp('date_lecture')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
