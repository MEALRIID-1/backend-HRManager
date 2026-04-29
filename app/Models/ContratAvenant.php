<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratAvenant extends Model
{
    use HasFactory;

    protected $table = 'contrat_avenants';

    protected $fillable = [
        'contrat_id',
        'type_modification',
        'ancienne_valeur',
        'nouvelle_valeur',
        'motif',
        'date_effet',
        'created_by',
    ];

    protected $casts = [
        'ancienne_valeur' => 'array',
        'nouvelle_valeur' => 'array',
        'date_effet' => 'date',
    ];

    /**
     * Types de modification.
     */
    public const TYPE_SALAIRE = 'salaire';
    public const TYPE_TYPE = 'type';
    public const TYPE_DATE_FIN = 'date_fin';
    public const TYPE_FONCTION = 'fonction';
    public const TYPE_AUTRE = 'autre';

    /**
     * Contrat lié à l'avenant.
     */
    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }

    /**
     * Créateur de l'avenant.
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get type formatté.
     */
    public function getTypeFormateAttribute(): string
    {
        $labels = [
            self::TYPE_SALAIRE => 'Modification de salaire',
            self::TYPE_TYPE => 'Changement de type',
            self::TYPE_DATE_FIN => 'Modification date de fin',
            self::TYPE_FONCTION => 'Changement de fonction',
            self::TYPE_AUTRE => 'Autre modification',
        ];

        return $labels[$this->type_modification] ?? $this->type_modification;
    }
}
