<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Service de gestion sécurisée des fichiers.
 * Gère les uploads, downloads et accès aux fichiers sensibles.
 */
class FileStorageService
{
    // Types MIME autorisés par contexte
    public const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const ALLOWED_DOCUMENT_TYPES = ['application/pdf'];
    public const ALLOWED_EXPORT_TYPES = [
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // Extensions autorisées
    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf'];

    // Taille max (en Ko)
    public const MAX_PHOTO_SIZE = 2048; // 2MB
    public const MAX_DOCUMENT_SIZE = 10240; // 10MB

    /**
     * Upload d'une photo de profil avec redimensionnement et conversion WebP.
     *
     * @param UploadedFile $file Le fichier uploadé
     * @param string $path Chemin de destination (ex: "profiles/123")
     * @return array Informations sur le fichier uploadé
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function uploadPhoto(UploadedFile $file, string $path): array
    {
        // Validation
        $this->validateFile($file, self::ALLOWED_PHOTO_TYPES, self::MAX_PHOTO_SIZE);

        // Génération d'un nom hashé unique
        $hash = hash('sha256', $file->getClientOriginalName() . time() . Str::random(16));
        $filename = $hash . '.webp';
        $fullPath = $path . '/' . $filename;

        try {
            // Redimensionnement et conversion en WebP
            $image = Image::read($file->getRealPath());

            // Redimensionner à 300x300 avec crop centré
            $image->cover(300, 300, 'center');

            // Convertir en WebP avec qualité 85
            $processedImage = $image->encodeByExtension('webp', quality: 85);

            // Stockage
            Storage::disk('photos')->put($fullPath, $processedImage);

            return [
                'disk' => 'photos',
                'path' => $fullPath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => 'image/webp',
                'size' => strlen($processedImage),
                'hash' => $hash,
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors du traitement de l\'image: ' . $e->getMessage());
        }
    }

    /**
     * Upload d'un document sensible (contrat, fiche de paie, etc.).
     *
     * @param UploadedFile $file Le fichier uploadé
     * @param string $path Chemin de destination
     * @param string $disk Disk de stockage ('documents' ou 'payslips')
     * @return array Informations sur le fichier uploadé
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function uploadDocument(
        UploadedFile $file,
        string $path,
        string $disk = 'documents'
    ): array {
        // Validation
        $this->validateFile($file, self::ALLOWED_DOCUMENT_TYPES, self::MAX_DOCUMENT_SIZE);

        // Vérifier que le disk est autorisé
        if (!in_array($disk, ['documents', 'payslips', 's3'])) {
            throw new \InvalidArgumentException('Disk non autorisé pour les documents sensibles.');
        }

        // Génération d'un nom hashé unique
        $hash = hash('sha256', $file->getClientOriginalName() . time() . Str::random(16));
        $extension = $file->getClientOriginalExtension();
        $filename = $hash . '.' . $extension;
        $fullPath = $path . '/' . $filename;

        try {
            // Stockage direct sans modification
            $stored = Storage::disk($disk)->putFileAs(
                $path,
                $file,
                $filename
            );

            if (!$stored) {
                throw new \RuntimeException('Échec du stockage du fichier.');
            }

            return [
                'disk' => $disk,
                'path' => $fullPath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'hash' => $hash,
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors de l\'upload du document: ' . $e->getMessage());
        }
    }

    /**
     * Génère une URL signée temporaire pour un fichier privé.
     *
     * @param string $path Chemin du fichier
     * @param string $disk Disk où est stocké le fichier
     * @param int $minutes Durée de validité en minutes
     * @return string URL signée
     * @throws \InvalidArgumentException
     */
    public function getSignedUrl(string $path, string $disk = 'documents', int $minutes = 60): string
    {
        // Vérifier que le fichier existe
        if (!Storage::disk($disk)->exists($path)) {
            throw new \InvalidArgumentException('Fichier non trouvé.');
        }

        // Vérifier que le disk supporte les URLs signées
        if (in_array($disk, ['local', 'public', 'exports'])) {
            throw new \InvalidArgumentException('Ce disk ne supporte pas les URLs signées.');
        }

        return Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Supprime un fichier avec vérification d'existence.
     *
     * @param string $path Chemin du fichier
     * @param string $disk Disk où est stocké le fichier
     * @return bool True si supprimé, false si n'existait pas
     */
    public function deleteFile(string $path, string $disk = 'documents'): bool
    {
        if (!Storage::disk($disk)->exists($path)) {
            return false;
        }

        return Storage::disk($disk)->delete($path);
    }

    /**
     * Déplace un fichier entre deux locations (même disk ou différents).
     *
     * @param string $fromPath Chemin source
     * @param string $toPath Chemin destination
     * @param string $fromDisk Disk source
     * @param string|null $toDisk Disk destination (null = même que source)
     * @return array Informations sur le fichier déplacé
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function moveFile(
        string $fromPath,
        string $toPath,
        string $fromDisk = 'documents',
        ?string $toDisk = null
    ): array {
        $toDisk = $toDisk ?? $fromDisk;

        // Vérifier que le fichier source existe
        if (!Storage::disk($fromDisk)->exists($fromPath)) {
            throw new \InvalidArgumentException('Fichier source non trouvé.');
        }

        try {
            if ($fromDisk === $toDisk) {
                // Déplacement simple sur le même disk
                Storage::disk($fromDisk)->move($fromPath, $toPath);
            } else {
                // Copie puis suppression pour changement de disk
                $content = Storage::disk($fromDisk)->get($fromPath);
                Storage::disk($toDisk)->put($toPath, $content);
                Storage::disk($fromDisk)->delete($fromPath);
            }

            return [
                'disk' => $toDisk,
                'path' => $toPath,
                'moved' => true,
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors du déplacement du fichier: ' . $e->getMessage());
        }
    }

    /**
     * Upload d'un fichier d'export (CSV, Excel).
     *
     * @param UploadedFile $file Le fichier uploadé
     * @param string $path Chemin de destination
     * @return array Informations sur le fichier uploadé
     */
    public function uploadExport(UploadedFile $file, string $path): array
    {
        $this->validateFile($file, self::ALLOWED_EXPORT_TYPES, self::MAX_DOCUMENT_SIZE);

        $hash = hash('sha256', $file->getClientOriginalName() . time() . Str::random(16));
        $extension = $file->getClientOriginalExtension();
        $filename = $hash . '.' . $extension;
        $fullPath = $path . '/' . $filename;

        Storage::disk('exports')->putFileAs($path, $file, $filename);

        return [
            'disk' => 'exports',
            'path' => $fullPath,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Valide un fichier selon les contraintes définies.
     *
     * @param UploadedFile $file Le fichier à valider
     * @param array $allowedTypes Types MIME autorisés
     * @param int $maxSize Taille maximale en Ko
     * @throws \InvalidArgumentException
     */
    private function validateFile(UploadedFile $file, array $allowedTypes, int $maxSize): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier est invalide ou corrompu.');
        }

        // Vérifier le type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedTypes)) {
            throw new \InvalidArgumentException(
                'Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowedTypes)
            );
        }

        // Vérifier la taille
        if ($file->getSize() > $maxSize * 1024) {
            throw new \InvalidArgumentException(
                "Taille du fichier trop grande. Maximum: {$maxSize}Ko"
            );
        }
    }

    /**
     * Récupère les métadonnées d'un fichier.
     *
     * @param string $path Chemin du fichier
     * @param string $disk Disk où est stocké le fichier
     * @return array|null Métadonnées ou null si inexistant
     */
    public function getFileMetadata(string $path, string $disk = 'documents'): ?array
    {
        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'path' => $path,
            'disk' => $disk,
            'size' => Storage::disk($disk)->size($path),
            'last_modified' => Storage::disk($disk)->lastModified($path),
            'mime_type' => Storage::disk($disk)->mimeType($path),
        ];
    }
}
