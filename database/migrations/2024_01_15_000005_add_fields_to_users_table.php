<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule')->nullable()->unique()->after('email');
            $table->string('telephone')->nullable()->after('matricule');
            $table->text('adresse')->nullable()->after('telephone');
            $table->date('date_naissance')->nullable()->after('adresse');
            $table->string('poste')->nullable()->after('departement');
            $table->decimal('salaire_brut', 10, 2)->nullable()->after('poste');
            $table->string('statut')->default('actif')->after('photo_profil');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()->after('statut');
            $table->timestamp('dernier_changement_password')->nullable()->after('manager_id');
            $table->integer('tentatives_connexion')->default(0)->after('dernier_changement_password');
            $table->timestamp('verrouille_jusqua')->nullable()->after('tentatives_connexion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'matricule',
                'telephone',
                'adresse',
                'date_naissance',
                'poste',
                'salaire_brut',
                'statut',
                'manager_id',
                'dernier_changement_password',
                'tentatives_connexion',
                'verrouille_jusqua',
            ]);
        });
    }
};
