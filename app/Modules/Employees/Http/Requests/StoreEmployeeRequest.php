<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour la création d'un employé
 */
class StoreEmployeeRequest extends FormRequest
{
    /**
     * Déterminer si l'utilisateur est autorisé.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create-employees');
    }

    /**
     * Préparer les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        // Normaliser l'email en minuscules
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }

        // Supprimer les espaces de l'IBAN
        if ($this->has('iban')) {
            $this->merge([
                'iban' => strtoupper(str_replace(' ', '', $this->iban)),
            ]);
        }
    }

    /**
     * Règles de validation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|regex:/^[\pL\s\-\'.]+$/u',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'telephone' => 'nullable|string|max:20|regex:/^[0-9\+\s\-\(\)]+$/',
            'adresse' => 'nullable|string|max:500',
            'date_embauche' => 'required|date|before_or_equal:today',
            'date_depart' => 'nullable|date|after_or_equal:date_embauche',
            'departement_id' => 'nullable|integer|exists:departements,id',
            'manager_id' => 'nullable|integer|exists:users,id|different:id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'est_actif' => 'boolean',
            'iban' => 'nullable|string|max:34|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$/',
            'numero_securite_sociale' => 'nullable|string|max:15|regex:/^[0-9]{13,15}$/',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ];
    }

    /**
     * Noms des attributs pour les messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
            'telephone' => 'téléphone',
            'adresse' => 'adresse',
            'date_embauche' => 'date d\'embauche',
            'date_depart' => 'date de départ',
            'departement_id' => 'département',
            'manager_id' => 'manager',
            'photo' => 'photo',
            'est_actif' => 'statut actif',
            'iban' => 'IBAN',
            'numero_securite_sociale' => 'numéro de sécurité sociale',
            'roles' => 'rôles',
        ];
    }

    /**
     * Messages d'erreur en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'telephone.max' => 'Le téléphone ne doit pas dépasser 20 caractères.',
            'adresse.max' => 'L\'adresse ne doit pas dépasser 500 caractères.',
            'date_embauche.required' => 'La date d\'embauche est obligatoire.',
            'date_embauche.date' => 'La date d\'embauche doit être une date valide.',
            'departement_id.exists' => 'Le département sélectionné n\'existe pas.',
            'manager_id.exists' => 'Le manager sélectionné n\'existe pas.',
            'photo.image' => 'La photo doit être une image.',
            'photo.mimes' => 'La photo doit être au format JPEG, PNG, JPG ou GIF.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }
}
