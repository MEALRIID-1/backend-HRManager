<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';
    // Indique à Laravel que le champ password s'appelle mot_de_passe
protected $authPasswordName = 'mot_de_passe';

    protected $fillable = [
        'is_active',
        'email',
        'mot_de_passe',
        'nom',
        'prenom',
        'departement',
        'photo_profil',
        'date_embauche',
        'iban',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'date_embauche' => 'date',
        'mot_de_passe' => 'hashed', 
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'user_id', 'id');
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'user_id', 'id');
    }

    public function fichesPaie(): HasMany
    {
        return $this->hasMany(FichePaie::class, 'user_id', 'id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class, 'validateur_id', 'id');
    }

    /**
     * Scope pour filtrer les utilisateurs actifs (non supprimés).
     */
    public function scopeActif($query)
    {
        return $query->where('is_active', true)
                     ->whereNull('deleted_at');
    }

    /**
     * Scope pour filtrer par département.
     */
    public function scopeByDepartement($query, string $dept)
    {
        return $query->where('departement', $dept);
    }

    /**
     * Scope pour filtrer par rôle.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('slug', $role);
        });
    }

    /**
     * Scope pour recherche globale (nom, prénom, email).
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nom', 'LIKE', "%{$term}%")
              ->orWhere('prenom', 'LIKE', "%{$term}%")
              ->orWhere('email', 'LIKE', "%{$term}%");
        });
    }
}
