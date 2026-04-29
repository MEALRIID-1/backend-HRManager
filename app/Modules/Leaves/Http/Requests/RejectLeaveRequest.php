<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour refuser une demande de congé.
 */
class RejectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conge = $this->route('leave');
        $user = auth()->user();

        // Mêmes permissions qu'ApproveLeaveRequest
        return $conge->enAttenteValidation() && (
            $user->hasRole(['admin', 'directeur']) ||
            ($user->hasRole('rh') && $conge->etat === 'valide_manager') ||
            ($user->hasRole('manager') && $conge->etat === 'soumis' && $conge->employe->manager_id === $user->id)
        );
    }

    public function rules(): array
    {
        return [
            'motif' => 'required|string|min:5|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'motif' => 'motif du refus',
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du refus est obligatoire.',
            'motif.min' => 'Le motif du refus doit contenir au moins 5 caractères.',
            'motif.max' => 'Le motif du refus ne doit pas dépasser 500 caractères.',
        ];
    }
}
