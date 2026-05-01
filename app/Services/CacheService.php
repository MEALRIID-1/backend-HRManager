<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Cache TTL par défaut en secondes.
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Tags de cache disponibles.
     */
    private const TAG_ROLES = 'roles';
    private const TAG_PERMISSIONS = 'permissions';
    private const TAG_DASHBOARD = 'dashboard';
    private const TAG_CONGES = 'conges';
    private const TAG_USERS = 'users';

    /**
     * Met en cache les rôles.
     *
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheRoles(Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::tags([self::TAG_ROLES])->remember('roles_all', $ttl, $callback);
    }

    /**
     * Met en cache les permissions.
     *
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cachePermissions(Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::tags([self::TAG_PERMISSIONS])->remember('permissions_all', $ttl, $callback);
    }

    /**
     * Met en cache les statistiques du dashboard pour un utilisateur.
     *
     * @param int $userId
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheDashboardStats(int $userId, Closure $callback, int $ttl = 300): mixed
    {
        $key = "dashboard_stats_user_{$userId}";
        return Cache::tags([self::TAG_DASHBOARD, self::TAG_USERS . ":{$userId}"])->remember($key, $ttl, $callback);
    }

    /**
     * Met en cache les données d'un utilisateur.
     *
     * @param int $userId
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheUser(int $userId, Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $key = "user_{$userId}";
        return Cache::tags([self::TAG_USERS, self::TAG_USERS . ":{$userId}"])->remember($key, $ttl, $callback);
    }

    /**
     * Met en cache la liste des congés.
     *
     * @param string $key
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheConges(string $key, Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::tags([self::TAG_CONGES])->remember("conges_{$key}", $ttl, $callback);
    }

    /**
     * Met en cache la liste des contrats.
     *
     * @param string $key
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheContrats(string $key, Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::tags(['contrats'])->remember("contrats_{$key}", $ttl, $callback);
    }

    /**
     * Met en cache la liste des employés.
     *
     * @param string $key
     * @param Closure $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheEmployes(string $key, Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::tags([self::TAG_USERS, 'employes'])->remember("employes_{$key}", $ttl, $callback);
    }

    /**
     * Invalide le cache d'un utilisateur spécifique.
     *
     * @param int $userId
     * @return void
     */
    public function invalidateUserCache(int $userId): void
    {
        try {
            Cache::tags([self::TAG_USERS . ":{$userId}"])->flush();
            Cache::forget("user_{$userId}");
            Log::info("Cache invalide pour l'utilisateur {$userId}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache utilisateur: " . $e->getMessage());
        }
    }

    /**
     * Invalide tout le cache lié aux congés.
     *
     * @return void
     */
    public function invalidateCongesCache(): void
    {
        try {
            Cache::tags([self::TAG_CONGES])->flush();
            Log::info('Cache des congés invalidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache congés: " . $e->getMessage());
        }
    }

    /**
     * Invalide tout le cache lié aux contrats.
     *
     * @return void
     */
    public function invalidateContratsCache(): void
    {
        try {
            Cache::tags(['contrats'])->flush();
            Log::info('Cache des contrats invalidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache contrats: " . $e->getMessage());
        }
    }

    /**
     * Invalide tout le cache lié aux employés.
     *
     * @return void
     */
    public function invalidateEmployesCache(): void
    {
        try {
            Cache::tags([self::TAG_USERS, 'employes'])->flush();
            Log::info('Cache des employés invalidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache employés: " . $e->getMessage());
        }
    }

    /**
     * Invalide tout le cache du dashboard.
     *
     * @return void
     */
    public function invalidateDashboardCache(): void
    {
        try {
            Cache::tags([self::TAG_DASHBOARD])->flush();
            Log::info('Cache du dashboard invalidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache dashboard: " . $e->getMessage());
        }
    }

    /**
     * Invalide tout le cache des rôles et permissions.
     *
     * @return void
     */
    public function invalidateRolesPermissionsCache(): void
    {
        try {
            Cache::tags([self::TAG_ROLES, self::TAG_PERMISSIONS])->flush();
            Log::info('Cache des rôles et permissions invalidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'invalidation du cache rôles/permissions: " . $e->getMessage());
        }
    }

    /**
     * Vide tout le cache de l'application.
     *
     * @return void
     */
    public function flushAll(): void
    {
        try {
            Cache::flush();
            Log::info('Tout le cache a été vidé');
        } catch (\Exception $e) {
            Log::error("Erreur lors du vidage du cache: " . $e->getMessage());
        }
    }

    /**
     * Récupère une valeur du cache si elle existe.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return Cache::get($key);
    }

    /**
     * Vérifie si une clé existe dans le cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Supprime une clé spécifique du cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }
}
