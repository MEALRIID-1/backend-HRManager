<?php

namespace App\Http\Controllers;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contrôleur pour les endpoints /me/*
 * Accès aux données de l'utilisateur connecté uniquement
 */
class MeController extends Controller
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * GET /api/me
     * Retourne le profil complet de l'utilisateur connecté
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Eager load relation manager
        $user->load(['manager']);

        // Charger les rôles et permissions si disponibles
        if (\Illuminate\Support\Facades\Schema::hasTable('roles') && \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
            $user->loadMissing('roles.permissions');
        }

        $roles = [];
        $permissions = [];
        if ($user->relationLoaded('roles')) {
            $roles = $user->roles->pluck('name')->values()->all();
            $permissions = $user->roles->flatMap->permissions->pluck('name')->unique()->values()->all();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'dateNaissance' => $user->date_naissance,
                'adresse' => $user->adresse,
                'avatar' => $user->photo_profil,
                'statut' => $user->statut,
                'dateEmbauche' => $user->date_embauche,
                'rib' => $user->iban ? $this->maskIBAN($user->iban) : null,
                'departementId' => $user->departement_id,
                'manager' => $user->manager ? [
                    'id' => $user->manager->id,
                    'nom' => $user->manager->nom,
                    'prenom' => $user->manager->prenom,
                ] : null,
                'roles' => $roles,
                'permissions' => $permissions,
            ]
        ]);
    }

    /**
     * POST /api/me/photo
     * Upload de la photo de profil
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        try {
            // Supprimer l'ancienne photo si existe
            if ($user->photo_profil) {
                Storage::disk('public')->delete($user->photo_profil);
            }

            // Stocker la nouvelle photo
            $path = $request->file('photo')->store('photos', 'public');
            
            $user->photo_profil = $path;
            $user->save();

            // Invalider le cache
            $this->cacheService->invalidateEmployees();

            return response()->json([
                'success' => true,
                'data' => [
                    'photo_url' => Storage::disk('public')->url($path),
                ],
                'message' => 'Photo mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Échec de l\'upload : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/me/password
     * Changement de mot de passe
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'mot_de_passe_actuel' => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|regex:/[A-Z]/|regex:/[0-9]/',
            'confirmation' => 'required|string|same:nouveau_mot_de_passe',
        ]);

        $user = $request->user();

        // Vérifier le mot de passe actuel
        if (!Hash::check($request->mot_de_passe_actuel, $user->password)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Le mot de passe actuel est incorrect'],
            ]);
        }

        $user->password = Hash::make($request->nouveau_mot_de_passe);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * GET /api/me/contrats
     * Liste tous les contrats de l'employé
     */
    public function getContrats(Request $request)
    {
        $user = $request->user();

        $contrats = Contrat::where('employe_id', $user->id)
            ->orderBy('date_debut', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $contrats->map(function ($contrat) {
                return [
                    'id' => $contrat->id,
                    'reference' => $contrat->reference ?? 'CONT-' . $contrat->id,
                    'type' => $contrat->type,
                    'statut' => $contrat->etat,
                    'dateDebut' => $contrat->date_debut,
                    'dateFin' => $contrat->date_fin,
                    'salaireBase' => $contrat->salaire,
                ];
            }),
            'meta' => [
                'current_page' => $contrats->currentPage(),
                'last_page' => $contrats->lastPage(),
                'per_page' => $contrats->perPage(),
                'total' => $contrats->total(),
                'totalPages' => $contrats->lastPage(),
            ]
        ]);
    }

    /**
     * GET /api/me/contrats/actif
     * Retourne le contrat actif uniquement
     */
    public function getContratActif(Request $request)
    {
        $user = $request->user();

        $contrat = Contrat::where('employe_id', $user->id)
            ->where(function ($q) {
                $q->where('etat', 'en_cours')
                  ->orWhere(function ($sq) {
                      $sq->where('date_debut', '<=', now())
                         ->where(function ($sqq) {
                             $sqq->whereNull('date_fin')
                                ->orWhere('date_fin', '>=', now());
                         });
                  });
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$contrat) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun contrat actif trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contrat->id,
                'type' => $contrat->type,
                'etat' => $contrat->etat,
                'date_debut' => $contrat->date_debut,
                'date_fin' => $contrat->date_fin,
                'salaire_base' => $contrat->salaire,
            ]
        ]);
    }

    /**
     * GET /api/me/conges
     * Liste les congés de l'employé
     */
    public function getConges(Request $request)
    {
        $user = $request->user();

        $query = Conge::where('employe_id', $user->id);

        // Filtre : exclure les annulées
        if ($request->boolean('exclure_annulees')) {
            $query->whereNull('deleted_at')
                  ->where('etat', '!=', 'annule');
        }

        // Filtre : annulées seulement (corbeille)
        if ($request->boolean('annulees_seulement')) {
            $query->where(function ($q) {
                $q->whereNotNull('deleted_at')
                  ->orWhere('etat', 'annule');
            });
        }

        // Limit pour dashboard
        if ($request->has('limit')) {
            $query->limit($request->integer('limit'));
        }

        $conges = $query->with(['validations.validateur'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conges->map(function ($conge) {
                return [
                    'id' => $conge->id,
                    'type' => $conge->type,
                    'statut' => $conge->etat,
                    'dateDebut' => $conge->date_debut,
                    'dateFin' => $conge->date_fin,
                    'nombreJours' => $conge->nombre_jours,
                    'motif' => $conge->motif,
                    'motifRefus' => $conge->motif_refus,
                    'validations' => $conge->validations->map(function ($v) {
                        return [
                            'niveau' => $v->niveau,
                            'decision' => $v->decision,
                            'commentaire' => $v->commentaire,
                            'dateValidation' => $v->date_validation,
                            'validateur' => $v->validateur ? [
                                'id' => $v->validateur->id,
                                'nom' => $v->validateur->nom,
                                'prenom' => $v->validateur->prenom,
                            ] : null,
                        ];
                    }),
                    'deletedAt' => $conge->deleted_at,
                ];
            })
        ]);
    }

    /**
     * GET /api/me/conges/solde
     * Retourne le solde de congés
     */
    public function getSoldeConges(Request $request)
    {
        $user = $request->user();

        // Calculer les soldes depuis les congés approuvés
        $congesApprouves = Conge::where('employe_id', $user->id)
            ->where('etat', 'approuve')
            ->whereNull('deleted_at')
            ->get();

        $joursAnnuelsPris = $congesApprouves
            ->where('type', 'conge_annuel')
            ->sum('nombre_jours');
        
        $joursMaladiePris = $congesApprouves
            ->where('type', 'maladie')
            ->sum('nombre_jours');

        // Valeurs par défaut - à adapter selon votre logique métier
        $soldeAnnuelTotal = 25; // Jours par an
        $soldeMaladieTotal = 5;

        return response()->json([
            'success' => true,
            'data' => [
                'conge_annuel' => max(0, $soldeAnnuelTotal - $joursAnnuelsPris),
                'maladie' => max(0, $soldeMaladieTotal - $joursMaladiePris),
                'maternite' => 90, // À calculer selon votre logique
                'paternite' => 3,
                'sans_solde' => 'Illimité',
                'exceptionnel' => 10,
            ]
        ]);
    }

    /**
     * GET /api/me/conges/corbeille/count
     * Compte les congés annulés (pour le badge)
     */
    public function getCorbeilleCount(Request $request)
    {
        $user = $request->user();

        $count = Conge::where('employe_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('deleted_at')
                  ->orWhere('etat', 'annule');
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ]
        ]);
    }

    /**
     * POST /api/me/conges
     * Créer une nouvelle demande de congé
     */
    public function createConge(Request $request)
    {
        $request->validate([
            'type' => 'required|in:conge_annuel,maladie,maternite,paternite,sans_solde,exceptionnel',
            'date_debut' => 'required|date|after_or_equal:today',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $user = $request->user();

        // Vérifier chevauchement avec congés existants
        $chevauchement = Conge::where('employe_id', $user->id)
            ->whereNull('deleted_at')
            ->where('etat', '!=', 'annule')
            ->where(function ($q) use ($request) {
                $q->whereBetween('date_debut', [$request->date_debut, $request->date_fin])
                  ->orWhereBetween('date_fin', [$request->date_debut, $request->date_fin])
                  ->orWhere(function ($sq) use ($request) {
                      $sq->where('date_debut', '<=', $request->date_debut)
                         ->where('date_fin', '>=', $request->date_fin);
                  });
            })
            ->exists();

        if ($chevauchement) {
            return response()->json([
                'success' => false,
                'message' => 'Chevauchement avec une demande existante'
            ], 422);
        }

        // Calculer nombre de jours
        $dateDebut = new \DateTime($request->date_debut);
        $dateFin = new \DateTime($request->date_fin);
        $nbJours = $dateDebut->diff($dateFin)->days + 1;

        $conge = Conge::create([
            'employe_id' => $user->id,
            'type' => $request->type,
            'date_debut' => $request->date_debut,
            'date_fin' => $request->date_fin,
            'nombre_jours' => $nbJours,
            'etat' => 'brouillon', // ou 'soumis' selon votre workflow
        ]);

        return response()->json([
            'success' => true,
            'data' => $conge,
            'message' => 'Demande de congé créée'
        ], 201);
    }

    /**
     * DELETE /api/me/conges/{id}
     * Soft delete (annuler) une demande
     */
    public function cancelConge(Request $request, $id)
    {
        $user = $request->user();

        $conge = Conge::where('id', $id)
            ->where('employe_id', $user->id)
            ->firstOrFail();

        // Vérifier que le congé peut être annulé
        if (!in_array($conge->etat, ['brouillon', 'soumis', 'en_attente_n1'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande ne peut plus être annulée'
            ], 422);
        }

        $conge->etat = 'annule';
        $conge->save();
        $conge->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Demande annulée'
        ]);
    }

    /**
     * PUT /api/me/conges/{id}/restaurer
     * Restaurer une demande annulée
     */
    public function restoreConge(Request $request, $id)
    {
        $user = $request->user();

        $conge = Conge::withTrashed()
            ->where('id', $id)
            ->where('employe_id', $user->id)
            ->firstOrFail();

        if (!$conge->trashed() && $conge->etat !== 'annule') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande n\'est pas annulée'
            ], 422);
        }

        $conge->restore();
        $conge->etat = 'brouillon';
        $conge->save();

        return response()->json([
            'success' => true,
            'data' => $conge,
            'message' => 'Demande restaurée'
        ]);
    }

    /**
     * Masquer l'IBAN pour l'affichage
     */
    private function maskIBAN(string $iban): string
    {
        $cleaned = preg_replace('/\s+/', '', $iban);
        if (strlen($cleaned) < 8) return $iban;
        
        return substr($cleaned, 0, 4) . ' **** **** **** **** **** ' . substr($cleaned, -3);
    }
}
