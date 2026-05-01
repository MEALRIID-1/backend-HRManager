<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCongeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'required', 'date', 'after_or_equal:date_debut'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de congé est requis.',
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.required' => 'La date de fin est requise.',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
        ];
    }
}
