<?php

namespace App\Modules\Contracts\Http\Requests;

use App\Models\Contrat;
use App\Rules\CheckNoContractOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-contracts');
    }

    public function rules(): array
    {
        $smig = config('hr.smig', 1762.00);
        $types = [Contrat::TYPE_CDI, Contrat::TYPE_CDD, Contrat::TYPE_STAGE, Contrat::TYPE_ALTERNANCE];
        $employeId = $this->input('employe_id');

        return [
            'employe_id' => 'required|integer|exists:users,id',
            'type' => ['required', 'string', Rule::in($types)],
            'date_debut' => 'required|date|before_or_equal:date_fin',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'salaire' => "required|numeric|min:{$smig}|regex:/^\d+(\.\d{1,2})?$/",
            'fonction' => 'nullable|string|max:255',
            'duree_periode_essai_jours' => 'nullable|integer|min:0|max:180',
            'no_overlap' => [new CheckNoContractOverlap($employeId)],
        ];
    }

    public function attributes(): array
    {
        return [
            'employe_id' => 'employé',
            'type' => 'type de contrat',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'salaire' => 'salaire',
            'fonction' => 'fonction',
            'duree_periode_essai_jours' => 'durée de la période d\'essai',
        ];
    }

    public function messages(): array
    {
        $smig = config('hr.smig', 1762.00);

        return [
            'employe_id.required' => 'L\'employé est obligatoire.',
            'employe_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'type.required' => 'Le type de contrat est obligatoire.',
            'type.in' => 'Le type de contrat doit être CDI, CDD, Stage ou Alternance.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.before_or_equal' => 'La date de début doit être avant ou égale à la date de fin.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'salaire.required' => 'Le salaire est obligatoire.',
            'salaire.min' => "Le salaire doit être supérieur ou égal au SMIG ({$smig} €).",
            'salaire.regex' => 'Le salaire ne peut avoir que 2 décimales maximum.',
            'no_overlap' => 'Un contrat existe déjà pour cet employé sur cette période.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'cdi') {
            $this->merge(['date_fin' => null]);
        }
    }
}
