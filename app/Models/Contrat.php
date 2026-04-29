<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contrats';

    /**
     * États possibles du contrat.
     */
    public const ETAT_EN_COURS = 'en_cours';
    public const ETAT_PERIODE_ESSAI = 'periode_essai';
    public const ETAT_SUSPENDU = 'suspendu';
    public const ETAT_TERMINE = 'termine';

    /**
     * Types de contrat.
     */
    public const TYPE_CDI = 'cdi';
    public const TYPE_CDD = 'cdd';
    public const TYPE_STAGE = 'stage';
    public const TYPE_ALTERNANCE = 'alternance';

    protected $fillable = [
        'employe_id',
        'type',
        'date_debut',
        'date_fin',
        'salaire',
        'etat',
        'etat_avant_archivage',
        'motif_terminaison',
        'date_terminaison',
        'est_en_periode_essai',
        'duree_periode_essai_jours',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_terminaison' => 'date',
        'salaire' => 'decimal:2',
        'est_en_periode_essai' => 'boolean',
        'duree_periode_essai_jours' => 'integer',
    ];

    // ============================================================================
    // RELATIONS
    // ============================================================================

    /**
     * Employé lié au contrat.
     */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    /**
     * Avenants (modifications) du contrat.
     */
    public function avenants(): HasMany
    {
        return $this->hasMany(ContratAvenant::class, 'contrat_id')->orderBy('created_at', 'desc');
    }

    /**
     * Créateur du contrat.
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    /**
     * Scope pour les contrats en cours.
     */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_EN_COURS);
    }

    /**
     * Scope pour les contrats en période d'essai.
     */
    public function scopeEnPeriodeEssai(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_PERIODE_ESSAI);
    }

    /**
     * Scope pour les contrats suspendus.
     */
    public function scopeSuspendus(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_SUSPENDU);
    }

    /**
     * Scope pour les contrats terminés.
     */
    public function scopeTermines(Builder $query): Builder
    {
        return $query->where('etat', self::ETAT_TERMINE);
    }

    /**
     * Scope pour les contrats actifs (en cours ou période essai).
     */
    public function scopeActifs(Builder $query): Builder
    {
        return $query->whereIn('etat', [self::ETAT_EN_COURS, self::ETAT_PERIODE_ESSAI]);
    }

    /**
     * Scope par type de contrat.
     */
    public function scopeParType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope par employé.
     */
    public function scopeParEmploye(Builder $query, int $employeId): Builder
    {
        return $query->where('employe_id', $employeId);
    }

    /**
     * Scope pour les contrats expirant sous N jours.
     */
    public function scopeExpirantSous(Builder $query, int $jours): Builder
    {
        return $query->whereNotNull('date_fin')
            ->whereDate('date_fin', '<=', now()->addDays($jours))
            ->whereDate('date_fin', '>=', now());
    }

    /**
     * Scope pour les contrats expirés.
     */
    public function scopeExpires(Builder $query): Builder
    {
        return $query->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', now());
    }

    /**
     * Scope pour les CDD/CDI.
     */
    public function scopeCdd(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CDD);
    }

    public function scopeCdi(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CDI);
    }

    // ============================================================================
    // ACCESSORS
    // ============================================================================

    /**
     * Accessor pour la durée restante en jours.
     */
    public function getDureeRestanteAttribute(): ?int
    {
        if (!$this->date_fin) {
            return null; // CDI sans fin
        }

        $jours = now()->diffInDays($this->date_fin, false);
        return max(0, (int) $jours);
    }

    /**
     * Accessor pour la durée totale du contrat en jours.
     */
    public function getDureeTotaleJoursAttribute(): int
    {
        if (!$this->date_fin) {
            return $this->date_debut->diffInDays(now());
        }

        return $this->date_debut->diffInDays($this->date_fin);
    }

    /**
     * Accessor pour vérifier si en période d'essai.
     */
    public function getEstEnPeriodeEssaiAttribute(): bool
    {
        if (!$this->duree_periode_essai_jours) {
            return false;
        }

        $dateFinEssai = $this->date_debut->copy()->addDays($this->duree_periode_essai_jours);
        return now()->lessThanOrEqualTo($dateFinEssai);
    }

    /**
     * Accessor pour vérifier si le contrat expire sous 30 jours.
     */
    public function getEstExpireSous30JoursAttribute(): bool
    {
        if (!$this->date_fin) {
            return false;
        }

        $joursRestants = $this->duree_restante;
        return $joursRestants !== null && $joursRestants <= 30 && $joursRestants > 0;
    }

    /**
     * Accessor pour vérifier si le contrat est expiré.
     */
    public function getEstExpireAttribute(): bool
    {
        if (!$this->date_fin) {
            return false;
        }

        return now()->greaterThan($this->date_fin);
    }

    /**
     * Accessor pour la progression de la période d'essai (0-100%).
     */
    public function getProgressionPeriodeEssaiAttribute(): ?float
    {
        if (!$this->duree_periode_essai_jours) {
            return null;
        }

        $joursPasses = $this->date_debut->diffInDays(now());
        $progression = min(100, ($joursPasses / $this->duree_periode_essai_jours) * 100);

        return round($progression, 2);
    }

    /**
     * Accessor pour le statut formaté.
     */
    public function getStatutFormateAttribute(): string
    {
        $labels = [
            self::ETAT_EN_COURS => 'En cours',
            self::ETAT_PERIODE_ESSAI => 'Période d\'essai',
            self::ETAT_SUSPENDU => 'Suspendu',
            self::ETAT_TERMINE => 'Terminé',
        ];

        return $labels[$this->etat] ?? $this->etat;
    }

    // ============================================================================
    // METHODES HELPER
    // ============================================================================

    /**
     * Terminer le contrat.
     */
    public function terminer(string $motif, ?Carbon $date = null): void
    {
        $this->update([
            'etat' => self::ETAT_TERMINE,
            'motif_terminaison' => $motif,
            'date_terminaison' => $date ?? now(),
            'date_fin' => $date ?? now(),
        ]);
    }

    /**
     * Renouveler le contrat (pour CDD).
     */
    public function renouveler(Carbon $nouvelleDateFin): void
    {
        $this->update([
            'date_fin' => $nouvelleDateFin,
            'etat' => self::ETAT_EN_COURS,
        ]);
    }

    /**
     * Vérifier si le contrat se chevauche avec une période donnée.
     */
    public function chevauche(Carbon $dateDebut, ?Carbon $dateFin): bool
    {
        if (!$dateFin) {
            return $this->date_fin === null || $this->date_fin >= $dateDebut;
        }

        return $this->date_debut <= $dateFin &&
               ($this->date_fin === null || $this->date_fin >= $dateDebut);
    }

    /**
     * Mettre à jour le statut automatiquement.
     */
    public function mettreAJourStatut(): void
    {
        if ($this->est_expire && $this->etat !== self::ETAT_TERMINE) {
            $this->update(['etat' => self::ETAT_TERMINE]);
        }

        if ($this->est_en_periode_essai && $this->etat === self::ETAT_EN_COURS) {
            $this->update(['etat' => self::ETAT_PERIODE_ESSAI]);
        }

        if (!$this->est_en_periode_essai && $this->etat === self::ETAT_PERIODE_ESSAI) {
            $this->update(['etat' => self::ETAT_EN_COURS]);
        }
    }
}
