<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Exports\LeavesExport;
use App\Exports\EmployeesExport;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController
{
    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Point d'entrée principal du dashboard - route les requêtes selon le rôle
     * 
     * Rôles et données retournées:
     * - Admin/Directeur: Vue RH complète avec tous les KPIs
     * - RH: Vue RH filtrée + vue Manager si applicable
     * - Manager: Vue de son équipe
     * - Employé: Vue personnelle
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Router vers la vue appropriée selon le rôle
            if ($user->hasRole(['admin', 'directeur'])) {
                return $this->getHrDashboard();
            }

            if ($user->hasRole('rh')) {
                // RH peut avoir aussi le rôle manager - on combine les deux vues
                $dashboard = $this->reportService->getHrKpis();
                
                if ($user->hasRole('manager')) {
                    $managerData = $this->reportService->getManagerDashboard($user->id);
                    $dashboard['mon_equipe'] = $managerData;
                }

                return response()->json([
                    'success' => true,
                    'data' => $dashboard,
                    'role' => 'rh',
                ]);
            }

            if ($user->hasRole('manager')) {
                return $this->getManagerDashboard($request);
            }

            // Employé par défaut
            return $this->getEmployeeDashboard($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dashboard RH/Admin - Vue complète avec KPIs
     * 
     * Endpoint: GET /api/dashboard/hr
     * Cache: 5 minutes avec tag 'dashboard:hr'
     */
    public function getHrDashboard(): JsonResponse
    {
        try {
            $kpis = $this->reportService->getHrKpis();

            // Récupérer données complémentaires pour les admins
            $contratsExpirantDetails = \App\Models\Contrat::actifs()
                ->expirantSous(30)
                ->with(['employe:id,name,email'])
                ->select(['id', 'employe_id', 'type', 'date_fin', 'duree_restante'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => array_merge($kpis, [
                    'contrats_expirant_details' => $contratsExpirantDetails,
                ]),
                'role' => 'hr',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dashboard Manager - Vue de l'équipe
     * 
     * Endpoint: GET /api/dashboard/manager
     * Permissions: Uniquement pour les managers (vérifie l'équipe)
     */
    public function getManagerDashboard(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('manager')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Rôle manager requis.',
                ], 403);
            }

            $dashboard = $this->reportService->getManagerDashboard($user->id);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'role' => 'manager',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dashboard Employé - Vue personnelle
     * 
     * Endpoint: GET /api/dashboard/employee
     */
    public function getEmployeeDashboard(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $dashboard = $this->reportService->getEmployeeDashboard($user->id);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'role' => 'employee',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint dédié pour les KPIs HR (pour les graphiques)
     * 
     * GET /api/dashboard/kpis
     */
    public function getHrKpis(): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'directeur', 'rh'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $kpis = $this->reportService->getHrKpis();

            return response()->json([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export des données en CSV ou Excel
     * 
     * Endpoint: GET /api/dashboard/export
     * Paramètres: 
     *   - type: 'leaves' | 'employees'
     *   - format: 'csv' | 'xlsx' (défaut: xlsx)
     *   - filtres optionnels (statut, type, date_debut, date_fin, etc.)
     * 
     * Utilise Maatwebsite/Excel pour la génération
     */
    public function exportReport(Request $request): Response|JsonResponse
    {
        try {
            Log::debug('exportReport called', ['type' => $request->input('type'), 'format' => $request->input('format')]);
            $type = $request->input('type', 'leaves');
            $format = $request->input('format', 'xlsx');
            $filters = $request->except(['type', 'format']);

            // Vérifier les permissions selon le type
            if ($type === 'employees' && !auth()->user()->hasRole(['admin', 'directeur', 'rh'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            if ($type === 'leaves') {
                // Limiter les congés à l'employé ou son équipe selon le rôle
                $user = auth()->user();
                if (!$user->hasRole(['admin', 'directeur', 'rh'])) {
                    if ($user->hasRole('manager')) {
                        $filters['manager_id'] = $user->id;
                    } else {
                        $filters['employe_id'] = $user->id;
                    }
                }

                $data = $this->reportService->getLeavesForExport($filters);
            } else {
                $data = $this->reportService->getEmployeesForExport($filters);
            }

            $filename = "{$type}_export_" . now()->format('Y-m-d_His') . ($format === 'csv' ? '.csv' : ($format === 'pdf' ? '.pdf' : '.xlsx'));

            if ($format === 'pdf') {
                $viewData = [
                    'type' => $type,
                    'generatedAt' => now(),
                    'rows' => $data,
                    'filters' => $filters,
                ];

                if (class_exists(Pdf::class)) {
                    return Pdf::loadView('exports.report', $viewData)->download($filename);
                }

                return $this->downloadPlainPdf($filename, $viewData);
            }

            // Use Maatwebsite/Excel exports for CSV/XLSX
            if (in_array($format, ['csv', 'xlsx'])) {
                Log::debug('exportReport: excel_class_exists', ['exists' => class_exists(\Maatwebsite\Excel\Excel::class)]);
                // If Maatwebsite Excel is available, use it for proper XLSX/CSV generation
                if (class_exists(\Maatwebsite\Excel\Excel::class)) {
                    Log::debug('exportReport using Excel', ['type' => $type, 'count' => $data->count()]);
                    $writerType = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

                    if ($type === 'leaves') {
                        return Excel::download(new LeavesExport($data), $filename, $writerType);
                    }

                    return Excel::download(new EmployeesExport($data), $filename, $writerType);
                }

                // Fallback to CSV stream if package not installed
                $format = 'csv';
            }

            // Fallback to CSV stream for unknown formats
            $callback = function () use ($data) {
                $out = fopen('php://output', 'w');

                if ($data->isEmpty()) {
                    fputcsv($out, ['no_data']);
                    fclose($out);
                    return;
                }

                $first = (array) $data->first();
                fputcsv($out, array_keys($first));

                foreach ($data as $row) {
                    $rowArr = [];
                    foreach ($first as $k => $v) {
                        $val = is_array($row) ? ($row[$k] ?? '') : ($row->$k ?? '');
                        $rowArr[] = is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
                    }
                    fputcsv($out, $rowArr);
                }

                fclose($out);
            };

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function downloadPlainPdf(string $filename, array $viewData): Response
    {
        $title = 'Rapport ' . ($viewData['type'] === 'leaves' ? 'des congés' : 'des effectifs');
        $lines = [
            $title,
            'Généré le ' . $viewData['generatedAt']->format('d/m/Y H:i'),
        ];

        if (!empty($viewData['filters']['debut']) || !empty($viewData['filters']['fin'])) {
            $lines[] = 'Période: ' . ($viewData['filters']['debut'] ?? '...') . ' -> ' . ($viewData['filters']['fin'] ?? '...');
        }

        $lines[] = '';

        if ($viewData['rows']->isEmpty()) {
            $lines[] = 'Aucune donnée disponible pour cette période.';
        } else {
            $first = (array) $viewData['rows']->first();
            $headers = array_keys($first);
            $lines[] = implode(' | ', array_map(fn ($heading) => str_replace('_', ' ', ucfirst($heading)), $headers));
            $lines[] = str_repeat('-', 90);

            foreach ($viewData['rows'] as $row) {
                $rowArray = (array) $row;
                $lines[] = implode(' | ', array_map(function ($key) use ($rowArray) {
                    $value = $rowArray[$key] ?? '';
                    if (is_scalar($value) || $value === null) {
                        return (string) $value;
                    }

                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }, $headers));
            }
        }

        return response($this->buildSimplePdf($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildSimplePdf(array $lines): string
    {
        $pageWidth = 595;
        $pageHeight = 842;
        $linesPerPage = 42;
        $chunks = array_chunk($lines, $linesPerPage);

        $objects = [];
        $fontObjectId = 3 + (count($chunks) * 2);

        $kids = [];
        foreach ($chunks as $index => $chunk) {
            $pageObjectId = 3 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = $pageObjectId . ' 0 R';

            $text = [];
            $text[] = 'BT';
            $text[] = '/F1 10 Tf';
            $text[] = '14 TL';
            $text[] = '50 800 Td';
            foreach ($chunk as $line) {
                $text[] = '(' . $this->escapePdfText($line) . ') Tj';
                $text[] = 'T*';
            }
            $text[] = 'ET';
            $contentStream = implode("\n", $text);

            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $fontObjectId,
                $contentObjectId
            );
            $objects[$contentObjectId] = sprintf('<< /Length %d >>\nstream\n%s\nendstream', strlen($contentStream), $contentStream);
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($chunks) . ' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $objectId => $body) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $totalObjects = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($totalObjects + 1) . "\n";
        $pdf .= sprintf("%010d 65535 f \n", 0);
        for ($i = 1; $i <= $totalObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($totalObjects + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * Endpoint pour invalider le cache (admin uniquement)
     * 
     * POST /api/dashboard/cache/clear
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'directeur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                ], 403);
            }

            $type = $request->input('type');
            $id = $request->input('id');

            $this->reportService->invalidateCache($type, $id);

            return response()->json([
                'success' => true,
                'message' => 'Cache invalidé avec succès.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rapport congés - utilisé par le frontend `/reports/leaves`
     */
    public function leaves(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'directeur', 'rh', 'manager'])) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $filters = $request->only(['debut', 'fin', 'statut', 'type', 'employe_id']);

            // Si manager, restreindre aux membres de son équipe sauf si rôle RH/Directeur/Admin
            $user = auth()->user();
            if ($user->hasRole('manager') && !$user->hasAnyRole(['admin', 'directeur', 'rh'])) {
                $filters['manager_id'] = $user->id;
            }

            $data = $this->reportService->getLeavesForExport($filters);

            return response()->json(['success' => true, 'data' => [
                'totalDemandes' => $data->count(),
                'parType' => $data->groupBy('type')->map->count(),
                'rows' => $data,
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rapport effectifs - utilisé par le frontend `/reports/employees`
     */
    public function employees(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            // Autoriser si rôle admin/directeur/rh ou permission 'view reports'
            if (!($user->hasAnyRole(['admin', 'directeur', 'rh']) || method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('view reports'))) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $filters = $request->only(['debut', 'fin', 'departement_id', 'manager_id', 'actif']);

            $data = $this->reportService->getEmployeesForExport($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Données pour les graphiques du dashboard
     * 
     * Endpoint: GET /api/dashboard/charts
     * Retourne: évolution effectifs, répartition départements, congés par type
     */
    public function chartData(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Vérifier les permissions
            if (!$user->hasAnyRole(['admin', 'directeur', 'rh'])) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $type = $request->input('type', 'all'); // all, evolution, departments, leaves
            
            $data = [];

            // Évolution des effectifs sur 12 mois
            if ($type === 'all' || $type === 'evolution') {
                $data['evolution'] = $this->getEvolutionEffectifs();
            }

            // Répartition par département
            if ($type === 'all' || $type === 'departments') {
                $data['departments'] = $this->getRepartitionDepartements();
            }

            // Congés par type
            if ($type === 'all' || $type === 'leaves') {
                $data['leaves'] = $this->getCongesParType();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Données d'évolution des effectifs sur 12 mois
     */
    private function getEvolutionEffectifs(): array
    {
        $months = [];
        $now = now();
        
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->translatedFormat('M');
            
            // Compter les employés actifs à cette date
            $count = \App\Models\User::whereDate('date_embauche', '<=', $date->endOfMonth())
                ->where(function ($q) use ($date) {
                    $q->whereNull('date_depart')
                        ->orWhereDate('date_depart', '>', $date->startOfMonth());
                })
                ->count();

            // Compter les embauches du mois
            $embauches = \App\Models\User::whereYear('date_embauche', $date->year)
                ->whereMonth('date_embauche', $date->month)
                ->count();

            // Compter les départs du mois
            $departs = \App\Models\User::whereYear('date_depart', $date->year)
                ->whereMonth('date_depart', $date->month)
                ->count();

            $months[] = [
                'name' => $monthLabel,
                'employes' => $count,
                'embauches' => $embauches,
                'departs' => $departs,
            ];
        }

        return $months;
    }

    /**
     * Répartition des employés par département
     */
    private function getRepartitionDepartements(): array
    {
        $departments = \App\Models\Departement::withCount(['employes' => function ($query) {
            $query->where('est_actif', true);
        }])->get();

        $colors = ['#0ea5e9', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        
        return $departments->map(function ($dept, $index) use ($colors) {
            return [
                'name' => $dept->nom,
                'value' => $dept->employes_count,
                'color' => $colors[$index % count($colors)],
            ];
        })->values()->toArray();
    }

    /**
     * Congés par type (demandes vs approuvées)
     */
    private function getCongesParType(): array
    {
        $types = ['annuel', 'maladie', 'sans_solde', 'maternite', 'paternite', 'formation'];
        $result = [];

        foreach ($types as $type) {
            $demandes = \App\Models\Conge::where('type', $type)
                ->whereYear('created_at', now()->year)
                ->count();

            $approuves = \App\Models\Conge::where('type', $type)
                ->where('etat', 'approuve')
                ->whereYear('created_at', now()->year)
                ->count();

            if ($demandes > 0) {
                $result[] = [
                    'name' => ucfirst(str_replace('_', ' ', $type)),
                    'demandes' => $demandes,
                    'approuves' => $approuves,
                ];
            }
        }

        return $result;
    }
}
