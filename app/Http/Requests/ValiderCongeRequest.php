<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValiderCongeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approuve,refuse'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est requise.',
            'decision.in' => 'La décision doit être "approuve" ou "refuse".',
            'motif.required' => 'Le motif est obligatoire pour valider ou refuser.',
            'motif.min' => 'Le motif doit contenir au moins 5 caractères.',
            'motif.max' => 'Le motif ne doit pas dépasser 1000 caractères.',
        ];
    }
}
