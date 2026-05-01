<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Conge;
use App\Models\Notification;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CongeService
{
    // Niveaux de validation requis
    private const NIVEAUX_VALIDATION = ['N1', 'N2', 'N3']; // Manager, RH, Admin

    /**
     * Créer une demande de congé.
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function create(User $user, array $data): Conge
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $conge = Conge::create([
                    'employe_id' => $user->id,
                    'type' => $data['type'],
                    'date_debut' => $data['date_debut'],
                    'date_fin' => $data['date_fin'],
                    'etat' => 'en_attente',
                    'motif_refus' => null,
                ]);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'create',
                    'entity_name' => 'conge',
                    'new_value' => json_encode($conge->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                // Notifier les validateurs
                $this->notifierValidateurs($conge);

                return $conge;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du congé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modifier une demande de congé (seulement si en_attente).
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function update(Conge $conge, array $data): Conge
    {
        try {
            return DB::transaction(function () use ($conge, $data) {
                if ($conge->etat !== 'en_attente') {
                    throw ValidationException::withMessages([
                        'conge' => ['Seules les demandes en attente peuvent être modifiées.'],
                    ]);
                }

                $oldValues = $conge->toArray();

                $conge->update([
                    'type' => $data['type'] ?? $conge->type,
                    'date_debut' => $data['date_debut'] ?? $conge->date_debut,
                    'date_fin' => $data['date_fin'] ?? $conge->date_fin,
                ]);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'entity_name' => 'conge',
                    'old_value' => json_encode($oldValues),
                    'new_value' => json_encode($conge->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $conge;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du congé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete d'une demande de congé.
     *
     * @throws \Exception
     */
    public function destroy(Conge $conge): bool
    {
        try {
            return DB::transaction(function () use ($conge) {
                if ($conge->etat === 'approuve') {
                    throw ValidationException::withMessages([
                        'conge' => ['Les congés approuvés ne peuvent pas être supprimés.'],
                    ]);
                }

                $oldValues = $conge->toArray();
                $result = $conge->delete();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'delete',
                    'entity_name' => 'conge',
                    'old_value' => json_encode($oldValues),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $result;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du congé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer un congé supprimé.
     *
     * @throws \Exception
     */
    public function restore(int $id): Conge
    {
        try {
            return DB::transaction(function () use ($id) {
                $conge = Conge::withTrashed()->findOrFail($id);
                $conge->restore();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'restore',
                    'entity_name' => 'conge',
                    'new_value' => json_encode($conge->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $conge;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la restauration du congé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Liste paginée avec filtres et scope par rôle.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], ?User $user = null, int $perPage = 15): CursorPaginator
    {
        $query = Conge::with(['employe', 'validations.validateur']);

        // Scope par rôle
        if ($user) {
            if ($user->roles->contains('nom', 'admin')) {
                // Admin voit tout
            } elseif ($user->roles->contains('nom', 'RH')) {
                // RH voit tout
            } elseif ($user->roles->contains('nom', 'manager')) {
                // Manager voit uniquement son département
                $query->whereHas('employe', function ($q) use ($user) {
                    $q->where('departement', $user->departement);
                });
            } else {
                // Employé ne voit que ses propres congés
                $query->byEmploye($user->id);
            }
        }

        // Filtres avec scopes
        if (isset($filters['statut'])) {
            $query->byStatut($filters['statut']);
        }
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['date_debut']) && isset($filters['date_fin'])) {
            $query->byPeriode(
                \Carbon\Carbon::parse($filters['date_debut']),
                \Carbon\Carbon::parse($filters['date_fin'])
            );
        }
        if (isset($filters['employe_id'])) {
            $query->byEmploye($filters['employe_id']);
        }

        return $query->orderBy('created_at', 'desc')->cursorPaginate($perPage);
    }

    /**
     * Liste des congés en corbeille.
     */
    public function trashed(int $perPage = 15): CursorPaginator
    {
        return Conge::onlyTrashed()->with(['employe'])->cursorPaginate($perPage);
    }

    /**
     * Valider ou refuser une demande de congé (workflow simultané).
     *
     * @throws \Exception
     */
    public function valider(Conge $conge, User $validateur, string $decision, string $motif, string $niveau): void
    {
        try {
            DB::transaction(function () use ($conge, $validateur, $decision, $motif, $niveau) {
                // Vérifier si le congé est toujours en attente ou partiellement validé
                if (!in_array($conge->etat, ['en_attente', 'partiellement_valide'])) {
                    throw ValidationException::withMessages([
                        'conge' => ['Cette demande a déjà été traitée définitivement.'],
                    ]);
                }

                // Vérifier si ce niveau n'a pas déjà été validé
                $validationExistante = Validation::where('conge_id', $conge->id)
                    ->where('niveau', $niveau)
                    ->first();

                if ($validationExistante) {
                    throw ValidationException::withMessages([
                        'niveau' => ['Ce niveau de validation a déjà été effectué.'],
                    ]);
                }

                // Créer la validation
                Validation::create([
                    'conge_id' => $conge->id,
                    'validateur_id' => $validateur->id,
                    'niveau' => $niveau,
                    'decision' => $decision,
                    'commentaire' => $motif,
                    'date_validation' => now(),
                ]);

                // Si refusé, le congé est immédiatement refusé
                if ($decision === 'refuse') {
                    $conge->update([
                        'etat' => 'refuse',
                        'motif' => $motif,
                        'motif_refus' => $motif,
                    ]);
                } else {
                    // Recalculer le statut final
                    $this->calculerStatutFinal($conge->id);
                }

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => $validateur->id,
                    'action' => 'validation_' . $decision,
                    'entity_name' => 'conge',
                    'new_value' => json_encode([
                        'conge_id' => $conge->id,
                        'niveau' => $niveau,
                        'decision' => $decision,
                    ]),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                // Notifier l'employé
                Notification::create([
                    'user_id' => $conge->employe_id,
                    'type' => $decision === 'approuve' ? 'conge_valide' : 'conge_refuse',
                    'message' => "Votre demande de congé a été {$decision}e (niveau {$niveau})",
                    'statut' => 'non_lue',
                    'date_envoi' => now(),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la validation du congé: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Super validation (admin seulement) - valide tous les niveaux d'un coup.
     *
     * @throws \Exception
     */
    public function superValidation(Conge $conge, User $admin, string $motif): void
    {
        try {
            DB::transaction(function () use ($conge, $admin, $motif) {
                // Vérifier que c'est un admin
                if (!$admin->roles->contains('nom', 'admin')) {
                    throw ValidationException::withMessages([
                        'user' => ['Seul un administrateur peut effectuer une super validation.'],
                    ]);
                }

                // Supprimer les validations existantes
                Validation::where('conge_id', $conge->id)->delete();

                // Créer les validations pour tous les niveaux
                foreach (self::NIVEAUX_VALIDATION as $niveau) {
                    Validation::create([
                        'conge_id' => $conge->id,
                        'validateur_id' => $admin->id,
                        'niveau' => $niveau,
                        'decision' => 'approuve',
                        'commentaire' => "Super validation: {$motif}",
                        'date_validation' => now(),
                    ]);
                }

                // Approuver le congé immédiatement
                $conge->update(['etat' => 'approuve']);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => $admin->id,
                    'action' => 'super_validation',
                    'entity_name' => 'conge',
                    'new_value' => json_encode(['conge_id' => $conge->id]),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                // Notifier l'employé
                Notification::create([
                    'user_id' => $conge->employe_id,
                    'type' => 'conge_super_valide',
                    'message' => 'Votre demande de congé a été approuvée par super validation administrative.',
                    'statut' => 'non_lue',
                    'date_envoi' => now(),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la super validation: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recalculer le statut final d'un congé après une validation.
     */
    public function calculerStatutFinal(int $congeId): void
    {
        $conge = Conge::findOrFail($congeId);

        $validations = Validation::where('conge_id', $congeId)
            ->where('decision', 'approuve')
            ->pluck('niveau')
            ->toArray();

        // Vérifier si tous les niveaux ont été validés
        $niveauxValides = array_unique($validations);
        $tousNiveauxValides = count(array_diff(self::NIVEAUX_VALIDATION, $niveauxValides)) === 0;

        if ($tousNiveauxValides) {
            $conge->update(['etat' => 'approuve']);
        } else {
            $conge->update(['etat' => 'partiellement_valide']);
        }
    }

    /**
     * Calculer le solde de congés d'un utilisateur.
     *
     * @return array<string, mixed>
     */
    public function getSoldeConges(int $userId): array
    {
        $totalAccorde = 25; // Jours par an

        $joursPris = Conge::byEmploye($userId)
            ->where('etat', 'approuve')
            ->whereYear('date_debut', now()->year)
            ->count();

        $joursEnAttente = Conge::byEmploye($userId)
            ->whereIn('etat', ['en_attente', 'partiellement_valide'])
            ->whereYear('date_debut', now()->year)
            ->count();

        return [
            'total_accorde' => $totalAccorde,
            'jours_pris' => $joursPris,
            'jours_en_attente' => $joursEnAttente,
            'solde_restant' => $totalAccorde - $joursPris,
        ];
    }

    /**
     * Notifier les validateurs selon leur niveau.
     */
    public function notifierValidateurs(Conge $conge): void
    {
        // Notifier les managers (N1)
        $managers = User::whereHas('roles', function ($q) {
            $q->where('nom', 'manager');
        })->get();

        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'validation_requise_n1',
                'message' => "Nouvelle demande de congé à valider (N1) de {$conge->employe->nom} {$conge->employe->prenom}",
                'statut' => 'non_lue',
                'date_envoi' => now(),
            ]);
        }

        // Notifier la RH (N2)
        $rhUsers = User::whereHas('roles', function ($q) {
            $q->where('nom', 'RH');
        })->get();

        foreach ($rhUsers as $rh) {
            Notification::create([
                'user_id' => $rh->id,
                'type' => 'validation_requise_n2',
                'message' => "Nouvelle demande de congé à valider (N2) de {$conge->employe->nom} {$conge->employe->prenom}",
                'statut' => 'non_lue',
                'date_envoi' => now(),
            ]);
        }

        // Notifier les admins (N3)
        $admins = User::whereHas('roles', function ($q) {
            $q->where('nom', 'admin');
        })->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'validation_requise_n3',
                'message' => "Nouvelle demande de congé à valider (N3) de {$conge->employe->nom} {$conge->employe->prenom}",
                'statut' => 'non_lue',
                'date_envoi' => now(),
            ]);
        }
    }

    /**
     * Vérifier si un utilisateur peut valider ce congé.
     */
    public function peutValider(User $user, Conge $conge): bool
    {
        // Admin peut toujours valider
        if ($user->roles->contains('nom', 'admin')) {
            return true;
        }

        // RH peut valider
        if ($user->roles->contains('nom', 'RH')) {
            return true;
        }

        // Manager peut valider si l'employé est dans son département
        if ($user->roles->contains('nom', 'manager')) {
            return $conge->employe->departement === $user->departement;
        }

        return false;
    }

    /**
     * Déterminer le niveau de validation d'un utilisateur.
     */
    public function getNiveauValidation(User $user): ?string
    {
        if ($user->roles->contains('nom', 'manager')) {
            return 'N1';
        }
        if ($user->roles->contains('nom', 'RH')) {
            return 'N2';
        }
        if ($user->roles->contains('nom', 'admin')) {
            return 'N3';
        }
        return null;
    }

    /**
     * Récupérer un congé avec son historique de validations.
     */
    public function getCongeWithDetails(int $id): ?Conge
    {
        return Conge::with(['employe', 'validations.validateur'])->find($id);
    }

    /**
     * Liste des congés de l'employé connecté.
     *
     * @return Collection<int, Conge>
     */
    public function getMyConges(int $userId): Collection
    {
        return Conge::byEmploye($userId)
            ->with(['validations.validateur'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
