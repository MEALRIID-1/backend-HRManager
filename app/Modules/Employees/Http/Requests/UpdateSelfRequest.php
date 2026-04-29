<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour la mise à jour de son propre profil (employé).
 */
class UpdateSelfRequest extends FormRequest
{
    /**
     * Déterminer si l'utilisateur est autorisé.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Préparer les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name' => 'sometimes|required|string|max:255|regex:/^[\pL\s\-\'.]+$/u',
            'email' => 'sometimes|required|email|unique:users,email,' . $userId . '|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'telephone' => 'nullable|string|max:20|regex:/^[0-9\+\s\-\(\)]+$/',
            'adresse' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    /**
     * Noms des attributs.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
            'telephone' => 'téléphone',
            'adresse' => 'adresse',
            'photo' => 'photo',
        ];
    }

    /**
     * Messages d'erreur en français.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, espaces et tirets.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'telephone.regex' => 'Le format du téléphone est invalide.',
            'photo.image' => 'La photo doit être une image.',
            'photo.mimes' => 'La photo doit être au format JPEG ou PNG.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }
}
