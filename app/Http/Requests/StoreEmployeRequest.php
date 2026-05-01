<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'string',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at')
            ],
            'departement' => ['nullable', 'string', 'max:100'],
            'date_embauche' => ['nullable', 'date', 'before_or_equal:today'],
            'iban' => ['nullable', 'string', 'max:34'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est requis.',
            'nom.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'prenom.required' => 'Le prénom est requis.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 100 caractères.',
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'date_embauche.before_or_equal' => 'La date d\'embauche ne peut pas être dans le futur.',
            'role_ids.*.exists' => 'Un des rôles sélectionnés n\'existe pas.',
        ];
    }
}
