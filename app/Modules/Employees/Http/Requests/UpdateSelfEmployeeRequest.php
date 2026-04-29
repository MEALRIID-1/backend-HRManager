<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request pour la mise à jour de son propre profil par un employé
 */
class UpdateSelfEmployeeRequest extends FormRequest
{
    /**
     * Déterminer si l'utilisateur est autorisé.
     */
    public function authorize(): bool
    {
        // L'utilisateur peut modifier son propre profil
        $employeeId = $this->route('employee')?->id ?? $this->input('user_id');
        return $this->user()->id == $employeeId;
    }

    /**
     * Règles de validation - champs restreints pour l'auto-modification.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // Les champs suivants ne peuvent pas être modifiés par l'employé lui-même
            // name, email, password, date_embauche, departement_id, manager_id, est_actif
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
            'telephone.max' => 'Le téléphone ne doit pas dépasser 20 caractères.',
            'adresse.max' => 'L\'adresse ne doit pas dépasser 500 caractères.',
            'photo.image' => 'La photo doit être une image.',
            'photo.mimes' => 'La photo doit être au format JPEG, PNG, JPG ou GIF.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }

    /**
     * Préparer les données pour validation.
     */
    protected function prepareForValidation(): void
    {
        // S'assurer que l'user_id correspond à l'utilisateur authentifié
        $this->merge([
            'user_id' => $this->user()->id,
        ]);
    }
}
