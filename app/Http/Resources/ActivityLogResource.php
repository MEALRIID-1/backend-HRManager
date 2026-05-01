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
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'user_name' => $this->user_name,
            'action' => $this->action,
            'action_label' => $this->getActions()[$this->action] ?? $this->action,
            'module' => $this->module,
            'module_label' => $this->getModules()[$this->module] ?? $this->module,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
