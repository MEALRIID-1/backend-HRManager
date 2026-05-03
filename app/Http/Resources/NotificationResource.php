<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(), // ✅ Utilise une méthode d'instance
            'titre' => $this->titre,
            'message' => $this->message,
            'action_url' => $this->action_url,
            'icone' => $this->icone,
            'lu' => $this->lu,
            'date_lecture' => $this->date_lecture?->format('Y-m-d H:i:s'),
            'data' => $this->data,
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    // ✅ Ajoute cette méthode dans le Resource
    protected function getTypeLabel(): string
    {
        $types = [
            'info' => 'Information',
            'success' => 'Succès',
            'warning' => 'Alerte',
            'error' => 'Erreur',
            'danger' => 'Danger',
            'absence' => 'Absence',
            'conge' => 'Congé',
            'paie' => 'Fiche de paie',
            'contrat' => 'Contrat',
            'entretien' => 'Entretien',
            'formation' => 'Formation',
            'document' => 'Document',
        ];

        return $types[$this->type] ?? $this->type;
    }
}