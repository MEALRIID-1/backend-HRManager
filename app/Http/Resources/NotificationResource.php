<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource pour le modèle Notification.
 * 
 * Exemple de réponse JSON:
 * {
 *   "id": "notif-uuid",
 *   "type": "conge_approved",
 *   "title": "Congé approuvé",
 *   "message": "Votre demande de congé a été approuvée",
 *   "icon": "calendar-check",
 *   "color_class": "success",
 *   "priority": "normal",
 *   "data": {"conge_id": 5, ...},
 *   "read_at": null,
 *   "action_url": "/leaves/5",
 *   "created_at": "2024-03-20T14:30:00+00:00",
 *   "_links": {"self": "/api/notifications/notif-uuid"}
 * }
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Type et affichage
            'type' => $this->type,
            'title' => $this->title ?? $this->getTitleFromType(),
            'message' => $this->message ?? ($this->data['message'] ?? null),
            
            // UI
            'icon' => $this->icon ?? $this->getIconFromType(),
            'color_class' => $this->color_class ?? $this->getColorFromType(),
            'priority' => $this->priority ?? 'normal',
            
            // Données associées
            'data' => $this->data,
            
            // Action
            'action_url' => $this->action_url ?? $this->getDefaultActionUrl(),
            'action_text' => $this->action_text ?? 'Voir',
            
            // État de lecture
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            
            // Relations
            'notifiable' => $this->when($this->notifiable_type && $this->notifiable_id, [
                'type' => $this->notifiable_type,
                'id' => $this->notifiable_id,
            ]),
            
            // Dates
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Liens HATEOAS
            '_links' => [
                'self' => "/api/notifications/{$this->id}",
                'mark_as_read' => $this->when($this->read_at === null, "/api/notifications/{$this->id}/read"),
            ],
            
            '_meta' => [
                'resource_type' => 'notification',
                'time_ago' => $this->created_at?->diffForHumans(),
            ],
        ];
    }

    private function getTitleFromType(): string
    {
        $titles = [
            'conge_submitted' => 'Nouvelle demande de congé',
            'conge_approved' => 'Congé approuvé',
            'conge_rejected' => 'Congé refusé',
            'conge_cancelled' => 'Congé annulé',
            'contract_expiring' => 'Contrat expirant',
            'contract_terminated' => 'Contrat terminé',
            'payslip_available' => 'Fiche de paie disponible',
            'employee_created' => 'Nouvel employé',
            'validation_required' => 'Validation requise',
            'system' => 'Notification système',
        ];
        return $titles[$this->type] ?? 'Notification';
    }

    private function getIconFromType(): string
    {
        $icons = [
            'conge_submitted' => 'calendar-clock',
            'conge_approved' => 'calendar-check',
            'conge_rejected' => 'calendar-x',
            'conge_cancelled' => 'calendar-off',
            'contract_expiring' => 'file-warning',
            'contract_terminated' => 'file-x',
            'payslip_available' => 'file-dollar-sign',
            'employee_created' => 'user-plus',
            'validation_required' => 'alert-circle',
            'system' => 'info',
        ];
        return $icons[$this->type] ?? 'bell';
    }

    private function getColorFromType(): string
    {
        $colors = [
            'conge_approved' => 'success',
            'payslip_available' => 'success',
            'conge_rejected' => 'danger',
            'contract_terminated' => 'danger',
            'conge_submitted' => 'warning',
            'validation_required' => 'warning',
            'contract_expiring' => 'orange',
            'system' => 'info',
        ];
        return $colors[$this->type] ?? 'default';
    }

    private function getDefaultActionUrl(): ?string
    {
        if (isset($this->data['conge_id'])) {
            return "/leaves/{$this->data['conge_id']}";
        }
        if (isset($this->data['contrat_id'])) {
            return "/contracts/{$this->data['contrat_id']}";
        }
        if (isset($this->data['fiche_paie_id'])) {
            return "/payslips/{$this->data['fiche_paie_id']}";
        }
        return null;
    }
}
