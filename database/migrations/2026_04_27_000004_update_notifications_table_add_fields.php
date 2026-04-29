<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour étendre la table notifications avec des champs supplémentaires
 * pour le système de notifications amélioré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Champs additionnels pour les notifications enrichies
            $table->string('title')->nullable()->after('type');
            $table->text('message')->nullable()->after('title');
            $table->string('action_url')->nullable()->after('data');
            $table->string('action_text')->nullable()->after('action_url');
            $table->string('icon')->nullable()->after('action_text');
            $table->string('priority')->default('normal')->after('icon'); // low, normal, high, urgent
            
            // Support polymorphic pour lier à d'autres modèles
            $table->string('notifiable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('notifiable_id')->nullable()->after('notifiable_type');
            
            // Index pour les recherches rapides
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['notifiable_type', 'notifiable_id']);
            $table->dropIndex(['created_at']);
            
            $table->dropColumn([
                'title',
                'message',
                'action_url',
                'action_text',
                'icon',
                'priority',
                'notifiable_type',
                'notifiable_id',
            ]);
        });
    }
};
