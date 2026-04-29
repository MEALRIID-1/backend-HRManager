<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * Types de notifications
     */
    public const TYPE_CONGE_SUBMITTED = 'conge_submitted';
    public const TYPE_CONGE_APPROVED = 'conge_approved';
    public const TYPE_CONGE_REJECTED = 'conge_rejected';
    public const TYPE_CONGE_CANCELLED = 'conge_cancelled';
    public const TYPE_CONTRACT_EXPIRING = 'contract_expiring';
    public const TYPE_CONTRACT_TERMINATED = 'contract_terminated';
    public const TYPE_PAYSLIP_AVAILABLE = 'payslip_available';
    public const TYPE_EMPLOYEE_CREATED = 'employee_created';
    public const TYPE_VALIDATION_REQUIRED = 'validation_required';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'notifiable_type',
        'notifiable_id',
        'action_url',
        'action_text',
        'icon',
        'priority',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ============================================================================
    // RELATIONS
    // ============================================================================

    /**
     * Utilisateur destinataire de la notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Modèle lié à la notification (polymorphic).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    /**
     * Scope pour les notifications non lues.
     */
    public function scopeNonLues(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope pour les notifications lues.
     */
    public function scopeLues(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope par type de notification.
     */
    public function scopeParType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les notifications de l'utilisateur.
     */
    public function scopePourUtilisateur(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour les notifications récentes (X derniers jours).
     */
    public function scopeRecentes(Builder $query, int $jours = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($jours));
    }

    /**
     * Scope par priorité.
     */
    public function scopeParPriorite(Builder $query, string $priorite): Builder
    {
        return $query->where('priority', $priorite);
    }

    /**
     * Scope pour les notifications haute priorité.
     */
    public function scopeImportantes(Builder $query): Builder
    {
        return $query->where('priority', 'high');
    }

    // ============================================================================
    // METHODES
    // ============================================================================

    /**
     * Marquer comme lue.
     */
    public function marquerCommeLue(): void
    {
        if (!$this->estLue()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Marquer comme non lue.
     */
    public function marquerCommeNonLue(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Vérifier si la notification est lue.
     */
    public function estLue(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Vérifier si la notification est non lue.
     */
    public function estNonLue(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Marquer toutes les notifications de l'utilisateur comme lues.
     */
    public static function marquerToutCommeLue(int $userId): int
    {
        return self::pourUtilisateur($userId)
            ->nonLues()
            ->update(['read_at' => now()]);
    }

    /**
     * Compter les notifications non lues par type.
     */
    public static function compterParType(int $userId): array
    {
        return self::pourUtilisateur($userId)
            ->nonLues()
            ->select('type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    /**
     * Créer une notification système.
     */
    public static function creerSystemNotification(int $userId, string $title, string $message, array $data = []): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_SYSTEM,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => 'normal',
        ]);
    }

    /**
     * Obtenir l'URL d'action ou une URL par défaut selon le type.
     */
    public function getActionUrlAttribute(): ?string
    {
        if ($this->attributes['action_url'] ?? null) {
            return $this->attributes['action_url'];
        }

        // URLs par défaut selon le type
        return match ($this->type) {
            self::TYPE_CONGE_SUBMITTED, self::TYPE_CONGE_APPROVED, self::TYPE_CONGE_REJECTED, self::TYPE_CONGE_CANCELLED => 
                $this->data['conge_id'] ?? null ? '/leaves/' . $this->data['conge_id'] : null,
            self::TYPE_CONTRACT_EXPIRING, self::TYPE_CONTRACT_TERMINATED => 
                $this->data['contrat_id'] ?? null ? '/contracts/' . $this->data['contrat_id'] : null,
            self::TYPE_PAYSLIP_AVAILABLE => 
                $this->data['fiche_paie_id'] ?? null ? '/payslips/' . $this->data['fiche_paie_id'] : null,
            default => null,
        };
    }

    /**
     * Get l'icône selon le type.
     */
    public function getIconAttribute(): string
    {
        if ($this->attributes['icon'] ?? null) {
            return $this->attributes['icon'];
        }

        return match ($this->type) {
            self::TYPE_CONGE_SUBMITTED => 'calendar-clock',
            self::TYPE_CONGE_APPROVED => 'calendar-check',
            self::TYPE_CONGE_REJECTED => 'calendar-x',
            self::TYPE_CONGE_CANCELLED => 'calendar-off',
            self::TYPE_CONTRACT_EXPIRING => 'file-warning',
            self::TYPE_CONTRACT_TERMINATED => 'file-x',
            self::TYPE_PAYSLIP_AVAILABLE => 'file-dollar-sign',
            self::TYPE_EMPLOYEE_CREATED => 'user-plus',
            self::TYPE_VALIDATION_REQUIRED => 'alert-circle',
            self::TYPE_SYSTEM => 'info',
            default => 'bell',
        };
    }

    /**
     * Get la classe CSS de couleur selon le type.
     */
    public function getColorClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CONGE_APPROVED, self::TYPE_PAYSLIP_AVAILABLE => 'success',
            self::TYPE_CONGE_REJECTED, self::TYPE_CONTRACT_TERMINATED => 'danger',
            self::TYPE_CONGE_SUBMITTED, self::TYPE_VALIDATION_REQUIRED => 'warning',
            self::TYPE_CONTRACT_EXPIRING => 'orange',
            self::TYPE_SYSTEM => 'info',
            default => 'default',
        };
    }

    /**
     * Supprimer les vieilles notifications lues (garde 90 jours).
     */
    public static function nettoyerVieilles(): int
    {
        return self::lues()
            ->where('read_at', '<', now()->subDays(90))
            ->delete();
    }
}
