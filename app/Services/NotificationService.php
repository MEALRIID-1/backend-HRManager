<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\EnvoyerEmailJob;
use App\Mail\CongeDecisionMail;
use App\Mail\CongeDeposeMail;
use App\Mail\ContratCreeMail;
use App\Mail\PasswordTemporaireEmail;
use App\Models\Conge;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Créer une notification générique.
     */
    public function notifier(int $userId, string $type, string $message): Notification
    {
        try {
            return DB::transaction(function () use ($userId, $type, $message) {
                return Notification::create([
                    'user_id'    => $userId,
                    'type'       => $type,
                    'message'    => $message,
                    'lu'         => false,
                    'date_envoi' => now(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Notifier les validateurs quand un congé est déposé.
     * Envoie notification en base de données ET email via queue.
     */
    public function notifierCongeDepose(Conge $conge): void
    {
        try {
            // Charger l'employé
            $conge->load('employe');

            // Notifier les managers (N1)
            $managers = User::whereHas('roles', function ($q) {
                $q->where('nom', 'manager');
            })->get();

            foreach ($managers as $manager) {
                $this->notifier(
                    $manager->id,
                    'validation_requise_n1',
                    "Nouvelle demande de congé à valider (N1) de {$conge->employe->nom} {$conge->employe->prenom} - Du {$conge->date_debut->format('d/m/Y')} au {$conge->date_fin->format('d/m/Y')}"
                );

                try {
                    $email = new CongeDeposeMail($conge, $manager, $conge->employe);
                    EnvoyerEmailJob::dispatch($email, $manager->email, $manager->prenom . ' ' . $manager->nom);
                } catch (\Exception $emailException) {
                    Log::warning('Échec envoi email congé déposé (manager): ' . $emailException->getMessage());
                }
            }

            // Notifier la RH (N2)
            $rhUsers = User::whereHas('roles', function ($q) {
                $q->where('nom', 'RH');
            })->get();

            foreach ($rhUsers as $rh) {
                $this->notifier(
                    $rh->id,
                    'validation_requise_n2',
                    "Nouvelle demande de congé à valider (N2) de {$conge->employe->nom} {$conge->employe->prenom}"
                );

                try {
                    $email = new CongeDeposeMail($conge, $rh, $conge->employe);
                    EnvoyerEmailJob::dispatch($email, $rh->email, $rh->prenom . ' ' . $rh->nom);
                } catch (\Exception $emailException) {
                    Log::warning('Échec envoi email congé déposé (RH): ' . $emailException->getMessage());
                }
            }

            // Notifier les admins (N3)
            $admins = User::whereHas('roles', function ($q) {
                $q->where('nom', 'admin');
            })->get();

            foreach ($admins as $admin) {
                $this->notifier(
                    $admin->id,
                    'validation_requise_n3',
                    "Nouvelle demande de congé à valider (N3) de {$conge->employe->nom} {$conge->employe->prenom}"
                );

                try {
                    $email = new CongeDeposeMail($conge, $admin, $conge->employe);
                    EnvoyerEmailJob::dispatch($email, $admin->email, $admin->prenom . ' ' . $admin->nom);
                } catch (\Exception $emailException) {
                    Log::warning('Échec envoi email congé déposé (admin): ' . $emailException->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification de congé déposé: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'employé quand son congé est validé.
     */
    public function notifierCongeValide(Conge $conge, string $niveau, ?User $validateur = null): void
    {
        try {
            $conge->load('employe');

            $message = "Votre demande de congé du {$conge->date_debut->format('d/m/Y')} au {$conge->date_fin->format('d/m/Y')} a été approuvée";

            if ($niveau === 'N1') {
                $message .= " par votre manager. En attente de validation RH et Admin.";
            } elseif ($niveau === 'N2') {
                $message .= " par la RH. En attente de validation Admin.";
            } elseif ($niveau === 'N3') {
                $message .= " par l'administration. Votre congé est entièrement approuvé !";
            }

            $this->notifier(
                $conge->employe_id,
                'conge_approuve',
                $message
            );

            try {
                $decision = ($niveau === 'N3') ? 'approuve' : 'partiellement_valide';
                $email = new CongeDecisionMail($conge, $conge->employe, $decision, null, $validateur);
                EnvoyerEmailJob::dispatch($email, $conge->employe->email, $conge->employe->prenom . ' ' . $conge->employe->nom);
            } catch (\Exception $emailException) {
                Log::warning('Échec envoi email congé validé: ' . $emailException->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification de congé validé: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'employé quand son congé est refusé.
     */
    public function notifierCongeRefuse(Conge $conge, string $motif, ?User $refuseur = null): void
    {
        try {
            $conge->load('employe');

            $this->notifier(
                $conge->employe_id,
                'conge_refuse',
                "Votre demande de congé du {$conge->date_debut->format('d/m/Y')} au {$conge->date_fin->format('d/m/Y')} a été refusée. Motif: {$motif}"
            );

            try {
                $email = new CongeDecisionMail($conge, $conge->employe, 'refuse', $motif, $refuseur);
                EnvoyerEmailJob::dispatch($email, $conge->employe->email, $conge->employe->prenom . ' ' . $conge->employe->nom);
            } catch (\Exception $emailException) {
                Log::warning('Échec envoi email congé refusé: ' . $emailException->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification de congé refusé: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'employé quand un contrat est créé.
     */
    public function notifierContratCree(Contrat $contrat): void
    {
        try {
            $contrat->load('employe');

            $this->notifier(
                $contrat->employe_id,
                'nouveau_contrat',
                "Un nouveau contrat de type {$contrat->type} a été créé pour vous, prenant effet le {$contrat->date_debut->format('d/m/Y')}."
            );

            try {
                $email = new ContratCreeMail($contrat, $contrat->employe);
                EnvoyerEmailJob::dispatch($email, $contrat->employe->email, $contrat->employe->prenom . ' ' . $contrat->employe->nom);
            } catch (\Exception $emailException) {
                Log::warning('Échec envoi email contrat créé: ' . $emailException->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification de contrat créé: ' . $e->getMessage());
        }
    }

    /**
     * Notifier le nouvel employé avec son mot de passe temporaire.
     */
    public function notifierNouvelEmploye(User $employe, string $password): void
    {
        try {
            $this->notifier(
                $employe->id,
                'compte_cree',
                "Bienvenue ! Votre compte a été créé. Votre mot de passe temporaire est: {$password}. Veuillez le changer lors de votre première connexion."
            );

            try {
                $email = new PasswordTemporaireEmail($employe, $password);
                EnvoyerEmailJob::dispatch($email, $employe->email, $employe->prenom . ' ' . $employe->nom);
            } catch (\Exception $emailException) {
                Log::warning('Échec envoi email password temporaire: ' . $emailException->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification du nouvel employé: ' . $e->getMessage());
        }
    }

    /**
     * Marquer une notification comme lue.
     */
    public function markAsRead(Notification $notification): void
    {
        try {
            $notification->update(['lu' => true, 'date_lecture' => now()]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du marquage de la notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marquer toutes les notifications comme lues pour un utilisateur.
     */
    public function markAllAsRead(int $userId): int
    {
        try {
            return Notification::where('user_id', $userId)
                ->where('lu', false)
                ->update(['lu' => true, 'date_lecture' => now()]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du marquage de toutes les notifications: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Compter les notifications non lues.
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('lu', false)
            ->count();
    }

    /**
     * Liste des notifications d'un utilisateur.
     */
    public function getNotifications(int $userId, int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->orderByRaw("CASE WHEN lu = 0 THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Supprimer une notification.
     */
    public function delete(Notification $notification): bool
    {
        try {
            return $notification->delete();
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la notification: ' . $e->getMessage());
            throw $e;
        }
    }
}