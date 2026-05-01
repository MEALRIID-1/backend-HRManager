<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrat extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'contrats';

    protected $fillable = [
        'user_id',
        'type',
        'date_debut',
        'date_fin',
        'statut',
        'salaire_brut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'salaire_brut' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope pour les contrats actifs.
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Scope pour les contrats expirant bientôt.
     */
    public function scopeExpirantBientot($query, int $jours = 30)
    {
        return $query->where('statut', 'actif')
                     ->whereNotNull('date_fin')
                     ->whereDate('date_fin', '<=', now()->addDays($jours))
                     ->whereDate('date_fin', '>=', now());
    }

    /**
     * Accessor pour employe_id (alias pour user_id)
     */
    public function getEmployeIdAttribute()
    {
        return $this->user_id;
    }
}
