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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50', 'in:CDI,CDD,Stage,Alternance,Freelance'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut', 'required_if:type,CDD,Stage,Alternance'],
            'statut' => ['nullable', 'string', 'in:actif,termine,renouvele'],
            'salaire_brut' => ['nullable', 'numeric', 'min:0'],  // ✅ salaire_brut
            'poste' => ['nullable', 'string', 'max:100'],
            'departement' => ['nullable', 'string', 'max:100'],
            'forcer' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'employé est requis.',
            'user_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'type.required' => 'Le type de contrat est requis.',
            'type.in' => 'Le type de contrat doit être : CDI, CDD, Stage, Alternance ou Freelance.',
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'date_fin.required_if' => 'La date de fin est requise pour les contrats de type CDD, Stage ou Alternance.',
            'salaire_brut.numeric' => 'Le salaire doit être un nombre.',
            'salaire_brut.min' => 'Le salaire doit être supérieur ou égal à 0.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->input('forcer') && $this->hasContratActif()) {
                $validator->errors()->add('user_id', 'Un contrat actif existe déjà pour cet employé.');
            }
        });
    }

    /**
     * Vérifie s'il existe un contrat actif pour l'employé.
     */
    private function hasContratActif(): bool
    {
        $userId = $this->input('user_id');

        if (!$userId) {
            return false;
        }

        return Contrat::where('user_id', $userId)
            ->where('statut', 'actif')
            ->whereNull('deleted_at')
            ->exists();
    }
}