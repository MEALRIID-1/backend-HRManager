<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'min:8'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'different:current_password',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*(),.?":{}|<>]/',
            ],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Le mot de passe actuel est requis.',
            'new_password.required' => 'Le nouveau mot de passe est requis.',
            'new_password.min' => 'Le nouveau mot de passe doit contenir au moins :min caractères.',
            'new_password.different' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
            'new_password.regex' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
            'new_password_confirmation.required' => 'La confirmation du mot de passe est requise.',
            'new_password_confirmation.same' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }
}
