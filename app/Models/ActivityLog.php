<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * Modèle pour les logs d'activité (Audit Trail).
 */
class ActivityLog extends Model
{
    use HasFactory;

    // Actions possibles
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_TERMINATED = 'terminated';

    protected $fillable = [
        'user_id',
        'action',
        'entity_name',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Champs sensibles à ne jamais logger
    public static array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'plain_password',
        'token',
    ];

    // ============================================================================
    // RELATIONS
    // ============================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    /**
     * Scope par utilisateur.
     */
    public function scopeParUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope par entité (type de modèle).
     */
    public function scopeParEntite(Builder $query, string $entityName): Builder
    {
        return $query->where('entity_name', $entityName);
    }

    /**
     * Scope par action.
     */
    public function scopeParAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope par ID d'entité.
     */
    public function scopeParEntityId(Builder $query, int $entityId): Builder
    {
        return $query->where('entity_id', $entityId);
    }

    /**
     * Scope entre deux dates.
     */
    public function scopeEntrePlages(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope pour une période récente.
     */
    public function scopeRecent(Builder $query, int $jours = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($jours));
    }

    /**
     * Scope pour les créations.
     */
    public function scopeCreated(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_CREATED);
    }

    /**
     * Scope pour les mises à jour.
     */
    public function scopeUpdated(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_UPDATED);
    }

    /**
     * Scope pour les suppressions.
     */
    public function scopeDeleted(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_DELETED);
    }

    /**
     * Scope pour les actions importantes.
     */
    public function scopeImportant(Builder $query): Builder
    {
        return $query->whereIn('action', [
            self::ACTION_DELETED,
            self::ACTION_TERMINATED,
        ]);
    }

    // ============================================================================
    // METHODES
    // ============================================================================

    /**
     * Filtrer les valeurs sensibles.
     */
    public static function filterSensitive(array $data): array
    {
        return array_diff_key($data, array_flip(self::$sensitiveFields));
    }

    /**
     * Nettoyer les vieux logs.
     */
    public static function purgeOldLogs(int $jours = 365): int
    {
        return self::where('created_at', '<', now()->subDays($jours))->delete();
    }

    /**
     * Obtenir le label de l'action.
     */
    public function getActionLabelAttribute(): string
    {
        $labels = [
            self::ACTION_CREATED => 'Création',
            self::ACTION_UPDATED => 'Modification',
            self::ACTION_DELETED => 'Suppression',
            self::ACTION_RESTORED => 'Restauration',
            self::ACTION_LOGIN => 'Connexion',
            self::ACTION_LOGOUT => 'Déconnexion',
            self::ACTION_SUBMITTED => 'Soumission',
            self::ACTION_APPROVED => 'Approbation',
            self::ACTION_REJECTED => 'Refus',
            self::ACTION_TERMINATED => 'Terminaison',
        ];

        return $labels[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Get la classe CSS selon l'action.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'success',
            self::ACTION_UPDATED => 'info',
            self::ACTION_DELETED, self::ACTION_TERMINATED => 'danger',
            self::ACTION_REJECTED => 'warning',
            self::ACTION_APPROVED => 'primary',
            default => 'secondary',
        };
    }
}