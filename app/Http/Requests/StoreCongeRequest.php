<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conge;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCongeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50', 'in:conge_paye,conge_sans_solde,rtt,maladie,formation'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de congé est requis.',
            'type.in' => 'Le type de congé doit être l\'un des suivants : conge_paye, conge_sans_solde, rtt, maladie, formation.',
            'date_debut.required' => 'La date de début est requise.',
            'date_debut.after_or_equal' => 'La date de début doit être aujourd\'hui ou plus tard.',
            'date_fin.required' => 'La date de fin est requise.',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
            'commentaire.max' => 'Le commentaire ne doit pas dépasser 500 caractères.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Vérifier le chevauchement avec un congé existant
            if ($this->hasChevauchement()) {
                $validator->errors()->add('date_debut', 'Vous avez déjà un congé sur cette période.');
            }
        });
    }

    /**
     * Vérifie s'il y a un chevauchement avec un congé existant.
     */
    private function hasChevauchement(): bool
    {
        $userId = auth()->id();
        $dateDebut = $this->input('date_debut');
        $dateFin = $this->input('date_fin');

        if (!$dateDebut || !$dateFin || !$userId) {
            return false;
        }

        $dateDebut = Carbon::parse($dateDebut);
        $dateFin = Carbon::parse($dateFin);

        return Conge::byEmploye($userId)
            ->where(function ($query) use ($dateDebut, $dateFin) {
                $query->whereBetween('date_debut', [$dateDebut, $dateFin])
                    ->orWhereBetween('date_fin', [$dateDebut, $dateFin])
                    ->orWhere(function ($q) use ($dateDebut, $dateFin) {
                        $q->where('date_debut', '<=', $dateDebut)
                            ->where('date_fin', '>=', $dateFin);
                    });
            })
            ->whereNotIn('statut', ['refuse'])
            ->whereNull('deleted_at')
            ->exists();
    }
}
