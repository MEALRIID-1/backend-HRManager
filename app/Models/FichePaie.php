<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichePaie extends Model
{
    use HasFactory;

    protected $table = 'fiches_paie';

    protected $fillable = [
        'employe_id',
        'mois',
        'annee',
        'montant',
        'etat',
    ];

    protected $casts = [
        'mois' => 'integer',
        'annee' => 'integer',
        'montant' => 'decimal:2',
    ];

    /**
     * Employé lié à la fiche de paie.
     */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /**
     * Période formatée.
     */
    public function getPeriodeAttribute(): string
    {
        $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        return $mois[$this->mois - 1] . ' ' . $this->annee;
    }
}
