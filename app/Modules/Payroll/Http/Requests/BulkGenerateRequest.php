<?php

namespace App\Modules\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour générer des fiches de paie en masse.
 */
class BulkGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-payslips') || $this->user()->hasRole(['rh', 'admin']);
    }

    public function rules(): array
    {
        return [
            'mois' => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2000|max:' . (now()->year + 1),
            'employe_ids' => 'nullable|array',
            'employe_ids.*' => 'integer|exists:users,id',
            'exclure_inactifs' => 'boolean',
            'date_paie' => 'nullable|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'mois' => 'mois',
            'annee' => 'année',
            'employe_ids' => 'liste des employés',
            'employe_ids.*' => 'employé',
            'exclure_inactifs' => 'exclure les employés inactifs',
            'date_paie' => 'date de paie',
        ];
    }

    public function messages(): array
    {
        return [
            'mois.required' => 'Le mois est obligatoire.',
            'mois.between' => 'Le mois doit être compris entre 1 et 12.',
            'annee.required' => 'L\'année est obligatoire.',
            'annee.min' => 'L\'année doit être supérieure ou égale à 2000.',
            'employe_ids.array' => 'La liste des employés doit être un tableau.',
            'employe_ids.*.exists' => 'Un employé sélectionné n\'existe pas.',
        ];
    }

    /**
     * Préparer les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        // Si aucun employé spécifié, inclure tous les actifs
        if (!$this->has('employe_ids')) {
            $this->merge(['inclure_tous' => true]);
        }

        // Par défaut, exclure les inactifs
        if (!$this->has('exclure_inactifs')) {
            $this->merge(['exclure_inactifs' => true]);
        }
    }
}
