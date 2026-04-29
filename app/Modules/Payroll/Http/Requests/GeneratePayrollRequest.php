<?php

namespace App\Modules\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request pour générer une fiche de paie.
 */
class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-payslips') || $this->user()->hasRole(['rh', 'admin']);
    }

    public function rules(): array
    {
        return [
            'employe_id' => 'required|integer|exists:users,id',
            'mois' => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2000|max:' . (now()->year + 1),
            'salaire_brut' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'heures_travaillees' => 'nullable|numeric|min:0|max:200',
            'jours_travailles' => 'nullable|integer|min:0|max:31',
            'date_paie' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'employe_id' => 'employé',
            'mois' => 'mois',
            'annee' => 'année',
            'salaire_brut' => 'salaire brut',
            'heures_travaillees' => 'heures travaillées',
            'jours_travailles' => 'jours travaillés',
            'date_paie' => 'date de paie',
            'notes' => 'notes',
        ];
    }

    public function messages(): array
    {
        return [
            'employe_id.required' => 'L\'employé est obligatoire.',
            'employe_id.exists' => 'L\'employé sélectionné n\'existe pas.',
            'mois.required' => 'Le mois est obligatoire.',
            'mois.between' => 'Le mois doit être compris entre 1 et 12.',
            'annee.required' => 'L\'année est obligatoire.',
            'annee.min' => 'L\'année doit être supérieure ou égale à 2000.',
            'annee.max' => 'L\'année ne peut pas dépasser l\'année prochaine.',
            'salaire_brut.required' => 'Le salaire brut est obligatoire.',
            'salaire_brut.regex' => 'Le salaire ne peut avoir que 2 décimales maximum.',
            'heures_travaillees.max' => 'Les heures travaillées ne peuvent pas excéder 200.',
            'jours_travailles.max' => 'Les jours travaillés ne peuvent pas excéder 31.',
        ];
    }
}
