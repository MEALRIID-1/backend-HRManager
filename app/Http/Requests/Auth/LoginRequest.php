<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ];
    }
}
