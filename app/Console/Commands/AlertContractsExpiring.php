<?php

namespace App\Console\Commands;

use App\Modules\Contracts\Services\ContractService;
use Illuminate\Console\Command;

class AlertContractsExpiring extends Command
{
    protected $signature = 'contracts:alert-expiring 
                            {--jours=30 : Nombre de jours avant expiration}
                            {--dry-run : Simulation sans envoi}';

    protected $description = 'Envoie des alertes pour les contrats expirant dans N jours';

    private ContractService $service;

    public function __construct(ContractService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $jours = (int) $this->option('jours');
        $dryRun = $this->option('dry-run');

        $this->info("Recherche des contrats expirant dans {$jours} jours...");

        try {
            $contrats = $this->service->getExpiringContracts($jours);

            if ($contrats->isEmpty()) {
                $this->info('Aucun contrat expirant trouvé.');
                return self::SUCCESS;
            }

            $this->info("{$contrats->count()} contrat(s) trouvé(s).");

            if ($dryRun) {
                $this->warn('Mode simulation - aucune notification envoyée.');
                foreach ($contrats as $contrat) {
                    $this->line("- {$contrat->employe->name} (expire le {$contrat->date_fin->format('d/m/Y')})");
                }
                return self::SUCCESS;
            }

            $this->service->sendExpirationAlerts();
            $this->info('Alertes envoyées avec succès.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
