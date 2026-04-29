<?php

namespace App\Modules\Contracts\Http\Requests;

use App\Models\Contrat;
use App\Rules\CheckNoContractOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request pour la mise à jour d'un contrat.
 */
class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-contracts');
    }

    public function rules(): array
    {
        $smig = config('hr.smig', 1762.00);
        $types = [Contrat::TYPE_CDI, Contrat::TYPE_CDD, Contrat::TYPE_STAGE, Contrat::TYPE_ALTERNANCE];
        
        $contrat = $this->route('contract');
        $employeId = $contrat?->employe_id ?? $this->input('employe_id');

        $rules = [
            'type' => ['sometimes', 'in:' . implode(',', $types)],
            'date_fin' => 'sometimes|nullable|date',
            'salaire' => "sometimes|nullable|numeric|min:{$smig}|regex:/^\d+(\.\d{1,2})?$/",
            'fonction' => 'sometimes|nullable|string|max:255',
            'motif_modification' => 'required_with:salaire,type,date_fin|string|max:500',
            'date_effet' => 'nullable|date',
            
            // Règle personnalisée pour vérifier le chevauchement (si dates modifiées)
            'no_overlap' => $this->has('date_debut') || $this->has('date_fin') 
                ? [new CheckNoContractOverlap($employeId, $contrat?->id)] 
                : 'nullable',
        ];

        // Vérifier que date_fin reste après date_debut
        if ($this->has('date_fin') && $contrat) {
            $rules['date_fin'] .= '|after_or_equal:' . $contrat->date_debut->format('Y-m-d');
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'type' => 'type de contrat',
            'date_fin' => 'date de fin',
            'salaire' => 'salaire',
            'fonction' => 'fonction',
            'motif_modification' => 'motif de modification',
            'date_effet' => 'date d\'effet',
        ];
    }

    public function messages(): array
    {
        $smig = config('hr.smig', 1762.00);
        
        return [
            'type.in' => 'Le type de contrat doit être CDI, CDD, Stage ou Alternance.',
            'date_fin.date' => 'La date de fin doit être une date valide.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure à la date de début.',
            'salaire.numeric' => 'Le salaire doit être un nombre.',
            'salaire.min' => "Le salaire doit être supérieur ou égal au SMIG ({$smig} €).",
            'salaire.regex' => 'Le salaire ne peut avoir que 2 décimales maximum.',
            'motif_modification.required_with' => 'Le motif est requis lors d\'une modification de salaire, type ou date de fin.',
            'motif_modification.max' => 'Le motif ne doit pas dépasser 500 caractères.',
            'no_overlap' => 'Un contrat existe déjà pour cet employé sur cette période.',
        ];
    }
}
