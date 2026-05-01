<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ancien_mot_de_passe' => ['required', 'string', 'min:6'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'confirmed'],
            'nouveau_mot_de_passe_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'ancien_mot_de_passe.required' => 'Le mot de passe actuel est requis.',
            'ancien_mot_de_passe.min' => 'Le mot de passe actuel doit contenir au moins 6 caractères.',
            'nouveau_mot_de_passe.required' => 'Le nouveau mot de passe est requis.',
            'nouveau_mot_de_passe.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau_mot_de_passe.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
        ];
    }
}
