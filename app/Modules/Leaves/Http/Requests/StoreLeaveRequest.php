<?php

namespace App\Modules\Leaves\Http\Requests;

use App\Rules\CheckLeaveBalance;
use App\Rules\CheckLeaveOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request pour créer une demande de congé.
 */
class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $employeId = auth()->id();

        return [
            'type' => ['required', 'string', Rule::in([
                'conge_paye', 'rtt', 'conge_sans_solde', 'maladie', 'formation', 'maternite', 'paternite'
            ])],
            'date_debut' => 'required|date|before_or_equal:date_fin',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'raison' => 'nullable|string|max:500',
            'commentaire' => 'nullable|string|max:1000',
            
            // Règles personnalisées
            'solde_suffisant' => [new CheckLeaveBalance($employeId)],
            'pas_chevauchement' => [new CheckLeaveOverlap($employeId)],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'type de congé',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'raison' => 'raison',
            'commentaire' => 'commentaire',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de congé est obligatoire.',
            'type.in' => 'Le type de congé n\'est pas valide.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.before_or_equal' => 'La date de début doit être avant ou égale à la date de fin.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'solde_suffisant' => 'Solde de congés insuffisant pour cette demande.',
            'pas_chevauchement' => 'Vous avez déjà une demande de congé sur cette période.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // S'assurer que la raison est obligatoire pour certains types
        if (in_array($this->input('type'), ['maladie', 'formation', 'maternite', 'paternite'])) {
            $this->merge(['raison_required' => true]);
        }
    }
}
