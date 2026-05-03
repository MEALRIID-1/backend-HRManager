<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFichePaieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employe_id' => ['required', 'exists:users,id'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'salaire_base' => ['required', 'numeric', 'min:0'],
            'heures_sup' => ['nullable', 'numeric', 'min:0'],
            'absences' => ['nullable', 'integer', 'min:0'],
            'statut' => ['nullable', 'string', 'in:brouillon,generee,validee,payee'],
        ];
    }

    public function messages(): array
    {
        return [
            'employe_id.required' => 'L\'employé est obligatoire.',
            'employe_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'mois.required' => 'Le mois est obligatoire.',
            'mois.integer' => 'Le mois doit être un nombre entre 1 et 12.',
            'mois.min' => 'Le mois doit être compris entre 1 et 12.',
            'mois.max' => 'Le mois doit être compris entre 1 et 12.',
            'annee.required' => 'L\'année est obligatoire.',
            'annee.integer' => 'L\'année doit être un nombre entier.',
            'annee.min' => 'L\'année doit être comprise entre 2000 et 2100.',
            'annee.max' => 'L\'année doit être comprise entre 2000 et 2100.',
            'salaire_base.required' => 'Le salaire de base est obligatoire.',
            'salaire_base.numeric' => 'Le salaire de base doit être un nombre.',
            'salaire_base.min' => 'Le salaire de base ne peut pas être négatif.',
            'heures_sup.numeric' => 'Les heures supplémentaires doivent être un nombre.',
            'heures_sup.min' => 'Les heures supplémentaires ne peuvent pas être négatives.',
            'absences.integer' => 'Les absences doivent être un nombre entier.',
            'absences.min' => 'Les absences ne peuvent pas être négatives.',
            'statut.in' => 'Le statut doit être brouillon, generee, validee ou payee.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Conversion employe_id → user_id
        if ($this->has('employe_id') && !$this->has('user_id')) {
            $this->merge(['user_id' => $this->employe_id]);
        }
    }
}