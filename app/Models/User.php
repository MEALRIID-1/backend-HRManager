<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['prenom', 'nom', 'name', 'email', 'password', 'photo', 'telephone', 'adresse', 'date_embauche', 'departement_id', 'manager_id', 'est_actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_embauche' => 'date',
            'est_actif' => 'boolean',
        ];
    }

    // ============================================================================
    // RELATIONS
    // ============================================================================

    /**
     * Contrats de l'employé.
     */
    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'employe_id');
    }

    /**
     * Congés de l'employé.
     */
    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'employe_id');
    }

    /**
     * Fiches de paie de l'employé.
     */
    public function fichePaies(): HasMany
    {
        return $this->hasMany(FichePaie::class, 'employe_id');
    }

    /**
     * Notifications de l'employé.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * Manager de l'employé.
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Employés managés par cet utilisateur.
     */
    public function subordonnes(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    // ============================================================================
    // SCOPES
    // ============================================================================

    /**
     * Scope pour les employés actifs.
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('est_actif', true);
    }

    /**
     * Scope pour les employés inactifs.
     */
    public function scopeInactif(Builder $query): Builder
    {
        return $query->where('est_actif', false);
    }

    /**
     * Scope pour filtrer par département.
     */
    public function scopeParDepartement(Builder $query, int $departementId): Builder
    {
        return $query->where('departement_id', $departementId);
    }

    /**
     * Scope pour filtrer par manager.
     */
    public function scopeParManager(Builder $query, int $managerId): Builder
    {
        return $query->where('manager_id', $managerId);
    }

    /**
     * Scope pour la recherche fulltext.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('telephone', 'like', "%{$search}%");
        });
    }

    /**
     * Scope pour les employés avec contrat actif.
     */
    public function scopeAvecContratActif(Builder $query): Builder
    {
        return $query->whereHas('contrats', function ($q) {
            $q->where('etat', 'actif')
              ->whereDate('date_debut', '<=', now())
              ->where(function ($sq) {
                  $sq->whereNull('date_fin')
                     ->orWhereDate('date_fin', '>=', now());
              });
        });
    }

    // ============================================================================
    // ACCESSORS
    // ============================================================================

    /**
     * Accessor pour le nom complet.
     */
    public function getNomCompletAttribute(): string
    {
        $prenom = $this->prenom ?? '';
        $nom = $this->nom ?? '';

        return trim($prenom . ' ' . $nom) ?: (string) $this->name;
    }

    /**
     * Accessor pour l'ancienneté en années.
     */
    public function getAncienneteAttribute(): ?int
    {
        if (!$this->date_embauche) {
            return null;
        }

        return Carbon::parse($this->date_embauche)->diffInYears(now());
    }

    /**
     * Accessor pour l'ancienneté formatée.
     */
    public function getAncienneteFormateeAttribute(): ?string
    {
        if (!$this->date_embauche) {
            return null;
        }

        $diff = Carbon::parse($this->date_embauche)->diff(now());
        $annees = $diff->y;
        $mois = $diff->m;

        if ($annees > 0 && $mois > 0) {
            return "{$annees} an" . ($annees > 1 ? 's' : '') . " et {$mois} mois";
        } elseif ($annees > 0) {
            return "{$annees} an" . ($annees > 1 ? 's' : '');
        } else {
            return "{$mois} mois";
        }
    }

    /**
     * Accessor pour l'URL de la photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        return asset('storage/' . $this->photo);
    }

    /**
     * Accessor pour le solde de congés.
     */
    public function getSoldeCongesAttribute(): array
    {
        $totalAccorde = 25; // À adapter selon la politique de l'entreprise
        $totalPris = $this->conges()
            ->where('etat', 'approuve')
            ->whereYear('date_debut', now()->year)
            ->sum('nombre_jours');

        return [
            'total_accorde' => $totalAccorde,
            'total_pris' => (int) $totalPris,
            'solde_restant' => $totalAccorde - (int) $totalPris,
        ];
    }

    // ============================================================================
    // METHODES HELPER
    // ============================================================================

    /**
     * Vérifier si l'employé a un contrat actif.
     */
    public function aContratActif(): bool
    {
        return $this->contrats()
            ->where('etat', 'actif')
            ->whereDate('date_debut', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_fin')
                  ->orWhereDate('date_fin', '>=', now());
            })
            ->exists();
    }

    /**
     * Récupérer le contrat actif.
     */
    public function contratActif(): ?Contrat
    {
        return $this->contrats()
            ->where('etat', 'actif')
            ->whereDate('date_debut', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_fin')
                  ->orWhereDate('date_fin', '>=', now());
            })
            ->first();
    }

    /**
     * Récupérer la dernière fiche de paie.
     */
    public function derniereFichePaie(): ?FichePaie
    {
        return $this->fichePaies()
            ->orderByDesc('annee')
            ->orderByDesc('mois')
            ->first();
    }

    /**
     * Récupérer les notifications non lues.
     */
    public function notificationsNonLues(): HasMany
    {
        return $this->userNotifications()->whereNull('read_at');
    }
}
