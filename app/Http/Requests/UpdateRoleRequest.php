<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('id');

        return [
            'nom' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'nom')->ignore($roleId),
            ],
            'description' => ['nullable', 'string'],
            'niveau_validation' => ['sometimes', 'integer', 'min:0', 'max:3'],
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
            'nom.unique' => 'Ce nom de rôle existe déjà.',
            'niveau_validation.integer' => 'Le niveau de validation doit être un nombre entier.',
            'niveau_validation.min' => 'Le niveau de validation doit être compris entre 0 et 3.',
            'niveau_validation.max' => 'Le niveau de validation doit être compris entre 0 et 3.',
            'permissions.*.exists' => 'Une ou plusieurs permissions sont invalides.',
        ];
    }
}
