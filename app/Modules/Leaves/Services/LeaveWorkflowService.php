<?php

namespace App\Modules\Leaves\Services;

use App\Models\Conge;
use App\Models\User;
use App\Models\Validation;
use App\Notifications\LeaveApprovedNotification;
use App\Notifications\LeaveRejectedNotification;
use App\Notifications\LeaveSubmittedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Service de workflow pour la gestion des congés.
 * Workflow SIMULTANÉ : chaque niveau peut valider indépendamment,
 * sans attendre la validation du niveau précédent.
 */
class LeaveWorkflowService
{
    /**
     * États considérés comme finaux (aucune action possible).
     */
    private const ETATS_FINAUX = [
        Conge::ETAT_APPROUVE,
        Conge::ETAT_REFUSE_MANAGER,
        Conge::ETAT_REFUSE_RH,
        Conge::ETAT_REFUSE_DIRECTEUR,
        Conge::ETAT_ANNULE,
    ];

    /**
     * États en attente de validation (action possible).
     */
    private const ETATS_EN_ATTENTE = [
        Conge::ETAT_SOUMIS,
        Conge::ETAT_VALIDE_MANAGER,
        Conge::ETAT_VALIDE_RH,
    ];

    /**
     * Niveaux de validation par action.
     */
    private const NIVEAUX = [
        'approve_manager'  => Validation::NIVEAU_MANAGER,
        'reject_manager'   => Validation::NIVEAU_MANAGER,
        'approve_rh'       => Validation::NIVEAU_RH,
        'reject_rh'        => Validation::NIVEAU_RH,
        'approve_directeur'=> Validation::NIVEAU_DIRECTEUR,
        'reject_directeur' => Validation::NIVEAU_DIRECTEUR,
    ];

    /**
     * Vérifier si une action est possible (congé non final).
     */
    public function peutTransitionner(Conge $conge, string $action): bool
    {
        return !in_array($conge->etat, self::ETATS_FINAUX)
            && $conge->etat !== Conge::ETAT_BROUILLON
            && isset(self::NIVEAUX[$action]);
    }

