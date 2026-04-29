<?php

namespace App\Modules\Contracts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('terminate-contracts');
    }

    public function rules(): array
    {
        $contrat = $this->route('contract');

        return [
            'motif_terminaison' => 'required|string|min:10|max:500',
            'date_terminaison' => 'required|date|after_or_equal:date_debut|before_or_equal:today',
            'notifier_employe' => 'boolean',
            'documents_rendus' => 'nullable|array',
        ];
    }

    public function attributes(): array
    {
        return [
            'motif_terminaison' => 'motif de terminaison',
            'date_terminaison' => 'date de terminaison',
            'notifier_employe' => 'notifier l\'employé',
            'documents_rendus' => 'documents rendus',
        ];
    }

    public function messages(): array
    {
        return [
            'motif_terminaison.required' => 'Le motif de terminaison est obligatoire.',
            'motif_terminaison.min' => 'Le motif doit contenir au moins 10 caractères.',
            'motif_terminaison.max' => 'Le motif ne doit pas dépasser 500 caractères.',
            'date_terminaison.required' => 'La date de terminaison est obligatoire.',
            'date_terminaison.date' => 'La date de terminaison doit être une date valide.',
            'date_terminaison.after_or_equal' => 'La date de terminaison doit être postérieure à la date de début du contrat.',
            'date_terminaison.before_or_equal' => 'La date de terminaison ne peut pas être dans le futur.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Par défaut, notifier l'employé
        if (!$this->has('notifier_employe')) {
            $this->merge(['notifier_employe' => true]);
        }
    }
}
