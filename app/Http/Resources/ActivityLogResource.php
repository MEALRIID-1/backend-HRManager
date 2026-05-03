<?php
declare(strict_types=1);
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user'             => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'nom'    => $this->user->nom ?? 'Système',
                'prenom' => $this->user->prenom ?? '',
                'email'  => $this->user->email ?? '',
            ], [
                'id'     => null,
                'nom'    => 'Système',
                'prenom' => '',
                'email'  => '',
            ]),
            'action'           => $this->action,
            'action_label'     => $this->getActions()[$this->action] ?? $this->action,
            'entity_name'      => $this->entity_name,
            'module'           => $this->module ?? '',
            'module_label'     => $this->getModules()[$this->module ?? ''] ?? $this->module ?? '',
            'ip_address'       => $this->ip_address,
            'timestamp'        => $this->created_at?->toISOString() ?? now()->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans() ?? '',
        ];
    }

    private function getActions(): array
    {
        return [
            'create'              => 'Création',
            'update'              => 'Modification',
            'delete'              => 'Suppression',
            'restore'             => 'Restauration',
            'force_delete'        => 'Suppression définitive',
            'upload_photo'        => 'Upload photo',
            'validation_approuve' => 'Validation approuvée',
            'validation_refuse'   => 'Validation refusée',
            'super_validation'    => 'Super validation',
            'login'               => 'Connexion',
            'logout'              => 'Déconnexion',
        ];
    }

    private function getModules(): array
    {
        return [
            'employes'      => 'Employés',
            'contrats'      => 'Contrats',
            'conges'        => 'Congés',
            'notifications' => 'Notifications',
            'auth'          => 'Authentification',
        ];
    }
}