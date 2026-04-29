<?php

namespace App\Http\Controllers;

use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controller pour la gestion sécurisée des fichiers.
 * Tous les accès passent par ce controller - jamais de chemin direct exposé.
 */
class FileController
{
    private FileStorageService $storageService;

    public function __construct(FileStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Téléchargement d'un fichier avec vérification des permissions.
     *
     * GET /api/files/download/{type}/{id}
     *
     * @param string $type Type de fichier: 'photo', 'contrat', 'payslip', 'document'
     * @param int $id ID de l'entité associée
     * @return JsonResponse|
     */
    public function download(string $type, int $id)
    {
        $user = auth()->user();

        try {
            [$path, $disk, $filename] = match ($type) {
                'photo' => $this->getPhotoPath($id, $user),
                'contrat' => $this->getContratPath($id, $user),
                'payslip' => $this->getPayslipPath($id, $user),
                'document' => $this->getDocumentPath($id, $user),
                default => throw new \InvalidArgumentException('Type de fichier non supporté.'),
            };

            // Générer l'URL signée
            $signedUrl = $this->storageService->getSignedUrl($path, $disk, 60);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $signedUrl,
                    'expires_in' => 60,
                    'filename' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e instanceof \Illuminate\Http\Exceptions\HttpResponseException ? 403 : 404);
        }
    }

    /**
     * Upload générique avec whitelist de types MIME.
     *
     * POST /api/files/upload
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'type' => 'required|in:photo,document,export',
            'path' => 'required|string|max:255',
            'entity_id' => 'nullable|integer',
        ]);

        $type = $request->input('type');
        $path = $request->input('path');
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        try {
            $result = match ($type) {
                'photo' => $this->storageService->uploadPhoto($file, $path),
                'document' => $this->storageService->uploadDocument($file, $path, 'documents'),
                'export' => $this->storageService->uploadExport($file, $path),
                default => throw new \InvalidArgumentException('Type d\'upload non supporté.'),
            };

            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès.',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload de photo de profil spécifique.
     *
     * POST /api/files/photo/{userId}
     */
    public function uploadPhoto(Request $request, int $userId): JsonResponse
    {
        $user = auth()->user();
        $targetUser = User::findOrFail($userId);

        // Vérifier les permissions
        if (!$this->canUploadPhoto($user, $targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('photo');
            $path = 'profiles/' . $userId;

            // Supprimer l'ancienne photo si existe
            if ($targetUser->photo_path) {
                $this->storageService->deleteFile($targetUser->photo_path, 'photos');
            }

            $result = $this->storageService->uploadPhoto($file, $path);

            // Mettre à jour le profil utilisateur
            $targetUser->update(['photo_path' => $result['path']]);

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil mise à jour.',
                'data' => [
                    'photo_url' => Storage::disk('photos')->url($result['path']),
                    'photo_path' => $result['path'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suppression d'un fichier.
     *
     * DELETE /api/files/delete
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'disk' => 'required|string|in:photos,documents,payslips,exports',
        ]);

        $user = auth()->user();

        // Vérifier les permissions selon le type de fichier
        if (!$this->canDeleteFile($user, $request->input('disk'))) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $path = $request->input('path');
        $disk = $request->input('disk');

        $deleted = $this->storageService->deleteFile($path, $disk);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé ou déjà supprimé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fichier supprimé avec succès.',
        ]);
    }

    /**
     * Informations sur un fichier (métadonnées).
     *
     * GET /api/files/metadata
     */
    public function metadata(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'disk' => 'required|string',
        ]);

        $metadata = $this->storageService->getFileMetadata(
            $request->input('path'),
            $request->input('disk')
        );

        if (!$metadata) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $metadata,
        ]);
    }

    // ============================================================================
    // Méthodes privées de vérification des permissions
    // ============================================================================

    private function getPhotoPath(int $userId, $currentUser): array
    {
        $targetUser = User::findOrFail($userId);

        // Vérifier que l'utilisateur peut voir cette photo
        if ($currentUser->id !== $userId &&
            !$currentUser->hasRole(['admin', 'rh', 'manager'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Accès refusé.'], 403)
            );
        }

        if (!$targetUser->photo_path) {
            throw new \InvalidArgumentException('Photo de profil non trouvée.');
        }

        return [$targetUser->photo_path, 'photos', basename($targetUser->photo_path)];
    }

    private function getContratPath(int $contratId, $currentUser): array
    {
        $contrat = Contrat::with('employe')->findOrFail($contratId);

        // Vérifier les permissions
        if ($currentUser->id !== $contrat->employe_id &&
            !$currentUser->hasRole(['admin', 'rh', 'directeur'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Accès refusé.'], 403)
            );
        }

        if (!$contrat->document_path) {
            throw new \InvalidArgumentException('Document de contrat non trouvé.');
        }

        return [$contrat->document_path, 'documents', "contrat_{$contratId}.pdf"];
    }

    private function getPayslipPath(int $payslipId, $currentUser): array
    {
        $payslip = FichePaie::with('employe')->findOrFail($payslipId);

        // Vérifier les permissions (employé ne peut voir que ses propres fiches)
        if ($currentUser->id !== $payslip->employe_id &&
            !$currentUser->hasRole(['admin', 'rh', 'directeur'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Accès refusé.'], 403)
            );
        }

        if (!$payslip->document_path) {
            throw new \InvalidArgumentException('Fiche de paie non trouvée.');
        }

        return [
            $payslip->document_path,
            'payslips',
            "bulletin_{$payslip->annee}_{$payslip->mois}.pdf"
        ];
    }

    private function getDocumentPath(int $id, $currentUser): array
    {
        // Logique générique pour les documents
        // À adapter selon le type de document spécifique
        throw new \InvalidArgumentException('Type document générique non implémenté.');
    }

    private function canUploadPhoto($user, User $targetUser): bool
    {
        // L'utilisateur peut modifier sa propre photo
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Admin/RH peuvent modifier toutes les photos
        return $user->hasRole(['admin', 'rh']);
    }

    private function canDeleteFile($user, string $disk): bool
    {
        // Seuls admin/RH peuvent supprimer des fichiers
        return $user->hasRole(['admin', 'rh']);
    }
}
