<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255', 'unique:roles,nom'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'niveau_validation' => ['required', 'integer', 'min:0', 'max:3'],
            'is_active' => ['boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
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
            'nom.required' => 'Le nom du rôle est obligatoire.',
            'nom.unique' => 'Ce nom de rôle existe déjà.',
            'niveau_validation.required' => 'Le niveau de validation est obligatoire.',
            'niveau_validation.integer' => 'Le niveau de validation doit être un nombre entier.',
            'niveau_validation.min' => 'Le niveau de validation doit être compris entre 0 et 3.',
            'niveau_validation.max' => 'Le niveau de validation doit être compris entre 0 et 3.',
            'permissions.*.exists' => 'Une ou plusieurs permissions sont invalides.',
        ];
    }
}
