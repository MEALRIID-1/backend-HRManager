<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FichePaie;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FichePaieService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Calculer le net à payer avec heures sup et absences.
     */
    public function calculerNetAPayer(float $salaire_base, float $heures_sup, int $absences): array
    {
        $taux_horaire = $salaire_base / 151.67;
        $montant_heures_sup = $heures_sup * $taux_horaire * 1.5;
        $deduction_absences = $absences > 0 ? ($salaire_base / 22) * $absences : 0;
        $salaire_brut = $salaire_base + $montant_heures_sup - $deduction_absences;
        $cotisations_salariales = $salaire_brut * 0.22;
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
     * Vérifier si une fiche existe déjà pour cet employé/période.
     */
    public function verifierFicheExistante(int $userId, string $periode): bool
    {
        return FichePaie::where('user_id', $userId)
            ->where('periode', $periode)
            ->exists();
    }

    /**
     * Convertir un nom de mois en numéro.
     */
    private function convertirMoisEnNumero(string $mois): int
    {
        $mapping = [
            'Janvier' => 1, 'Février' => 2, 'Mars' => 3, 'Avril' => 4,
            'Mai' => 5, 'Juin' => 6, 'Juillet' => 7, 'Août' => 8,
            'Septembre' => 9, 'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
        ];
        
        return $mapping[$mois] ?? 1;
    }

    /**
     * Créer une fiche de paie.
     */
    public function create(array $data): FichePaie
    {
        try {
            return DB::transaction(function () use ($data) {
                $userId = $data['user_id'] ?? $data['employe_id'] ?? null;
                
                if (!$userId) {
                    throw ValidationException::withMessages([
                        'user_id' => ['L\'identifiant de l\'employé est requis.'],
                    ]);
                }
                
                $mois = is_numeric($data['mois']) ? (int)$data['mois'] : $this->convertirMoisEnNumero($data['mois']);
                $periode = $data['periode'] ?? ($data['annee'] . '-' . sprintf('%02d', $mois));
                
                $existante = $this->verifierFicheExistante($userId, $periode);
                if ($existante) {
                    throw ValidationException::withMessages([
                        'periode' => ['Une fiche de paie existe déjà pour cette période.'],
                    ]);
                }

                $calculs = $this->calculerNetAPayer(
                    $data['salaire_base'] ?? 0,
                    $data['heures_sup'] ?? 0,
                    $data['absences'] ?? 0
                );

                $fiche = FichePaie::create([
                    'user_id' => $userId,
                    'periode' => $periode,
                    'date_emission' => now(),
                    'salaire_brut' => $calculs['salaire_brut'],
                    'salaire_net' => $calculs['net_a_payer'],
                    'heures_travaillees' => 151.67 - (($data['absences'] ?? 0) * 7),
                    'heures_supplementaires' => $data['heures_sup'] ?? 0,
                    'absences' => $data['absences'] ?? 0,
                    'montant_heures_sup' => $calculs['montant_heures_sup'],
                    'prime_anciennete' => $data['prime_anciennete'] ?? 0,
                    'prime_productivite' => $data['prime_productivite'] ?? 0,
                    'prime_autres' => $data['prime_autres'] ?? 0,
                    'total_cotisations' => $calculs['cotisations_salariales'],
                    'statut' => $data['statut'] ?? 'generee',
                ]);

                $employe = User::find($userId);
                if ($employe) {
                    $this->notificationService->notifier(
                        $employe->id,
                        'fiche_paie_generee',
                        "Votre fiche de paie pour la période {$periode} est disponible."
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
     * Lister les fiches avec filtres.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FichePaie::with(['employe']);

        if (isset($filters['mois']) && $filters['mois']) {
            $annee = $filters['annee'] ?? date('Y');
            $periode = $annee . '-' . sprintf('%02d', $filters['mois']);
            $query->where('periode', 'like', $periode . '%');
        } elseif (isset($filters['annee']) && $filters['annee']) {
            $query->where('periode', 'like', $filters['annee'] . '-%');
        }

        if (isset($filters['employe_id']) && $filters['employe_id']) {
            $query->where('user_id', $filters['employe_id']);
        }

        if (isset($filters['statut']) && $filters['statut']) {
            $query->where('statut', $filters['statut']);
        }

        return $query->orderBy('periode', 'desc')->paginate($perPage);
    }

    /**
     * Récupérer les fiches de l'employé connecté.
     */
    public function getMesFiches(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return FichePaie::where('user_id', $userId)
            ->with(['employe'])
            ->orderBy('periode', 'desc')
            ->paginate($perPage);
    }

    /**
     * Récupérer les fiches supprimées.
     */
    public function getTrashed(int $perPage = 15): LengthAwarePaginator
    {
        return FichePaie::onlyTrashed()
            ->with(['employe'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }

    public function delete(FichePaie $fiche): bool
    {
        return $fiche->delete();
    }

    public function restore(int $id): FichePaie
    {
        $fiche = FichePaie::withTrashed()->findOrFail($id);
        $fiche->restore();
        return $fiche;
    }

    public function update(FichePaie $fiche, array $data): FichePaie
    {
        $fiche->update($data);
        return $fiche->fresh();
    }

    /**
     * Générer le PDF d'une fiche de paie.
     */
    public function genererFichePDF(FichePaie $fiche): string
    {
        try {
            $fiche->load('employe');

            // Recompute display calculations for the PDF using stored fiche values
            $montant_heures_sup = (float) ($fiche->montant_heures_sup ?? 0);
            $absences = (float) ($fiche->absences ?? 0);

            // Approximate salaire_base from stored fields when not explicit
            $deduction_absences = $absences > 0 ? (($fiche->salaire_brut ?? 0) / 22) * $absences : 0;
            $salaire_base = max(0, (float) ($fiche->salaire_brut ?? 0) - $montant_heures_sup + $deduction_absences);
            $cotisations_salariales = (float) ($fiche->total_cotisations ?? (($fiche->salaire_brut ?? 0) * 0.22));
            $net_a_payer = (float) ($fiche->salaire_net ?? ($salaire_base - $cotisations_salariales + $montant_heures_sup - $deduction_absences));

            $calculs = [
                'salaire_base' => round($salaire_base, 2),
                'montant_heures_sup' => round($montant_heures_sup, 2),
                'deduction_absences' => round($deduction_absences, 2),
                'salaire_brut' => round((float) ($fiche->salaire_brut ?? 0), 2),
                'cotisations_salariales' => round($cotisations_salariales, 2),
                'net_a_payer' => round($net_a_payer, 2),
            ];

            $pdf = Pdf::loadView('pdf.fiche-paie', [
                'fiche' => $fiche,
                'employe' => $fiche->employe,
                'calculs' => $calculs,
            ]);

            // Ensure A4 paper and safer rendering to prevent cut-offs
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
                'dpi' => 150,
            ]);

            $filename = "fiche-paie-{$fiche->user_id}-{$fiche->periode}.pdf";
            $path = "fiches-paie/{$filename}";

            if (!Storage::disk('public')->exists('fiches-paie')) {
                Storage::disk('public')->makeDirectory('fiches-paie');
            }

            Storage::disk('public')->put($path, $pdf->output());
            $fiche->update(['document_path' => $path]);

            return $path;
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF: ' . $e->getMessage());
            throw $e;
        }
    }
}