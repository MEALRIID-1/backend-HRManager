<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validation extends Model
{
    use HasFactory;

    protected $table = 'validations';

    // Niveaux de validation
    public const NIVEAU_MANAGER = 1;
    public const NIVEAU_RH = 2;
    public const NIVEAU_DIRECTEUR = 3;

    // Actions possibles
    public const ACTION_APPROUVE = 'approuve';
    public const ACTION_REFUSE = 'refuse';

    protected $fillable = [
        'conge_id',
        'validateur_id',
        'niveau',
        'action',
        'motif',
        'date_validation',
    ];

    protected $casts = [
        'date_validation' => 'datetime',
    ];

    /**
     * Congé lié à cette validation.
     */
    public function conge(): BelongsTo
    {
        return $this->belongsTo(Conge::class, 'conge_id');
    }

    /**
     * Validateur (utilisateur qui a validé).
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }

    /**
     * Check si c'est une approbation.
     */
    public function estApprobation(): bool
    {
        return $this->action === self::ACTION_APPROUVE;
    }

    /**
     * Check si c'est un refus.
     */
    public function estRefus(): bool
    {
        return $this->action === self::ACTION_REFUSE;
    }

    /**
     * Get niveau en texte.
     */
    public function getNiveauLabelAttribute(): string
    {
        $labels = [
            self::NIVEAU_MANAGER => 'Manager',
            self::NIVEAU_RH => 'RH',
            self::NIVEAU_DIRECTEUR => 'Directeur',
        ];

        return $labels[$this->niveau] ?? 'Inconnu';
    }

    /**
     * Get action en texte.
     */
    public function getActionLabelAttribute(): string
    {
        return $this->action === self::ACTION_APPROUVE ? 'Approuvé' : 'Refusé';
    }
}
