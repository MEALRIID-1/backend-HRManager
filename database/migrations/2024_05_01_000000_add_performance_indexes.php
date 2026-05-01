<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index sur users
        Schema::table('users', function (Blueprint $table) {
            // Index composite sur email et deleted_at pour les recherches d'utilisateurs actifs
            $table->index(['email', 'deleted_at'], 'idx_users_email_deleted');
            // Index sur departement pour les filtres par département
            $table->index('departement', 'idx_users_departement');
            // Index sur is_active pour filtrer les utilisateurs actifs
            $table->index('is_active', 'idx_users_is_active');
            // Index composite sur departement et is_active
            $table->index(['departement', 'is_active'], 'idx_users_dept_active');
        });

        // Index sur conges
        Schema::table('conges', function (Blueprint $table) {
            // Index composite sur user_id et statut pour les recherches fréquentes
            $table->index(['user_id', 'statut'], 'idx_conges_user_statut');
            // Index composite sur date_debut et date_fin pour les recherches par période
            $table->index(['date_debut', 'date_fin'], 'idx_conges_dates');
            // Index sur statut pour filtrer par statut
            $table->index('statut', 'idx_conges_statut');
        });

        // Index sur contrats
        Schema::table('contrats', function (Blueprint $table) {
            // Index sur user_id
            $table->index('user_id', 'idx_contrats_user');
            // Index sur statut pour filtrer les contrats actifs
            $table->index('statut', 'idx_contrats_statut');
            // Index sur date_fin pour trouver les contrats expirants
            $table->index('date_fin', 'idx_contrats_date_fin');
            // Index composite sur statut et date_fin
            $table->index(['statut', 'date_fin'], 'idx_contrats_statut_datefin');
        });

        // Index sur activity_logs
        Schema::table('activity_logs', function (Blueprint $table) {
            // Index composite sur user_id et created_at pour les recherches chronologiques
            $table->index(['user_id', 'created_at'], 'idx_logs_user_timestamp');
            // Index sur reference_type pour filtrer par type d'entité
            $table->index('reference_type', 'idx_logs_reference_type');
            // Index sur action pour filtrer par type d'action
            $table->index('action', 'idx_logs_action');
        });

        // Index sur notifications
        Schema::table('notifications', function (Blueprint $table) {
            // Index sur user_id
            $table->index('user_id', 'idx_notif_user');
            // Index sur lu pour compter les non lus
            $table->index('lu', 'idx_notif_lu');
            // Index composite sur user_id et lu
            $table->index(['user_id', 'lu'], 'idx_notif_user_lu');
        });

        // Index sur fiches_paie
        Schema::table('fiches_paie', function (Blueprint $table) {
            // Index sur user_id
            $table->index('user_id', 'idx_fiches_user');
            // Index sur periode pour les recherches par période
            $table->index('periode', 'idx_fiches_periode');
            // Index sur statut
            $table->index('statut', 'idx_fiches_statut');
        });

        // Index sur validations
        Schema::table('validations', function (Blueprint $table) {
            // Index sur conge_id
            $table->index('conge_id', 'idx_valid_conge');
            // Index sur validateur_id
            $table->index('validateur_id', 'idx_valid_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Suppression des index users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email_deleted');
            $table->dropIndex('idx_users_departement');
            $table->dropIndex('idx_users_is_active');
            $table->dropIndex('idx_users_dept_active');
        });

        // Suppression des index conges
        Schema::table('conges', function (Blueprint $table) {
            $table->dropIndex('idx_conges_user_statut');
            $table->dropIndex('idx_conges_dates');
            $table->dropIndex('idx_conges_statut');
        });

        // Suppression des index contrats
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropIndex('idx_contrats_user');
            $table->dropIndex('idx_contrats_statut');
            $table->dropIndex('idx_contrats_date_fin');
            $table->dropIndex('idx_contrats_statut_datefin');
        });

        // Suppression des index activity_logs
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_logs_user_timestamp');
            $table->dropIndex('idx_logs_reference_type');
            $table->dropIndex('idx_logs_action');
        });

        // Suppression des index notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notif_user');
            $table->dropIndex('idx_notif_lu');
            $table->dropIndex('idx_notif_user_lu');
        });

        // Suppression des index fiches_paie
        Schema::table('fiches_paie', function (Blueprint $table) {
            $table->dropIndex('idx_fiches_user');
            $table->dropIndex('idx_fiches_periode');
            $table->dropIndex('idx_fiches_statut');
        });

        // Suppression des index validations
        Schema::table('validations', function (Blueprint $table) {
            $table->dropIndex('idx_valid_conge');
            $table->dropIndex('idx_valid_user');
        });
    }
};
