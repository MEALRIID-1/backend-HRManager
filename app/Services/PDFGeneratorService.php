<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contrat;
use App\Models\FichePaie;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PDFGeneratorService
{
    private FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Génère un PDF de contrat officiel.
     *
     * @param Contrat $contrat
     * @return string URL du PDF généré
     */
    public function genererContrat(Contrat $contrat): string
    {
        try {
            // Charger les relations nécessaires
            $contrat->load(['employe']);

            // Générer le PDF
            $pdf = Pdf::loadView('pdf.contrat', [
                'contrat' => $contrat,
                'employe' => $contrat->employe,
                'dateGeneration' => now()->format('d/m/Y'),
            ]);

            // Configuration du PDF
            $pdf->setPaper('A4');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

            // Nom du fichier
            $filename = $this->fileUploadService->genererNomFichier(
                'contrat',
                $contrat->id
            ) . '.pdf';

            $path = "contrats/{$filename}";

            // Sauvegarder le PDF
            Storage::disk('public')->put($path, $pdf->output());

            Log::info("PDF contrat généré: {$path}");

            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF contrat: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Génère un PDF de fiche de paie (bulletin de salaire).
     *
     * @param FichePaie $fiche
     * @return string URL du PDF généré
     */
    public function genererFichePaie(FichePaie $fiche): string
    {
        try {
            // Charger les relations
            $fiche->load(['employe']);

            // Calculs détaillés
            $calculs = $this->calculerFichePaie($fiche);

            // Générer le PDF
            $pdf = Pdf::loadView('pdf.fiche-paie', [
                'fiche' => $fiche,
                'employe' => $fiche->employe,
                'calculs' => $calculs,
                'dateGeneration' => now()->format('d/m/Y'),
            ]);

            $pdf->setPaper('A4');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

            $filename = $this->fileUploadService->genererNomFichier(
                'fiche-paie',
                $fiche->id
            ) . '.pdf';

            $path = "fiches-paie/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            // Mettre à jour le chemin dans la fiche
            $fiche->update(['pdf_url' => $path]);

            Log::info("PDF fiche de paie généré: {$path}");

            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF fiche de paie: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Génère un PDF de rapport.
     *
     * @param string $type Type de rapport (conges, employes, activite)
     * @param array $data Données du rapport
     * @return string URL du PDF généré
     */
    public function genererRapport(string $type, array $data): string
    {
        try {
            $view = match($type) {
                'conges' => 'pdf.rapport-conges',
                'employes' => 'pdf.rapport-employes',
                'activite' => 'pdf.rapport-activite',
                default => 'pdf.rapport-generic',
            };

            $pdf = Pdf::loadView($view, [
                'data' => $data,
                'type' => $type,
                'dateGeneration' => now()->format('d/m/Y'),
                'periode' => $data['periode'] ?? null,
            ]);

            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

            $filename = $this->fileUploadService->genererNomFichier(
                'rapport-' . $type,
                0
            ) . '.pdf';

            $path = "rapports/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            Log::info("PDF rapport généré: {$path}");

            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF rapport: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcule les détails d'une fiche de paie.
     *
     * @param FichePaie $fiche
     * @return array<string, float>
     */
    private function calculerFichePaie(FichePaie $fiche): array
    {
        $salaireBase = $fiche->salaire_base;
        $heuresSup = $fiche->heures_sup ?? 0;
        $absences = $fiche->absences ?? 0;

        // Taux horaire basé sur 151.67h/mois
        $tauxHoraire = $salaireBase / 151.67;

        // Heures supplémentaires majorées 25%
        $montantHeuresSup = $heuresSup * $tauxHoraire * 1.25;

        // Déduction absences (22 jours ouvrés/mois)
        $deductionAbsences = $absences > 0 ? ($salaireBase / 22) * $absences : 0;

        // Salaire brut
        $salaireBrut = $salaireBase + $montantHeuresSup - $deductionAbsences;

        // Cotisations salariales estimées (~22%)
        $cotisations = $salaireBrut * 0.22;

        // Net à payer
        $netAPayer = $salaireBrut - $cotisations;

        return [
            'salaire_base' => round($salaireBase, 2),
            'taux_horaire' => round($tauxHoraire, 2),
            'montant_heures_sup' => round($montantHeuresSup, 2),
            'deduction_absences' => round($deductionAbsences, 2),
            'salaire_brut' => round($salaireBrut, 2),
            'cotisations_salariales' => round($cotisations, 2),
            'net_a_payer' => round($netAPayer, 2),
        ];
    }

    /**
     * Stream un PDF directement (sans le sauvegarder).
     *
     * @param string $view
     * @param array $data
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function streamPDF(string $view, array $data, string $filename)
    {
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('A4');

        return $pdf->stream($filename);
    }

    /**
     * Download un PDF directement.
     *
     * @param string $view
     * @param array $data
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF(string $view, array $data, string $filename)
    {
        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('A4');

        return $pdf->download($filename);
    }
}
