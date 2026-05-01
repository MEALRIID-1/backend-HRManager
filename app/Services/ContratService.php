<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContratService
{
    private FileUploadService $fileUploadService;
    private PDFGeneratorService $pdfGeneratorService;

    public function __construct(
        FileUploadService $fileUploadService,
        PDFGeneratorService $pdfGeneratorService
    ) {
        $this->fileUploadService = $fileUploadService;
        $this->pdfGeneratorService = $pdfGeneratorService;
    }

    /**
     * Créer un contrat avec validation métier (un seul contrat actif par employé).
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function create(array $data): Contrat
    {
        try {
            return DB::transaction(function () use ($data) {
                // Vérifier si l'employé a déjà un contrat actif
                $contratActif = $this->verifierContratActif($data['employe_id']);
                if ($contratActif && (!isset($data['forcer']) || !$data['forcer'])) {
                    throw ValidationException::withMessages([
                        'employe_id' => ['Cet employé a déjà un contrat actif. Veuillez le clôturer avant d\'en créer un nouveau.'],
                    ]);
                }

                // Si on force, on termine l'ancien contrat
                if ($contratActif && isset($data['forcer']) && $data['forcer']) {
                    $contratActif->update(['etat' => 'termine', 'date_fin' => now()]);
                }

                $contrat = Contrat::create([
                    'user_id' => $data['employe_id'],
                    'type' => $data['type'],
                    'date_debut' => $data['date_debut'],
                    'date_fin' => $data['date_fin'] ?? null,
                    'etat' => $data['etat'] ?? 'actif',
                    'salaire_base' => $data['salaire_base'],
                ]);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'entity_name' => 'contrat',
                    'new_value' => json_encode($contrat->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                // Notifier l'employé
                Notification::create([
                    'user_id' => $data['employe_id'],
                    'type' => 'nouveau_contrat',
                    'message' => "Un nouveau contrat de type {$data['type']} a été créé pour vous.",
                    'statut' => 'non_lue',
                    'date_envoi' => now(),
                ]);

                return $contrat;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modifier un contrat avec logs.
     *
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function update(Contrat $contrat, array $data): Contrat
    {
        try {
            return DB::transaction(function () use ($contrat, $data) {
                $oldValues = $contrat->toArray();

                $contrat->update([
                    'type' => $data['type'] ?? $contrat->type,
                    'date_debut' => $data['date_debut'] ?? $contrat->date_debut,
                    'date_fin' => $data['date_fin'] ?? $contrat->date_fin,
                    'etat' => $data['etat'] ?? $contrat->etat,
                    'salaire_base' => $data['salaire_base'] ?? $contrat->salaire_base,
                ]);

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'entity_name' => 'contrat',
                    'old_value' => json_encode($oldValues),
                    'new_value' => json_encode($contrat->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $contrat;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete d'un contrat.
     *
     * @throws \Exception
     */
    public function destroy(Contrat $contrat): bool
    {
        try {
            return DB::transaction(function () use ($contrat) {
                $oldValues = $contrat->toArray();
                $result = $contrat->delete();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'delete',
                    'entity_name' => 'contrat',
                    'old_value' => json_encode($oldValues),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer un contrat supprimé.
     *
     * @throws \Exception
     */
    public function restore(int $id): Contrat
    {
        try {
            return DB::transaction(function () use ($id) {
                $contrat = Contrat::withTrashed()->findOrFail($id);
                $contrat->restore();

                // Logger l'activité
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'restore',
                    'entity_name' => 'contrat',
                    'new_value' => json_encode($contrat->toArray()),
                    'timestamp' => now(),
                    'ip_address' => request()->ip(),
                ]);

                return $contrat;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la restauration du contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Liste paginée avec filtres.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = [], int $perPage = 15): CursorPaginator
    {
        $query = Contrat::with(['employe']);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['etat'])) {
            $query->where('etat', $filters['etat']);
        }

        if (isset($filters['employe_id'])) {
            $query->where('user_id', $filters['employe_id']);
        }

        if (isset($filters['date_debut'])) {
            $query->where('date_debut', '>=', $filters['date_debut']);
        }

        if (isset($filters['date_fin'])) {
            $query->where('date_fin', '<=', $filters['date_fin']);
        }

        if (isset($filters['expirant_bientot']) && $filters['expirant_bientot']) {
            $jours = $filters['jours'] ?? 30;
            $query->expirantBientot($jours);
        }

        return $query->orderBy('created_at', 'desc')->cursorPaginate($perPage);
    }

    /**
     * Liste des contrats en corbeille.
     */
    public function trashed(int $perPage = 15): CursorPaginator
    {
        return Contrat::onlyTrashed()->with(['employe'])->cursorPaginate($perPage);
    }

    /**
     * Vérifier si un employé a déjà un contrat actif.
     */
    public function verifierContratActif(int $employeId): ?Contrat
    {
        return Contrat::where('user_id', $employeId)
            ->where('etat', 'actif')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', now());
            })
            ->first();
    }

    /**
     * Retourner le contrat actif d'un employé.
     */
    public function getContratActif(int $employeId): ?Contrat
    {
        return Contrat::with('employe')
            ->where('user_id', $employeId)
            ->where('etat', 'actif')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', now());
            })
            ->first();
    }

    /**
     * Historique des contrats d'un employé.
     *
     * @return Collection<int, Contrat>
     */
    public function getHistoriqueContrats(int $employeId): Collection
    {
        return Contrat::with(['employe'])
            ->where('user_id', $employeId)
            ->orderBy('date_debut', 'desc')
            ->get();
    }

    /**
     * Générer un PDF du contrat sous forme de lettre officielle.
     *
     * @throws \Exception
     */
    public function genererPDFContrat(Contrat $contrat): string
    {
        try {
            // Utiliser le PDFGeneratorService pour générer le PDF
            $pdfUrl = $this->pdfGeneratorService->genererContrat($contrat);
            
            // Extraire le chemin relatif de l'URL
            $path = str_replace(Storage::disk('public')->url(''), '', $pdfUrl);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Télécharger un PDF existant.
     */
    public function telechargerPDF(string $path): ?string
    {
        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        return storage_path('app/public/' . $path);
    }

    /**
     * Récupérer un contrat avec toutes ses relations.
     */
    public function getContratWithDetails(int $id): ?Contrat
    {
        return Contrat::with(['employe'])->find($id);
    }
}
