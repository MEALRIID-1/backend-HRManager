<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfilRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'telephone' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:500'],
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
            'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 255 caractères.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'telephone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'adresse.max' => 'L\'adresse ne doit pas dépasser 500 caractères.',
        ];
    }
}
