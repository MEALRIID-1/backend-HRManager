<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Contrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employe_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50', 'in:CDI,CDD,Stage,Alternance'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut', 'required_if:type,CDD,Stage,Alternance'],
            'etat' => ['nullable', 'string', 'in:actif,termine'],
            'salaire_base' => ['nullable', 'numeric', 'min:0'],
            'poste' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'forcer' => ['nullable', 'boolean'], // Force création même si contrat actif existe
        ];
    }

    public function messages(): array
    {
        return [
            'employe_id.required' => 'L\'employé est requis.',
            'employe_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'type.required' => 'Le type de contrat est requis.',
            'type.in' => 'Le type de contrat doit être : CDI, CDD, Stage ou Alternance.',
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'date_fin.required_if' => 'La date de fin est requise pour les contrats de type CDD, Stage ou Alternance.',
            'salaire_base.numeric' => 'Le salaire de base doit être un nombre.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Vérifier s'il existe déjà un contrat actif pour cet employé
            if (!$this->input('forcer') && $this->hasContratActif()) {
                $validator->errors()->add('employe_id', 'Un contrat actif existe déjà pour cet employé. Utilisez l\'option "forcer" pour créer quand même.');
            }
        });
    }

    /**
     * Vérifie s'il existe un contrat actif pour l'employé.
     */
    private function hasContratActif(): bool
    {
        $employeId = $this->input('employe_id');

        if (!$employeId) {
            return false;
        }

        return Contrat::where('user_id', $employeId)
            ->where('etat', 'actif')
            ->whereNull('deleted_at')
            ->exists();
    }
}
