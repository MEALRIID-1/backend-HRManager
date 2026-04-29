<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour ajouter les champs employés à la table users
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Photo de profil
            $table->string('photo')->nullable()->after('password');

            // Informations de contact
            $table->string('telephone', 20)->nullable()->after('photo');
            $table->text('adresse')->nullable()->after('telephone');

            // Informations professionnelles
            $table->date('date_embauche')->nullable()->after('adresse');
            $table->boolean('est_actif')->default(true)->after('date_embauche');

            // Relations
            $table->unsignedBigInteger('departement_id')->nullable()->after('est_actif');
            $table->unsignedBigInteger('manager_id')->nullable()->after('departement_id');

            // Clés étrangères
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index('departement_id');
            $table->index('manager_id');
            $table->index('est_actif');
            $table->index('date_embauche');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['departement_id']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['est_actif']);
            $table->dropIndex(['date_embauche']);

            $table->dropColumn([
                'photo',
                'telephone',
                'adresse',
                'date_embauche',
                'est_actif',
                'departement_id',
                'manager_id',
            ]);
        });
    }
};
