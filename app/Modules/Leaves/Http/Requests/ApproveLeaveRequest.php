<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour approuver une demande de congé.
 */
class ApproveLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conge = $this->route('conge');
        $user = auth()->user();

        // Vérifier que l'utilisateur peut approuver ce congé selon le workflow
        return $conge->enAttenteValidation() && (
            $user->hasRole(['admin', 'directeur']) ||
            ($user->hasRole('rh') && $conge->etat === 'valide_manager') ||
            ($user->hasRole('manager') && $conge->etat === 'soumis' && $conge->employe->manager_id === $user->id)
        );
    }

    public function rules(): array
    {
        return [
            'commentaire' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'commentaire' => 'commentaire de validation',
        ];
    }

    public function messages(): array
    {
        return [
            'commentaire.max' => 'Le commentaire ne doit pas dépasser 500 caractères.',
        ];
    }
}
