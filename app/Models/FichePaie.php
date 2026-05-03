<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FichePaie extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fiches_paie';

    protected $fillable = [
        'user_id',
        'periode',
        'date_emission',
        'salaire_brut',
        'salaire_net',
        'heures_travaillees',
        'heures_supplementaires',
        'absences',
        'montant_heures_sup',
        'prime_anciennete',
        'prime_productivite',
        'prime_autres',
        'total_cotisations',
        'total_retenues',
        'document_path',
        'statut',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'salaire_brut' => 'decimal:2',
        'salaire_net' => 'decimal:2',
        'heures_travaillees' => 'decimal:2',
        'heures_supplementaires' => 'decimal:2',
        'absences' => 'decimal:2',
        'montant_heures_sup' => 'decimal:2',
        'prime_anciennete' => 'decimal:2',
        'prime_productivite' => 'decimal:2',
        'prime_autres' => 'decimal:2',
        'total_cotisations' => 'decimal:2',
        'total_retenues' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Accessor pour employe_id (alias pour user_id)
     */
    public function getEmployeIdAttribute()
    {
        return $this->user_id;
    }

    /**
     * Récupérer le mois à partir de la période
     */
    public function getMoisAttribute(): int
    {
        return (int) explode('-', $this->periode)[1];
    }

    /**
     * Récupérer l'année à partir de la période
     */
    public function getAnneeAttribute(): int
    {
        return (int) explode('-', $this->periode)[0];
    }
}