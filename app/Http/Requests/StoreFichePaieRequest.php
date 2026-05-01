<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFichePaieRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employe_id' => ['required', 'exists:users,id'],
            'mois' => ['required', 'string', 'in:Janvier,Février,Mars,Avril,Mai,Juin,Juillet,Août,Septembre,Octobre,Novembre,Décembre'],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'salaire_base' => ['required', 'numeric', 'min:0'],
            'heures_sup' => ['nullable', 'numeric', 'min:0'],
            'absences' => ['nullable', 'integer', 'min:0'],
            'statut' => ['nullable', 'in:brouillon,finalisee,payee'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employe_id.required' => 'L\'employé est obligatoire.',
            'employe_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'mois.required' => 'Le mois est obligatoire.',
            'mois.in' => 'Le mois sélectionné n\'est pas valide.',
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
            'statut.in' => 'Le statut doit être brouillon, finalisee ou payee.',
        ];
    }
}
