<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'conges';

    // ÉTATS DU WORKFLOW
    public const ETAT_BROUILLON = 'brouillon';
    public const ETAT_SOUMIS = 'soumis';
    public const ETAT_VALIDE_MANAGER = 'valide_manager';
    public const ETAT_VALIDE_RH = 'valide_rh';
    public const ETAT_APPROUVE = 'approuve';
    public const ETAT_REFUSE_MANAGER = 'refuse_manager';
    public const ETAT_REFUSE_RH = 'refuse_rh';
    public const ETAT_REFUSE_DIRECTEUR = 'refuse_directeur';
    public const ETAT_ANNULE = 'annule';

    // TYPES DE CONGÉS
    public const TYPE_CONGE_PAYE = 'conge_paye';
    public const TYPE_RTT = 'rtt';
    public const TYPE_CONGE_SANS_SOLDE = 'conge_sans_solde';
    public const TYPE_MALADIE = 'maladie';
    public const TYPE_FORMATION = 'formation';
    public const TYPE_MATERNITE = 'maternite';
    public const TYPE_PATERNITE = 'paternite';

    protected $fillable = [
        'employe_id',
        'type',
        'date_debut',
        'date_fin',
        'raison',
        'etat',
        'nombre_jours',
        'commentaire',
        'motif_annulation',
        'annule_par',
        'annule_le',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'nombre_jours' => 'integer',
        'annule_le' => 'datetime',
    ];

    /**
     * Employé lié au congé.
     */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /**
     * Validations liées au congé.
     */
    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class, 'conge_id')->orderBy('niveau');
    }

    /**
     * Validations avec validateurs.
     */
    public function validateurs(): HasMany
    {
        return $this->hasMany(Validation::class, 'conge_id')
            ->with('validateur')
            ->orderBy('niveau');
    }

    // ============================================================================
    // SCOPES PAR ÉTAT
    // ============================================================================

    public function scopeBrouillon(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_BROUILLON);
    }

    public function scopeSoumis(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_SOUMIS);
    }

    public function scopeValideManager(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_VALIDE_MANAGER);
    }

    public function scopeValideRH(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_VALIDE_RH);
    }

    public function scopeApprouves(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_APPROUVE);
    }

    public function scopeRefuses(Builder $query): Builder
    {
        return $query->whereIn('etat', [
            self::ETAT_REFUSE_MANAGER,
            self::ETAT_REFUSE_RH,
            self::ETAT_REFUSE_DIRECTEUR,
        ]);
    }

    public function scopeEnCoursValidation(Builder $query): Builder
    {
        return $query->whereIn('etat', [
            self::ETAT_SOUMIS,
            self::ETAT_VALIDE_MANAGER,
            self::ETAT_VALIDE_RH,
        ]);
    }

    public function scopeAnnules(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_ANNULE);
    }

    // ============================================================================
    // SCOPES PAR PÉRIODE
    // ============================================================================

    public function scopeParEmploye(Builder $query, int $employeId): Builder
    {
        return $query->where('employe_id', $employeId);
    }

    public function scopeParType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeParPeriode(Builder $query, Carbon $debut, Carbon $fin): Builder
    {
        return $query->where(function ($q) use ($debut, $fin) {
            $q->whereBetween('date_debut', [$debut, $fin])
              ->orWhereBetween('date_fin', [$debut, $fin])
              ->orWhere(function ($q) use ($debut, $fin) {
                  $q->where('date_debut', '<=', $debut)
                    ->where('date_fin', '>=', $fin);
              });
        });
    }

    public function scopeChevauche(Builder $query, int $employeId, Carbon $debut, Carbon $fin): Builder
    {
        return $query->where('employe_id', $employeId)
            ->whereNotIn('etat', [self::ETAT_REFUSE_MANAGER, self::ETAT_REFUSE_RH, self::ETAT_REFUSE_DIRECTEUR, self::ETAT_ANNULE])
            ->where(function ($q) use ($debut, $fin) {
                $q->whereBetween('date_debut', [$debut, $fin])
                  ->orWhereBetween('date_fin', [$debut, $fin])
                  ->orWhere(function ($q) use ($debut, $fin) {
                      $q->where('date_debut', '<=', $debut)
                        ->where('date_fin', '>=', $debut);
                  });
            });
    }

    // ============================================================================
    // SCOPES PAR RÔLE POUR LES VUES
    // ============================================================================

    public function scopeAPrevaliderParManager(Builder $query, int $managerId): Builder
    {
        return $query->where('etat', self::ETAT_SOUMIS)
            ->whereHas('employe', function ($q) use ($managerId) {
                $q->where('manager_id', $managerId);
            });
    }

    public function scopeAPrevaliderParRH(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_VALIDE_MANAGER);
    }

    public function scopeAApprouverParDirecteur(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_VALIDE_RH);
    }

    // ============================================================================
    // MÉTHODES HELPER
    // ============================================================================

    /**
     * Calculer le nombre de jours.
     */
    public function calculerNombreJours(): int
    {
        if ($this->date_debut && $this->date_fin) {
            return $this->date_debut->diffInDays($this->date_fin) + 1;
        }
        return 0;
    }

    /**
     * Vérifier si le congé se chevauche avec une période donnée.
     */
    public function chevauche(Carbon $debut, Carbon $fin): bool
    {
        return $this->date_debut <= $fin && $this->date_fin >= $debut;
    }

    /**
     * Vérifier si le congé peut être annulé.
     */
    public function peutEtreAnnule(): bool
    {
        return in_array($this->etat, [
            self::ETAT_BROUILLON,
            self::ETAT_SOUMIS,
            self::ETAT_VALIDE_MANAGER,
            self::ETAT_VALIDE_RH,
        ]);
    }

    /**
     * Vérifier si le congé est en attente de validation (workflow simultané).
     * Tout état non-final et non-brouillon accepte une validation de n'importe quel niveau.
     */
    public function enAttenteValidation(int $niveau = 0): bool
    {
        $etatsPendants = [
            self::ETAT_SOUMIS,
            self::ETAT_VALIDE_MANAGER,
            self::ETAT_VALIDE_RH,
        ];

        return in_array($this->etat, $etatsPendants);
    }

    /**
     * Vérifier si le congé est approuvé.
     */
    public function estApprouve(): bool
    {
        return $this->etat === self::ETAT_APPROUVE;
    }

    /**
     * Vérifier si le congé est refusé.
     */
    public function estRefuse(): bool
    {
        return in_array($this->etat, [
            self::ETAT_REFUSE_MANAGER,
            self::ETAT_REFUSE_RH,
            self::ETAT_REFUSE_DIRECTEUR,
        ]);
    }

    /**
     * Annuler le congé.
     */
    public function annuler(string $motif, int $annulePar): void
    {
        $this->update([
            'etat' => self::ETAT_ANNULE,
            'motif_annulation' => $motif,
            'annule_par' => $annulePar,
            'annule_le' => now(),
        ]);
    }

    /**
     * Soumettre le congé.
     */
    public function soumettre(): void
    {
        $this->update([
            'etat' => self::ETAT_SOUMIS,
            'nombre_jours' => $this->calculerNombreJours(),
        ]);
    }

    /**
     * Get le label de l'état.
     */
    public function getEtatLabelAttribute(): string
    {
        $labels = [
            self::ETAT_BROUILLON => 'Brouillon',
            self::ETAT_SOUMIS => 'Soumis',
            self::ETAT_VALIDE_MANAGER => 'Validé par le manager',
            self::ETAT_VALIDE_RH => 'Validé par RH',
            self::ETAT_APPROUVE => 'Approuvé',
            self::ETAT_REFUSE_MANAGER => 'Refusé par le manager',
            self::ETAT_REFUSE_RH => 'Refusé par RH',
            self::ETAT_REFUSE_DIRECTEUR => 'Refusé par le directeur',
            self::ETAT_ANNULE => 'Annulé',
        ];

        return $labels[$this->etat] ?? $this->etat;
    }
}
