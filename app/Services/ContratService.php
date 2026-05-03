<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContratService
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly PDFGeneratorService $pdfGeneratorService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function create(array $data): Contrat
    {
        try {
            return DB::transaction(function () use ($data) {
                $userId = $data['user_id'] ?? $data['employe_id'] ?? null;
                
                if (!$userId) {
                    throw ValidationException::withMessages([
                        'user_id' => ['L\'identifiant de l\'employé est requis.'],
                    ]);
                }

                $contratActif = $this->verifierContratActif($userId);
                if ($contratActif && (!isset($data['forcer']) || !$data['forcer'])) {
                    throw ValidationException::withMessages([
                        'user_id' => ['Cet employé a déjà un contrat actif.'],
                    ]);
                }

                if ($contratActif && isset($data['forcer']) && $data['forcer']) {
                    $contratActif->update(['statut' => 'termine', 'date_fin' => now()]);
                }

                $contrat = Contrat::create([
                    'user_id'      => $userId,
                    'type'         => $data['type'],
                    'date_debut'   => $data['date_debut'],
                    'date_fin'     => $data['date_fin'] ?? null,
                    'statut'       => $data['statut'] ?? 'actif',
                    'salaire_brut' => $data['salaire_brut'] ?? $data['salaire_base'] ?? null,
                    'poste'        => $data['poste'] ?? null,
                    'departement'  => $data['departement'] ?? null,
                ]);

                $employe = User::find($userId);
                
                // Récupérer l'utilisateur connecté qui fait l'action
                $connectedUserId = auth()->id();
                $connectedUser = auth()->user();

                // ✅ CORRECTION : Appel avec les bons paramètres selon la signature de log()
                $this->activityLogService->log(
                    'create',                                                    // action
                    'contrats',                                                  // module
                    "Création du contrat {$data['type']} pour {$employe?->prenom} {$employe?->nom}", // description
                    $contrat->id,                                                // referenceId
                    'Contrat',                                                   // referenceType
                    $connectedUserId,                                            // userId (utilisateur connecté)
                    $connectedUser?->name ?? $connectedUser?->email ?? null,    // userName
                    request()->ip(),                                             // ipAddress
                    request()->userAgent()                                       // userAgent
                );

                Notification::create([
                    'user_id' => $userId,
                    'type'    => 'nouveau_contrat',
                    'titre'   => 'Nouveau contrat',
                    'message' => "Un nouveau contrat de type {$data['type']} a été créé pour vous.",
                    'lu'      => false,
                ]);

                return $contrat;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur création contrat: ' . $e->getMessage(), ['data' => $data]);
            throw $e;
        }
    }

    public function update(Contrat $contrat, array $data): Contrat
    {
        try {
            return DB::transaction(function () use ($contrat, $data) {
                $oldValues = [
                    'type' => $contrat->type, 
                    'date_debut' => $contrat->date_debut, 
                    'date_fin' => $contrat->date_fin, 
                    'salaire_brut' => $contrat->salaire_brut
                ];

                $contrat->update([
                    'type'         => $data['type'] ?? $contrat->type,
                    'date_debut'   => $data['date_debut'] ?? $contrat->date_debut,
                    'date_fin'     => $data['date_fin'] ?? $contrat->date_fin,
                    'statut'       => $data['statut'] ?? $contrat->statut,
                    'salaire_brut' => $data['salaire_brut'] ?? $data['salaire_base'] ?? $contrat->salaire_brut,
                    'poste'        => $data['poste'] ?? $contrat->poste,
                    'departement'  => $data['departement'] ?? $contrat->departement,
                ]);

                // Récupérer l'utilisateur connecté
                $connectedUserId = auth()->id();
                $connectedUser = auth()->user();

                $this->activityLogService->log(
                    'update',
                    'contrats',
                    "Modification du contrat #{$contrat->id}",
                    $contrat->id,
                    'Contrat',
                    $connectedUserId,
                    $connectedUser?->name ?? $connectedUser?->email ?? null,
                    request()->ip(),
                    request()->userAgent()
                );

                return $contrat;
            });
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    public function destroy(Contrat $contrat): bool
    {
        try {
            return DB::transaction(function () use ($contrat) {
                $result = $contrat->delete();

                // Récupérer l'utilisateur connecté
                $connectedUserId = auth()->id();
                $connectedUser = auth()->user();

                $this->activityLogService->log(
                    'delete',
                    'contrats',
                    "Suppression du contrat #{$contrat->id}",
                    $contrat->id,
                    'Contrat',
                    $connectedUserId,
                    $connectedUser?->name ?? $connectedUser?->email ?? null,
                    request()->ip(),
                    request()->userAgent()
                );

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur suppression contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    public function restore(int $id): Contrat
    {
        try {
            return DB::transaction(function () use ($id) {
                $contrat = Contrat::withTrashed()->findOrFail($id);
                $contrat->restore();

                // Récupérer l'utilisateur connecté
                $connectedUserId = auth()->id();
                $connectedUser = auth()->user();

                $this->activityLogService->log(
                    'restore',
                    'contrats',
                    "Restauration du contrat #{$contrat->id}",
                    $contrat->id,
                    'Contrat',
                    $connectedUserId,
                    $connectedUser?->name ?? $connectedUser?->email ?? null,
                    request()->ip(),
                    request()->userAgent()
                );

                return $contrat;
            });
        } catch (\Exception $e) {
            Log::error('Erreur restauration contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Contrat::with(['employe']);

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['statut']) && $filters['statut']) {
            $query->where('statut', $filters['statut']);
        }
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        } elseif (isset($filters['employe_id']) && $filters['employe_id']) {
            $query->where('user_id', $filters['employe_id']);
        }
        if (isset($filters['expirant_bientot']) && $filters['expirant_bientot']) {
            $query->expirantBientot($filters['jours'] ?? 30);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function trashed(int $perPage = 15): LengthAwarePaginator
    {
        return Contrat::onlyTrashed()->with(['employe'])->paginate($perPage);
    }

    public function verifierContratActif(int $userId): ?Contrat
    {
        return Contrat::where('user_id', $userId)
            ->where('statut', 'actif')
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()))
            ->first();
    }

    public function getContratActif(int $userId): ?Contrat
    {
        return Contrat::with('employe')
            ->where('user_id', $userId)
            ->where('statut', 'actif')
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()))
            ->first();
    }

    public function getHistoriqueContrats(int $userId): Collection
    {
        return Contrat::with(['employe'])
            ->where('user_id', $userId)
            ->orderBy('date_debut', 'desc')
            ->get();
    }

    public function genererPDFContrat(Contrat $contrat): string
    {
        try {
            $pdfUrl = $this->pdfGeneratorService->genererContrat($contrat);
            return str_replace(Storage::disk('public')->url(''), '', $pdfUrl);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    public function telechargerPDF(string $path): ?string
    {
        if (!Storage::disk('public')->exists($path)) return null;
        return storage_path('app/public/' . $path);
    }

    public function getContratWithDetails(int $id): ?Contrat
    {
        return Contrat::with(['employe'])->find($id);
    }
}