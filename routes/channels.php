<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Canal privé pour les notifications d'un utilisateur.
 * Seul l'utilisateur authentifié peut écouter son propre canal.
 */
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Canal privé pour les notifications de l'équipe d'un manager.
 */
Broadcast::channel('private-team.{managerId}', function ($user, $managerId) {
    return $user->hasRole('manager') && $user->id === (int) $managerId;
});

/**
 * Canal pour les notifications RH.
 */
Broadcast::channel('private-hr', function ($user) {
    return $user->hasRole(['rh', 'admin', 'directeur']);
});

/**
 * Canal pour les notifications admin.
 */
Broadcast::channel('private-admin', function ($user) {
    return $user->hasRole(['admin', 'directeur']);
});
