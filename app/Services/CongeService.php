<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conge;
use App\Models\Notification;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CongeService
{
    private const NIVEAUX_VALIDATION = ['N1', 'N2', 'N3'];

    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

   public function create(User $user, array $data): Conge
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $conge = Conge::create([
                    'user_id'     => $user->id,  // ✅ Correction : user_id au lieu de employe_id
                    'type'        => $data['type'],
                    'date_debut'  => $data['date_debut'],
                    'date_fin'    => $data['date_fin'],
                    'nombre_jours' => Carbon::parse($data['date_debut'])->diffInDays(Carbon::parse($data['date_fin'])) + 1,
                    'statut'      => 'en_attente',
                    'commentaire' => $data['commentaire'] ?? null,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'conges',
                    description: "Demande de congé créée par {$user->prenom} {$user->nom} du {$data['date_debut']} au {$data['date_fin']}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                    // ❌ Supprimer newValues (cause erreur dans ActivityLogService)
                    userId: $user->id,
                );

                $this->notifierValidateurs($conge);

                return $conge;
            });
        } catch (\Exception $e) {
            Log::error('Erreur création congé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(Conge $conge, array $data): Conge
    {
        try {
            return DB::transaction(function () use ($conge, $data) {
                if ($conge->statut !== 'en_attente') {
                    throw ValidationException::withMessages([
                        'conge' => ['Seules les demandes en attente peuvent être modifiées.'],
                    ]);
                }

                $conge->update([
                    'type'       => $data['type'] ?? $conge->type,
                    'date_debut' => $data['date_debut'] ?? $conge->date_debut,
                    'date_fin'   => $data['date_fin'] ?? $conge->date_fin,
                ]);

                $this->activityLogService->log(
                    action: 'update',
                    module: 'conges',
                    description: "Modification du congé #{$conge->id}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                    // ❌ Supprimer oldValues et newValues
                );

                return $conge;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour congé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function destroy(Conge $conge): bool
    {
        try {
            return DB::transaction(function () use ($conge) {
                if ($conge->statut === 'approuve') {
                    throw ValidationException::withMessages([
                        'conge' => ['Les congés approuvés ne peuvent pas être supprimés.'],
                    ]);
                }

                $result = $conge->delete();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'conges',
                    description: "Suppression du congé #{$conge->id}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                );

                return $result;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur suppression congé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function restore(int $id): Conge
    {
        try {
            return DB::transaction(function () use ($id) {
                $conge = Conge::withTrashed()->findOrFail($id);
                $conge->restore();

                $this->activityLogService->log(
                    action: 'restore',
                    module: 'conges',
                    description: "Restauration du congé #{$conge->id}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                );

                return $conge;
            });
        } catch (\Exception $e) {
            Log::error('Erreur restauration congé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function valider(Conge $conge, User $validateur, string $decision, string $motif, string $niveau): void
    {
        try {
            DB::transaction(function () use ($conge, $validateur, $decision, $motif, $niveau) {
                if (!in_array($conge->statut, ['en_attente', 'partiellement_valide'])) {
                    throw ValidationException::withMessages([
                        'conge' => ['Cette demande a déjà été traitée définitivement.'],
                    ]);
                }

                $validationExistante = Validation::where('conge_id', $conge->id)->where('niveau', $niveau)->first();
                if ($validationExistante) {
                    throw ValidationException::withMessages([
                        'niveau' => ['Ce niveau de validation a déjà été effectué.'],
                    ]);
                }

                $niveauValue = match($niveau) {
                    'N1' => 1,
                    'N2' => 2,
                    'N3' => 3,
                    default => 1,
                };
                Validation::create([
                    'conge_id'        => $conge->id,
                    'validateur_id'   => $validateur->id,
                    'niveau'          => $niveauValue,
                    'decision'        => $decision,
                    'statut'          => $decision, 
                    'commentaire'     => $motif,
                    'date_validation' => now(),
                ]);

                if ($decision === 'refuse') {
                    $conge->update(['statut' => 'refuse', 'motif' => $motif, 'motif_refus' => $motif]);
                } else {
                    $this->calculerStatutFinal($conge->id);
                }

               $this->activityLogService->log(
                    action: 'validation_' . $decision,
                    module: 'conges',
                    description: "Congé #{$conge->id} {$decision} au niveau {$niveau} par {$validateur->prenom} {$validateur->nom}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                    // ❌ Supprimer newValues
                    userId: $validateur->id,
                );

                Notification::create([
                    'user_id' => $conge->employe_id,
                    'type'    => $decision === 'approuve' ? 'conge_valide' : 'conge_refuse',
                    'titre'   => $decision === 'approuve' ? 'Congé validé' : 'Congé refusé',
                    'message' => "Votre demande de congé a été {$decision}e (niveau {$niveau})",
                    'lu'      => false,
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur validation congé: ' . $e->getMessage());
            throw $e;
        }
    }

    public function superValidation(Conge $conge, User $admin, string $motif): void
    {
        try {
            DB::transaction(function () use ($conge, $admin, $motif) {
                if (!$admin->roles->contains('nom', 'Administrateur')) {
                    throw ValidationException::withMessages([
                        'user' => ['Seul un administrateur peut effectuer une super validation.'],
                    ]);
                }

                Validation::where('conge_id', $conge->id)->delete();

                foreach (self::NIVEAUX_VALIDATION as $niveau) {
                    Validation::create([
                        'conge_id'        => $conge->id,
                        'validateur_id'   => $admin->id,
                        'niveau'          => $niveau,
                        'decision'        => 'approuve',
                        'commentaire'     => "Super validation: {$motif}",
                        'date_validation' => now(),
                    ]);
                }

                $conge->update(['statut' => 'approuve']);

                $this->activityLogService->log(
                    action: 'super_validation',
                    module: 'conges',
                    description: "Super validation du congé #{$conge->id} par {$admin->prenom} {$admin->nom}",
                    referenceId: $conge->id,
                    referenceType: 'Conge',
                    userId: $admin->id,
                );

                Notification::create([
                    'user_id' => $conge->employe_id,
                    'type'    => 'conge_super_valide',
                    'titre'   => 'Congé approuvé',
                    'message' => 'Votre demande de congé a été approuvée par super validation administrative.',
                    'lu'      => false,
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur super validation: ' . $e->getMessage());
            throw $e;
        }
    }

    public function calculerStatutFinal(int $congeId): void
    {
        $conge = Conge::findOrFail($congeId);
        $niveauxValides = Validation::where('conge_id', $congeId)->where('decision', 'approuve')->pluck('niveau')->unique()->toArray();
        $tousNiveauxValides = count(array_diff(self::NIVEAUX_VALIDATION, $niveauxValides)) === 0;
        $conge->update(['statut' => $tousNiveauxValides ? 'approuve' : 'partiellement_valide']);
    }

    public function getSoldeConges(int $userId): array
    {
        $totalAccorde = 25;
        $joursPris = Conge::byEmploye($userId)->where('statut', 'approuve')->whereYear('date_debut', now()->year)->count();
        $joursEnAttente = Conge::byEmploye($userId)->whereIn('statut', ['en_attente', 'partiellement_valide'])->whereYear('date_debut', now()->year)->count();

        return [
            'total_accorde'    => $totalAccorde,
            'jours_pris'       => $joursPris,
            'jours_en_attente' => $joursEnAttente,
            'solde_restant'    => $totalAccorde - $joursPris,
        ];
    }

   public function list(array $filters = [], ?User $user = null, int $perPage = 15): LengthAwarePaginator
{
    $query = Conge::with(['employe', 'validations.validateur']);

    if ($user && $user->roles) {
        $userRoles = $user->roles ?? collect();
        
        // Récupère les noms et slugs des rôles
        $rolesNoms = $userRoles->pluck('nom')->toArray();
        $rolesSlugs = $userRoles->pluck('slug')->toArray();
        
        // ✅ CORRECTION : Vérifie avec les vrais noms
        $isAdmin = in_array('Administrateur', $rolesNoms) || in_array('admin', $rolesSlugs);
        $isRH = in_array('Ressources Humaines', $rolesNoms) || in_array('rh', $rolesSlugs);
        $isManager = in_array('Manager', $rolesNoms) || in_array('manager', $rolesSlugs);
        
        if ($isAdmin || $isRH) {
            // voit tout - ne rien faire
        } elseif ($isManager) {
            $query->whereHas('employe', fn($q) => $q->where('departement', $user->departement));
        } else {
            $query->byEmploye($user->id);
        }
    }

    if (isset($filters['statut']) && $filters['statut']) {
        $query->byStatut($filters['statut']);
    }
    if (isset($filters['type']) && $filters['type']) {
        $query->where('type', $filters['type']);
    }
    if (isset($filters['employe_id']) && $filters['employe_id']) {
        $query->byEmploye($filters['employe_id']);
    }
    if (isset($filters['date_debut']) && isset($filters['date_fin'])) {
        $query->byPeriode(\Carbon\Carbon::parse($filters['date_debut']), \Carbon\Carbon::parse($filters['date_fin']));
    }

    return $query->orderBy('created_at', 'desc')->paginate($perPage);
}



    public function trashed(int $perPage = 15): LengthAwarePaginator
    {
        return Conge::onlyTrashed()->with(['employe'])->paginate($perPage);
    }

    public function notifierValidateurs(Conge $conge): void
    {
        $roles = ['manager' => 'N1', 'rh' => 'N2', 'Administrateur' => 'N3'];
        $types = ['manager' => 'validation_requise_n1', 'rh' => 'validation_requise_n2', 'Administrateur' => 'validation_requise_n3'];

        foreach ($roles as $role => $niveau) {
            $users = User::whereHas('roles', fn($q) => $q->where('nom', $role))->get();
            foreach ($users as $u) {
                Notification::create([
                    'user_id' => $u->id,
                    'type'    => $types[$role],
                    'titre'   => 'Validation requise',
                    'message' => "Nouvelle demande de congé à valider ({$niveau}) de {$conge->employe->nom} {$conge->employe->prenom}",
                    'lu'      => false,
                ]);
            }
        }
    }

    public function peutValider(User $user, Conge $conge): bool
{
    // Récupère les noms et slugs des rôles
    $rolesNoms = $user->roles->pluck('nom')->toArray();
    $rolesSlugs = $user->roles->pluck('slug')->toArray();
    
    // Debug - à enlever après
    \Log::info('peutValider - Rôles:', [
        'noms' => $rolesNoms,
        'slugs' => $rolesSlugs
    ]);
    
    // ✅ Vérifie Admin (par nom ou slug)
    if (in_array('Administrateur', $rolesNoms) || in_array('admin', $rolesSlugs)) {
        return true;
    }
    
    // ✅ Vérifie RH (par nom "Ressources Humaines" OU slug "rh")
    if (in_array('Ressources Humaines', $rolesNoms) || in_array('rh', $rolesSlugs)) {
        return true;
    }
    
    // ✅ Vérifie Manager (par nom ou slug)
    if (in_array('Manager', $rolesNoms) || in_array('manager', $rolesSlugs)) {
        return $conge->employe->departement === $user->departement;
    }
    
    return false;
}

public function getNiveauValidation(User $user): ?string
{
    $rolesNoms = $user->roles->pluck('nom')->toArray();
    $rolesSlugs = $user->roles->pluck('slug')->toArray();
    
    // Vérifie Manager
    if (in_array('Manager', $rolesNoms) || in_array('manager', $rolesSlugs)) {
        return 'N1';
    }
    
    // Vérifie RH
    if (in_array('Ressources Humaines', $rolesNoms) || in_array('rh', $rolesSlugs)) {
        return 'N2';
    }
    
    // Vérifie Admin
    if (in_array('Administrateur', $rolesNoms) || in_array('admin', $rolesSlugs)) {
        return 'N3';
    }
    
    return null;
}

    public function getCongeWithDetails(int $id): ?Conge
    {
        return Conge::with(['employe', 'validations.validateur'])->find($id);
    }

    public function getMyConges(int $userId): Collection
    {
        return Conge::byEmploye($userId)->with(['validations.validateur'])->orderBy('created_at', 'desc')->get();
    }
}