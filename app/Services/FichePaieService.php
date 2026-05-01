<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FichePaie;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FichePaieService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Calculer le net à payer avec heures sup et absences.
     *
     * @param float $salaire_base
     * @param float $heures_sup
     * @param int $absences
     * @return array<string, float>
     */
    public function calculerNetAPayer(float $salaire_base, float $heures_sup, int $absences): array
    {
        // Calcul heures supplémentaires (taux x1.5)
        $taux_horaire = $salaire_base / 151.67; // Base mensuelle légale
        $montant_heures_sup = $heures_sup * $taux_horaire * 1.5;

        // Calcul déduction absences (salaire_base / 22 jours * absences)
        $deduction_absences = $absences > 0 ? ($salaire_base / 22) * $absences : 0;

        // Calcul du brut
        $salaire_brut = $salaire_base + $montant_heures_sup - $deduction_absences;

        // Calcul des cotisations (estimation)
        $cotisations_salariales = $salaire_brut * 0.22; // ~22% de cotisations
        $net_a_payer = $salaire_brut - $cotisations_salariales;

        return [
            'salaire_base' => round($salaire_base, 2),
            'montant_heures_sup' => round($montant_heures_sup, 2),
            'deduction_absences' => round($deduction_absences, 2),
            'salaire_brut' => round($salaire_brut, 2),
            'cotisations_salariales' => round($cotisations_salariales, 2),
            'net_a_payer' => round($net_a_payer, 2),
        ];
    }

    /**
     * Vérifier si une fiche existe déjà pour cet employé/mois/année.
     *
     * @param int $employeId
     * @param string $mois
     * @param int $annee
     * @return bool
     */
    public function verifierFicheExistante(int $employeId, string $mois, int $annee): bool
    {
        return FichePaie::where('user_id', $employeId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->exists();
    }

    /**
     * Créer une fiche de paie.
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return FichePaie
     */
    public function create(User $user, array $data): FichePaie
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                // Calculs
                $calculs = $this->calculerNetAPayer(
                    $data['salaire_base'],
                    $data['heures_sup'] ?? 0,
                    $data['absences'] ?? 0
                );

                // Créer la fiche
                $fiche = FichePaie::create([
                    'user_id' => $data['employe_id'],
                    'mois' => $data['mois'],
                    'annee' => $data['annee'],
                    'salaire_base' => $calculs['salaire_base'],
                    'heures_sup' => $data['heures_sup'] ?? 0,
                    'absences' => $data['absences'] ?? 0,
                    'net_a_payer' => $calculs['net_a_payer'],
                    'statut' => $data['statut'] ?? 'brouillon',
                ]);

                // Log activité
                $this->activityLogService->log(
                    'create',
                    'fiche_paie',
                    null,
                    $fiche->toArray()
                );

                // Notifier l'employé
                $employe = User::find($data['employe_id']);
                if ($employe) {
                    $this->notificationService->notifier(
                        $employe->id,
                        'fiche_paie_generee',
                        "Votre fiche de paie de {$data['mois']} {$data['annee']} est disponible."
                    );
                }

                return $fiche;
            });
        } catch (\Exception $e) {
            Log::error('Erreur création fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour une fiche de paie.
     *
     * @param FichePaie $fiche
     * @param array<string, mixed> $data
     * @return FichePaie
     */
    public function update(FichePaie $fiche, array $data): FichePaie
    {
        try {
            return DB::transaction(function () use ($fiche, $data) {
                $oldValues = $fiche->toArray();

                // Recalculer si nécessaire
                if (isset($data['salaire_base']) || isset($data['heures_sup']) || isset($data['absences'])) {
                    $calculs = $this->calculerNetAPayer(
                        $data['salaire_base'] ?? $fiche->salaire_base,
                        $data['heures_sup'] ?? $fiche->heures_sup,
                        $data['absences'] ?? $fiche->absences
                    );
                    $data['net_a_payer'] = $calculs['net_a_payer'];
                }

                $fiche->update($data);

                // Log activité
                $this->activityLogService->log(
                    'update',
                    'fiche_paie',
                    $oldValues,
                    $fiche->fresh()->toArray()
                );

                return $fiche->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprimer une fiche de paie (soft delete).
     *
     * @param FichePaie $fiche
     * @return bool
     */
    public function delete(FichePaie $fiche): bool
    {
        try {
            return DB::transaction(function () use ($fiche) {
                $oldValues = $fiche->toArray();

                $result = $fiche->delete();

                // Log activité
                $this->activityLogService->log(
                    'delete',
                    'fiche_paie',
                    $oldValues,
                    null
                );

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Erreur suppression fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurer une fiche de paie.
     *
     * @param int $id
     * @return FichePaie
     */
    public function restore(int $id): FichePaie
    {
        try {
            return DB::transaction(function () use ($id) {
                $fiche = FichePaie::withTrashed()->findOrFail($id);
                $fiche->restore();

                // Log activité
                $this->activityLogService->log(
                    'restore',
                    'fiche_paie',
                    null,
                    $fiche->toArray()
                );

                return $fiche;
            });
        } catch (\Exception $e) {
            Log::error('Erreur restauration fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Générer le PDF d'une fiche de paie.
     *
     * @param FichePaie $fiche
     * @return string Chemin du fichier PDF
     */
    public function genererFichePDF(FichePaie $fiche): string
    {
        try {
            $fiche->load('employe');

            // Calculs détaillés
            $calculs = $this->calculerNetAPayer(
                $fiche->salaire_base,
                $fiche->heures_sup,
                $fiche->absences
            );

            // Générer PDF
            $pdf = Pdf::loadView('pdf.fiche-paie', [
                'fiche' => $fiche,
                'calculs' => $calculs,
                'employe' => $fiche->employe,
            ]);

            $filename = "fiche-paie-{$fiche->employe_id}-{$fiche->mois}-{$fiche->annee}.pdf";
            $path = "fiches-paie/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            // Mettre à jour le chemin du PDF
            $fiche->update(['pdf_url' => $path]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lister les fiches avec filtres.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FichePaie::with(['employe']);

        if (isset($filters['mois'])) {
            $query->where('mois', $filters['mois']);
        }

        if (isset($filters['annee'])) {
            $query->where('annee', $filters['annee']);
        }

        if (isset($filters['employe_id'])) {
            $query->where('user_id', $filters['employe_id']);
        }

        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Récupérer les fiches de l'employé connecté.
     *
     * @param int $employeId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getMesFiches(int $employeId, int $perPage = 15): LengthAwarePaginator
    {
        return FichePaie::where('user_id', $employeId)
            ->orderBy('annee', 'desc')
            ->orderBy('mois', 'desc')
            ->paginate($perPage);
    }

    /**
     * Récupérer les fiches supprimées (corbeille).
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTrashed(int $perPage = 15): LengthAwarePaginator
    {
        return FichePaie::onlyTrashed()
            ->with(['employe'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }
}