    /**
     * Exécuter une validation (workflow simultané).
     * N'importe quel niveau peut valider dès que le congé est soumis et non final.
     */
    public function transitionner(Conge $conge, string $action, User $utilisateur, ?string $motif = null): Conge
    {
        try {
            DB::beginTransaction();

            // Vérifier que le congé n'est pas dans un état final
            if (in_array($conge->etat, self::ETATS_FINAUX)) {
                throw new \Exception("Cette demande a déjà été traitée (état : {$conge->etat}).");
            }

            if ($conge->etat === Conge::ETAT_BROUILLON) {
                throw new \Exception("Le congé doit d'abord être soumis avant d'être validé.");
            }

            // Vérifier les permissions de rôle (sans dépendance séquentielle)
            $niveau = self::NIVEAUX[$action] ?? null;
            if (!$this->verifierPermissionRole($utilisateur, $niveau)) {
                throw new \Exception("Vous n'avez pas les permissions pour effectuer cette action.");
            }

            // Déterminer le nouvel état
            $isApproval = str_starts_with($action, 'approve');
            $nouvelEtat = $this->determinerNouvelEtat($action, $isApproval);

            // Créer l'entrée de validation
            Validation::create([
                'conge_id'       => $conge->id,
                'validateur_id'  => $utilisateur->id,
                'niveau'         => $niveau,
                'action'         => $isApproval ? Validation::ACTION_APPROUVE : Validation::ACTION_REFUSE,
                'motif'          => $motif,
                'date_validation'=> now(),
            ]);

            // Mettre à jour l'état du congé
            $conge->update(['etat' => $nouvelEtat]);

            // Notifications à toutes les parties
            $this->notifierParties($conge, $isApproval ? 'approuve' : 'refuse', $utilisateur, $motif);

            DB::commit();

            return $conge->fresh(['validations.validateur', 'employe']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Super-validation : court-circuiter le workflow (Admin/Directeur uniquement).
     */
    public function superValider(Conge $conge, User $validateur, string $decision, string $commentaire, ?string $motifRefus = null): Conge
    {
        try {
            DB::beginTransaction();

            if (!$validateur->hasRole(['directeur', 'admin'])) {
                throw new \Exception("Seul un directeur ou administrateur peut effectuer une super-validation.");
            }

            if (in_array($conge->etat, self::ETATS_FINAUX)) {
                throw new \Exception("Cette demande a déjà été traitée (état : {$conge->etat}).");
            }

            if ($conge->etat === Conge::ETAT_BROUILLON) {
                throw new \Exception("Le congé doit être soumis avant toute validation.");
            }

            $isApproval = ($decision === 'approuve');
            $nouvelEtat = $isApproval ? Conge::ETAT_APPROUVE : Conge::ETAT_REFUSE_DIRECTEUR;
            $motif = $isApproval ? $commentaire : ($motifRefus ?? $commentaire);

            // Créer la validation super-admin
            Validation::create([
                'conge_id'       => $conge->id,
                'validateur_id'  => $validateur->id,
                'niveau'         => 99, // niveau spécial super-admin
                'action'         => $isApproval ? Validation::ACTION_APPROUVE : Validation::ACTION_REFUSE,
                'motif'          => $motif,
                'date_validation'=> now(),
            ]);

            // Mettre à jour l'état
            $conge->update(['etat' => $nouvelEtat]);

            // Logger dans activity_logs
            $this->loggerActivite($validateur, 'super_validation', $conge, [
                'etat' => $nouvelEtat,
                'decision' => $decision,
                'commentaire' => $commentaire,
            ]);

            // Notifier toute la chaîne
            $this->notifierParties($conge, $isApproval ? 'approuve' : 'refuse', $validateur, $motif);

            DB::commit();

            return $conge->fresh(['validations.validateur', 'employe']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Déterminer le nouvel état selon l'action.
     */
    private function determinerNouvelEtat(string $action, bool $isApproval): string
    {
        if ($isApproval) {
            return Conge::ETAT_APPROUVE;
        }

        $etatRefus = [
            'reject_manager'   => Conge::ETAT_REFUSE_MANAGER,
            'reject_rh'        => Conge::ETAT_REFUSE_RH,
            'reject_directeur' => Conge::ETAT_REFUSE_DIRECTEUR,
        ];

        return $etatRefus[$action] ?? Conge::ETAT_REFUSE_DIRECTEUR;
    }

    /**
     * Vérifier uniquement les permissions de rôle (sans contrainte séquentielle).
     */
    private function verifierPermissionRole(User $utilisateur, ?int $niveau): bool
    {
        switch ($niveau) {
            case Validation::NIVEAU_MANAGER:
                return $utilisateur->hasRole(['manager', 'rh', 'admin', 'directeur']);
            case Validation::NIVEAU_RH:
                return $utilisateur->hasRole(['rh', 'admin', 'directeur']);
            case Validation::NIVEAU_DIRECTEUR:
                return $utilisateur->hasRole(['directeur', 'admin']);
            default:
                return false;
        }
    }

    /**
     * Notifier toutes les parties concernées (employé + manager + RH).
     */
    private function notifierParties(Conge $conge, string $decision, User $validateur, ?string $motif): void
    {
        $employe = $conge->employe;
        if (!$employe) return;

        try {
            if ($decision === 'approuve') {
                $employe->notify(new LeaveApprovedNotification($conge));

                // Notifier aussi les RH et directeurs
                $rhEtAdmin = User::role(['rh', 'admin', 'directeur'])
                    ->where('id', '!=', $validateur->id)
                    ->get();
                Notification::send($rhEtAdmin, new LeaveApprovedNotification($conge));
            } else {
                $employe->notify(new LeaveRejectedNotification($conge, $validateur, $motif ?? ''));
            }
        } catch (\Exception $e) {
            // Ne pas bloquer le workflow si les notifications échouent
            \Log::warning("Échec notification congé #{$conge->id}: " . $e->getMessage());
        }
    }

    /**
     * Logger une activité dans les logs d'audit.
     */
    private function loggerActivite(User $user, string $action, Conge $conge, array $newValues): void
    {
        try {
            \DB::table('activity_logs')->insert([
                'user_id'     => $user->id,
                'action'      => $action,
                'entity_name' => 'conges',
                'entity_id'   => $conge->id,
                'new_value'   => json_encode($newValues),
                'timestamp'   => now(),
                'ip_address'  => request()->ip(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Exception $e) {
            \Log::warning("Échec audit log: " . $e->getMessage());
        }
    }

    /**
     * Soumettre un congé (brouillon -> soumis).
     */
    public function soumettre(Conge $conge, User $employe): Conge
    {
        try {
            DB::beginTransaction();

            if ($conge->etat !== Conge::ETAT_BROUILLON) {
                throw new \Exception("Le congé doit être en brouillon pour être soumis");
            }

            if ($conge->employe_id !== $employe->id) {
                throw new \Exception("Vous ne pouvez soumettre que vos propres congés");
            }

            $conge->soumettre();

            // Notifier le manager
            $manager = $employe->manager;
            if ($manager) {
                Notification::send($manager, new LeaveSubmittedNotification($conge));
            }

            DB::commit();

            return $conge->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Annuler un congé.
     */
    public function annuler(Conge $conge, User $utilisateur, string $motif): Conge
    {
        try {
            DB::beginTransaction();

            if (!$conge->peutEtreAnnule()) {
                throw new \Exception("Ce congé ne peut plus être annulé");
            }

            // Seul l'employé peut annuler son congé
            if ($conge->employe_id !== $utilisateur->id) {
                throw new \Exception("Seul l'employé peut annuler son congé");
            }

            $conge->annuler($motif, $utilisateur->id);

            DB::commit();

            return $conge->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approuver un congé selon le niveau du validateur.
     */
    public function approuver(Conge $conge, User $validateur, ?string $commentaire = null): Conge
    {
        $niveau = $this->determinerNiveau($validateur);

        $actions = [
            Validation::NIVEAU_MANAGER => 'approve_manager',
            Validation::NIVEAU_RH => 'approve_rh',
            Validation::NIVEAU_DIRECTEUR => 'approve_directeur',
        ];

        $action = $actions[$niveau] ?? null;

        if (!$action) {
            throw new \Exception("Niveau de validation inconnu");
        }

        return $this->transitionner($conge, $action, $validateur, $commentaire);
    }

    /**
     * Refuser un congé.
     */
    public function refuser(Conge $conge, User $validateur, string $motif): Conge
    {
        $niveau = $this->determinerNiveau($validateur);

        $actions = [
            Validation::NIVEAU_MANAGER => 'reject_manager',
            Validation::NIVEAU_RH => 'reject_rh',
            Validation::NIVEAU_DIRECTEUR => 'reject_directeur',
        ];

        $action = $actions[$niveau] ?? null;

        if (!$action) {
            throw new \Exception("Niveau de validation inconnu");
        }

        return $this->transitionner($conge, $action, $validateur, $motif);
    }

    /**
     * Vérifier si un utilisateur peut agir à un niveau donné.
     */
    private function peutAgir(Conge $conge, User $utilisateur, int $niveau, string $action): bool
    {
        // Vérifier que le congé est en attente de validation à ce niveau
        if (!$conge->enAttenteValidation($niveau)) {
            return false;
        }

        switch ($niveau) {
            case Validation::NIVEAU_MANAGER:
                // Le manager doit être le manager de l'employé
                return $conge->employe->manager_id === $utilisateur->id;

            case Validation::NIVEAU_RH:
                // RH ou Admin
                return $utilisateur->hasRole(['rh', 'admin']);

            case Validation::NIVEAU_DIRECTEUR:
                // Directeur ou Admin
                return $utilisateur->hasRole(['directeur', 'admin']);

            default:
                return false;
        }
    }

    /**
     * Déterminer le niveau d'un validateur selon son rôle.
     */
    private function determinerNiveau(User $utilisateur): ?int
    {
        if ($utilisateur->hasRole('directeur') || $utilisateur->hasRole('admin')) {
            return Validation::NIVEAU_DIRECTEUR;
        }

        if ($utilisateur->hasRole('rh')) {
            return Validation::NIVEAU_RH;
        }

        if ($utilisateur->hasRole('manager')) {
            return Validation::NIVEAU_MANAGER;
        }

        return null;
    }

    /**
     * Envoyer les notifications selon l'action.
     */
    private function envoyerNotifications(Conge $conge, string $action, User $validateur, ?string $motif): void
    {
        $employe = $conge->employe;

        switch ($action) {
            case 'approve_manager':
                // Notifier RH
                $rhUsers = User::role(['rh', 'admin'])->get();
                Notification::send($rhUsers, new LeaveSubmittedNotification($conge));
                break;

            case 'approve_rh':
                // Notifier Directeur
                $directeurs = User::role(['directeur', 'admin'])->get();
                Notification::send($directeurs, new LeaveSubmittedNotification($conge));
                break;

            case 'approve_directeur':
                // Notifier employé de l'approbation finale
                $employe->notify(new LeaveApprovedNotification($conge));
                break;

            case 'reject_manager':
            case 'reject_rh':
            case 'reject_directeur':
                // Notifier employé du refus
                $employe->notify(new LeaveRejectedNotification($conge, $validateur, $motif));
                break;
        }
    }

    /**
     * Calculer le solde de congés d'un employé.
     */
    public function calculerSolde(User $employe, string $type, ?int $annee = null): array
    {
        $annee = $annee ?? now()->year;

        // Solde total selon le type
        $soldes = [
            Conge::TYPE_CONGE_PAYE => 25,
            Conge::TYPE_RTT => 10,
            Conge::TYPE_CONGE_SANS_SOLDE => PHP_INT_MAX,
            Conge::TYPE_MALADIE => PHP_INT_MAX,
            Conge::TYPE_FORMATION => 5,
            Conge::TYPE_MATERNITE => 90,
            Conge::TYPE_PATERNITE => 11,
        ];

        $soldeTotal = $soldes[$type] ?? 0;

        // Calculer les jours pris (congés approuvés ou en cours de validation)
        $joursPris = Conge::parEmploye($employe->id)
            ->where('type', $type)
            ->whereYear('date_debut', $annee)
            ->whereNotIn('etat', [Conge::ETAT_REFUSE_MANAGER, Conge::ETAT_REFUSE_RH, Conge::ETAT_REFUSE_DIRECTEUR, Conge::ETAT_ANNULE])
            ->sum('nombre_jours');

        $soldeRestant = $type === Conge::TYPE_CONGE_SANS_SOLDE || $type === Conge::TYPE_MALADIE
            ? null
            : max(0, $soldeTotal - $joursPris);

        return [
            'type' => $type,
            'annee' => $annee,
            'solde_total' => $soldeTotal === PHP_INT_MAX ? null : $soldeTotal,
            'jours_pris' => (int) $joursPris,
            'jours_restants' => $soldeRestant,
        ];
    }

    /**
     * Vérifier le solde avant soumission.
     */
    public function verifierSolde(User $employe, string $type, int $joursDemandes): bool
    {
        if (in_array($type, [Conge::TYPE_CONGE_SANS_SOLDE, Conge::TYPE_MALADIE, Conge::TYPE_MATERNITE, Conge::TYPE_PATERNITE])) {
            return true;
        }

        $solde = $this->calculerSolde($employe, $type);

        return $solde['jours_restants'] >= $joursDemandes;
    }

    /**
     * Vérifier les chevauchements.
     */
    public function verifierChevauchement(int $employeId, Carbon $dateDebut, Carbon $dateFin, ?int $excludeId = null): bool
    {
        $query = Conge::parEmploye($employeId)
            ->chevauche($employeId, $dateDebut, $dateFin);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
