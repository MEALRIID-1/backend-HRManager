<?php
namespace App\Modules\Leaves\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conge = $this->route('conge');
        $user = auth()->user();

        if (!$conge) return false;

        if ($user->hasRole(['admin', 'directeur'])) {
            return $conge->enAttenteValidation();
        }

        if ($user->hasRole('rh')) {
            return in_array($conge->etat, ['soumis', 'valide_manager']);
        }

        if ($user->hasRole('manager')) {
            return $conge->etat === 'soumis' &&
                   $conge->employe &&
                   $conge->employe->manager_id === $user->id;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'motif' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du refus est obligatoire.',
            'motif.min' => 'Le motif doit contenir au moins 5 caracteres.',
            'motif.max' => 'Le motif ne doit pas depasser 500 caracteres.',
        ];
    }
}