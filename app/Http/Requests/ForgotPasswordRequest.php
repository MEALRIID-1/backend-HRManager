<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.exists' => 'Aucun compte trouvé avec cette adresse email.',
        ];
    }
}
