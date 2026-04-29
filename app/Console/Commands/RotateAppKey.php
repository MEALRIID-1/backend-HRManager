<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Commande pour faire tourner la clé d'application avec re-chiffrement des données.
 */
class RotateAppKey extends Command
{
    protected $signature = 'key:rotate
                            {--force : Forcer la rotation sans confirmation}
                            {--dry-run : Simuler sans appliquer les changements}';

    protected $description = 'Faire tourner APP_KEY et re-chiffrer les données sensibles';

    /**
     * Liste des modèles et attributs à re-chiffrer.
     */
    protected array $encryptedModels = [
        'App\Models\User' => ['iban', 'numero_securite_sociale'],
    ];

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm(
            '⚠️  AVERTISSEMENT: La rotation de clé déconnectera tous les utilisateurs. Continuer ?',
            false
        )) {
            $this->info('Opération annulée.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $oldKey = config('app.key');

        if (!$dryRun) {
            $this->info('1. Backup de la clé actuelle...');
            $this->backupOldKey($oldKey);
        }

        // 1. Déchiffrer toutes les données avec l'ancienne clé
        $this->info('2. Déchiffrement des données...');
        $decryptedData = $this->decryptAllData($dryRun);

        // 2. Générer nouvelle clé
        $this->info('3. Génération de la nouvelle clé...');
        $newKey = $this->generateNewKey($dryRun);

        // 3. Re-chiffrer avec nouvelle clé
        $this->info('4. Re-chiffrement des données...');
        $this->reencryptData($decryptedData, $dryRun);

        // 4. Mettre à jour .env
        if (!$dryRun) {
            $this->info('5. Mise à jour du fichier .env...');
            $this->updateEnvFile($oldKey, $newKey);
        }

        // 5. Vider tous les caches
        if (!$dryRun) {
            $this->info('6. Nettoyage des caches...');
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->call('view:clear');
            
            // Révoquer tous les tokens existants
            $this->call('passport:keys');
        }

        $this->newLine();
        $this->info('✅ Rotation de clé terminée avec succès !');
        
        if (!$dryRun) {
            $this->warn('⚠️  IMPORTANT:');
            $this->warn('   - Tous les utilisateurs sont déconnectés');
            $this->warn('   - Ils doivent se reconnecter avec leurs identifiants');
            $this->warn('   - La clé précédente est dans: storage/app/keys_backup/');
        }

        return 0;
    }

    /**
     * Backup de l'ancienne clé.
     */
    protected function backupOldKey(string $key): void
    {
        $backupDir = storage_path('app/keys_backup');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }

        $filename = $backupDir . '/key_' . now()->format('Y-m-d_H-i-s') . '.txt';
        file_put_contents($filename, $key, LOCK_EX);
        chmod($filename, 0600);

        $this->info("   Clé backupée dans: {$filename}");
    }

    /**
     * Déchiffre toutes les données sensibles.
     */
    protected function decryptAllData(bool $dryRun): array
    {
        $data = [];

        foreach ($this->encryptedModels as $modelClass => $attributes) {
            $this->info("   Déchiffrement {$modelClass}...");
            
            $model = new $modelClass;
            $records = $model->all();

            foreach ($records as $record) {
                foreach ($attributes as $attribute) {
                    $value = $record->$attribute;
                    
                    if ($value && $this->isEncrypted($value)) {
                        try {
                            $decrypted = Crypt::decryptString($value);
                            $data[$modelClass][$record->id][$attribute] = $decrypted;
                        } catch (\Exception $e) {
                            $this->error("   Erreur déchiffrement {$modelClass}#{$record->id}->{$attribute}");
                            throw $e;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Vérifie si une valeur est chiffrée.
     */
    protected function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Format de Laravel: base64 avec prefix
        try {
            $decoded = base64_decode($value, true);
            if ($decoded === false) {
                return false;
            }
            
            return str_starts_with($decoded, '{"iv":"');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Génère une nouvelle clé.
     */
    protected function generateNewKey(bool $dryRun): string
    {
        if ($dryRun) {
            return 'base64:' . base64_encode(random_bytes(32));
        }

        // Utiliser la commande artisan
        $this->call('key:generate', ['--show' => true]);
        
        return config('app.key');
    }

    /**
     * Re-chiffre les données avec la nouvelle clé.
     */
    protected function reencryptData(array $data, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('   [DRY-RUN] Données seraient re-chiffrées');
            return;
        }

        foreach ($data as $modelClass => $records) {
            foreach ($records as $id => $attributes) {
                $model = $modelClass::find($id);
                
                if ($model) {
                    foreach ($attributes as $attribute => $value) {
                        $model->$attribute = Crypt::encryptString($value);
                    }
                    
                    $model->save();
                }
            }
        }
    }

    /**
     * Met à jour le fichier .env.
     */
    protected function updateEnvFile(string $oldKey, string $newKey): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        // Remplacer l'ancienne clé
        $envContent = str_replace(
            'APP_KEY=' . $oldKey,
            'APP_KEY=' . $newKey,
            $envContent
        );

        // Ajouter commentaire de rotation
        $rotationComment = "# Clé rotée le " . now()->toDateTimeString() . "\n";
        $envContent = str_replace(
            'APP_KEY=' . $newKey,
            $rotationComment . 'APP_KEY=' . $newKey,
            $envContent
        );

        file_put_contents($envFile, $envContent);
        chmod($envFile, 0600);
    }
}
