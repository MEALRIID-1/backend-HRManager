<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;

class FileUploadService
{
    /**
     * Upload et redimensionne une photo de profil.
     *
     * @param UploadedFile $file
     * @param int $userId
     * @return string URL publique du fichier
     * @throws ValidationException
     */
    public function uploadPhoto(UploadedFile $file, int $userId): string
    {
        // Validation
        $this->validateFile($file, ['jpg', 'jpeg', 'png', 'webp'], 2048); // 2MB max

        try {
            // Générer le nom de fichier unique
            $filename = $this->genererNomFichier('photo', $userId) . '.' . $file->getClientOriginalExtension();
            
            // Chemin de stockage
            $folder = "photos/{$userId}";
            $path = "{$folder}/{$filename}";

            // Créer le dossier s'il n'existe pas
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder);
            }

            // Redimensionner l'image avec Intervention Image
            $image = Image::read($file->getRealPath());
            
            // Redimensionner à 300x300 avec crop centré
            $image->cover(300, 300, 'center');
            
            // Convertir en WebP pour optimiser
            $image->toWebp(quality: 85);

            // Sauvegarder l'image
            $fullPath = Storage::disk('public')->path($path);
            $image->save($fullPath);

            Log::info("Photo uploadée avec succès: {$path}");

            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Erreur upload photo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload un fichier PDF.
     *
     * @param UploadedFile $file
     * @param string $folder Dossier de destination (ex: 'contrats', 'fiches-paie')
     * @return string URL publique du fichier
     * @throws ValidationException
     */
    public function uploadPDF(UploadedFile $file, string $folder): string
    {
        // Validation : PDF uniquement, max 10MB
        $this->validateFile($file, ['pdf'], 10240); // 10MB max

        try {
            // Générer le nom de fichier unique
            $timestamp = now()->format('Ymd_His');
            $filename = "{$folder}_{$timestamp}.pdf";
            
            // Chemin de stockage
            $path = "{$folder}/{$filename}";

            // Stocker le fichier
            $storedPath = $file->storeAs($folder, $filename, 'public');

            Log::info("PDF uploadé avec succès: {$storedPath}");

            return Storage::disk('public')->url($storedPath);
        } catch (\Exception $e) {
            Log::error('Erreur upload PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprime un fichier du storage.
     *
     * @param string $path Chemin relatif du fichier (ex: 'photos/1/photo_123.jpg')
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            // Supprimer le préfixe 'public/' si présent
            $path = str_replace('public/', '', $path);
            
            if (Storage::disk('public')->exists($path)) {
                $result = Storage::disk('public')->delete($path);
                
                if ($result) {
                    Log::info("Fichier supprimé: {$path}");
                }
                
                return $result;
            }

            Log::warning("Fichier non trouvé pour suppression: {$path}");
            return false;
        } catch (\Exception $e) {
            Log::error('Erreur suppression fichier: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère un nom de fichier unique.
     *
     * @param string $prefix Préfixe du fichier
     * @param int $id ID de l'entité
     * @return string
     */
    public function genererNomFichier(string $prefix, int $id): string
    {
        $timestamp = now()->format('Ymd_His');
        $random = substr(md5(uniqid()), 0, 8);
        
        return "{$prefix}_{$id}_{$timestamp}_{$random}";
    }

    /**
     * Valide un fichier uploadé.
     *
     * @param UploadedFile $file
     * @param array $allowedExtensions Extensions autorisées
     * @param int $maxSizeKB Taille max en KB
     * @throws ValidationException
     */
    private function validateFile(UploadedFile $file, array $allowedExtensions, int $maxSizeKB): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $sizeKB = $file->getSize() / 1024;

        if (!in_array($extension, $allowedExtensions)) {
            throw ValidationException::withMessages([
                'file' => ["Le fichier doit être au format: " . implode(', ', $allowedExtensions)],
            ]);
        }

        if ($sizeKB > $maxSizeKB) {
            throw ValidationException::withMessages([
                'file' => ["Le fichier ne doit pas dépasser {$maxSizeKB}KB"],
            ]);
        }
    }

    /**
     * Récupère l'URL publique d'un fichier.
     *
     * @param string $path
     * @return string|null
     */
    public function getFileUrl(string $path): ?string
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    /**
     * Vérifie si un fichier existe.
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk('public')->exists($path);
    }
}
