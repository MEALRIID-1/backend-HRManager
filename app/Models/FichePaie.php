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
        'salaire_brut' => 'float',
        'salaire_net' => 'float',
        'heures_travaillees' => 'float',
        'heures_supplementaires' => 'float',
        'montant_heures_sup' => 'float',
        'prime_anciennete' => 'float',
        'prime_productivite' => 'float',
        'prime_autres' => 'float',
        'total_cotisations' => 'float',
        'total_retenues' => 'float',
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

}
