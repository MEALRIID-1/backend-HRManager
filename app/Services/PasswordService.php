<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Initiate password reset process.
     *
     * @param string $email
     * @throws ValidationException
     */
    public function forgotPassword(string $email): void
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['Aucun compte trouvé avec cette adresse email.'],
                ]);
            }

            if ($user->statut !== User::STATUT_ACTIF) {
                throw ValidationException::withMessages([
                    'email' => ['Ce compte est inactif. Contactez votre administrateur.'],
                ]);
            }

            $token = Password::createToken($user);

            $this->notificationService->create($user, [
                'type' => \App\Models\Notification::TYPE_SYSTEM,
                'titre' => 'Réinitialisation de mot de passe',
                'message' => "Un code de réinitialisation a été généré: {$token}",
                'data' => ['token' => $token],
            ]);

            ActivityLog::log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::MODULE_AUTH,
                "Demande de réinitialisation de mot de passe pour {$user->email}",
                null,
                null,
                null,
                null
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la demande de réinitialisation: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reset password with token.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        try {
            $status = Password::reset(
                $data,
                function (User $user, string $password) {
                    $this->updatePassword($user, $password);
                    event(new PasswordReset($user));
                }
            );

            if ($status !== Password::PASSWORD_RESET) {
                throw ValidationException::withMessages([
                    'token' => [__($status)],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Change password for authenticated user.
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        try {
            if (!Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Le mot de passe actuel est incorrect.'],
                ]);
            }

            if (Hash::check($newPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'new_password' => ['Le nouveau mot de passe doit être différent de l\'ancien.'],
                ]);
            }

            DB::transaction(function () use ($user, $newPassword) {
                $this->updatePassword($user, $newPassword);

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_AUTH,
                    "Changement de mot de passe par l'utilisateur",
                    null,
                    null,
                    null,
                    $user
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de mot de passe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Force password reset by admin.
     *
     * @param User $admin
     * @param User $targetUser
     * @param string|null $temporaryPassword
     * @throws \Exception
     */
    public function forcePasswordReset(User $admin, User $targetUser, ?string $temporaryPassword = null): string
    {
        try {
            return DB::transaction(function () use ($admin, $targetUser, $temporaryPassword) {
                $password = $temporaryPassword ?? $this->generateTemporaryPassword();

                $this->updatePassword($targetUser, $password);

                ActivityLog::log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::MODULE_AUTH,
                    "Réinitialisation forcée du mot de passe de {$targetUser->email} par {$admin->name}",
                    null,
                    null,
                    null,
                    $admin
                );

                $this->notificationService->notifyNewPassword($targetUser);

                return $password;
            });
        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation forcée: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if password change is required.
     */
    public function isPasswordChangeRequired(User $user): bool
    {
        if ($user->dernier_changement_password === null) {
            return true;
        }

        $daysSinceLastChange = $user->dernier_changement_password->diffInDays(Carbon::now());
        $maxDays = config('hrmanager.password_change_days', 90);

        return $daysSinceLastChange >= $maxDays;
    }

    /**
     * Get days until password expires.
     */
    public function getDaysUntilPasswordExpiry(User $user): ?int
    {
        if ($user->dernier_changement_password === null) {
            return 0;
        }

        $maxDays = config('hrmanager.password_change_days', 90);
        $daysSinceLastChange = $user->dernier_changement_password->diffInDays(Carbon::now());

        return max(0, $maxDays - $daysSinceLastChange);
    }

    /**
     * Validate password strength.
     *
     * @throws ValidationException
     */
    public function validatePasswordStrength(string $password): void
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'password' => $errors,
            ]);
        }
    }

    /**
     * Update password and reset security counters.
     */
    private function updatePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'dernier_changement_password' => Carbon::now(),
            'tentatives_connexion' => 0,
            'verrouille_jusqua' => null,
        ])->save();
    }

    private function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }
}
