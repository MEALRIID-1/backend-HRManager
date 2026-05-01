<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conge extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'conges';

    protected $fillable = [
        'user_id',
        'type',
        'date_debut',
        'date_fin',
        'nombre_jours',
        'statut',
        'niveau_validation',
        'motif',
        'commentaire',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class, 'conge_id', 'id');
    }

    /**
     * Scope pour filtrer par statut.
     */
    public function scopeByStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope pour filtrer par période.
     */
    public function scopeByPeriode($query, Carbon $debut, Carbon $fin)
    {
        return $query->where(function ($q) use ($debut, $fin) {
            $q->whereBetween('date_debut', [$debut, $fin])
              ->orWhereBetween('date_fin', [$debut, $fin])
              ->orWhere(function ($sq) use ($debut, $fin) {
                  $sq->where('date_debut', '<=', $debut)
                     ->where('date_fin', '>=', $fin);
              });
        });
    }

    /**
     * Scope pour filtrer par employé.
     */
    public function scopeByEmploye($query, int $employeId)
    {
        return $query->where('user_id', $employeId);
    }

    /**
     * Scope pour les congés en attente.
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope pour les congés que ce validateur peut/doit traiter.
     * N1 = Manager, N2 = RH, N3 = Admin
     */
    public function scopePourValidateur($query, User $validateur)
    {
        $niveauValidation = $validateur->roles->max('niveau_validation');

        return $query->where('statut', 'en_attente')
                     ->where('niveau_validation', '<=', $niveauValidation);
    }

    /**
     * Accessor pour employe_id (alias pour user_id)
     */
    public function getEmployeIdAttribute()
    {
        return $this->user_id;
    }
}
