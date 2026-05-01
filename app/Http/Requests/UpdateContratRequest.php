<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
            'etat' => ['nullable', 'string', 'in:actif,termine'],
            'salaire_base' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de contrat est requis.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'salaire_base.numeric' => 'Le salaire de base doit être un nombre.',
        ];
    }
}
