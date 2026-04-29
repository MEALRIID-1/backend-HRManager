<?php

namespace App\Modules\Contracts\Services;

use App\Models\ActivityLog;
use App\Models\Contrat;
use App\Models\ContratAvenant;
use App\Models\User;
use App\Modules\Contracts\Repositories\ContractRepository;
use App\Notifications\ContractExpiringNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ContractService
{
    private ContractRepository $repository;

    public function __construct(ContractRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getContracts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getAllPaginated($filters, $perPage);
    }

    public function getContract(int $id): ?Contrat
    {
        return $this->repository->findById($id);
    }

    public function createContract(array $data, int $createdBy): Contrat
    {
        try {
            DB::beginTransaction();

            // Vérifier le chevauchement
            if ($this->repository->hasOverlappingContract(
                $data['employe_id'],
                Carbon::parse($data['date_debut']),
                !empty($data['date_fin']) ? Carbon::parse($data['date_fin']) : null
            )) {
                throw new \Exception('Un contrat existe déjà pour cette période.');
            }

            // Définir l'état initial
            $data['etat'] = Contrat::ETAT_EN_COURS;
            $data['created_by'] = $createdBy;

            // Calculer la période d'essai si spécifiée
            if (!empty($data['duree_periode_essai_jours'])) {
                $data['est_en_periode_essai'] = true;
            }

            $contrat = $this->repository->create($data);

            // Logger
            ActivityLog::create([
                'user_id' => $createdBy,
                'action' => 'create_contract',
                'model' => 'Contrat',
                'model_id' => $contrat->id,
                'new_values' => $data,
            ]);

            // Notifier l'employé
            $employe = User::find($data['employe_id']);
            if ($employe) {
                $employe->userNotifications()->create([
                    'type' => 'contract_created',
                    'data' => [
                        'contrat_id' => $contrat->id,
                        'type_contrat' => $contrat->type,
                        'message' => 'Un nouveau contrat a été créé pour vous.',
                    ],
                ]);
            }

            DB::commit();

            return $contrat->fresh(['employe']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateContract(Contrat $contrat, array $data, int $updatedBy): Contrat
    {
        try {
            DB::beginTransaction();

            $oldValues = $contrat->toArray();
            $avenantData = null;

            // Vérifier si on doit créer un avenant
            if (isset($data['salaire']) && $data['salaire'] != $contrat->salaire) {
                $avenantData = [
                    'type_modification' => ContratAvenant::TYPE_SALAIRE,
                    'ancienne_valeur' => ['salaire' => $contrat->salaire],
                    'nouvelle_valeur' => ['salaire' => $data['salaire']],
                ];
            }

            if (isset($data['type']) && $data['type'] != $contrat->type) {
                $avenantData = [
                    'type_modification' => ContratAvenant::TYPE_TYPE,
                    'ancienne_valeur' => ['type' => $contrat->type],
                    'nouvelle_valeur' => ['type' => $data['type']],
                ];
            }

            if (isset($data['date_fin']) && $data['date_fin'] != $contrat->date_fin?->format('Y-m-d')) {
                $avenantData = [
                    'type_modification' => ContratAvenant::TYPE_DATE_FIN,
                    'ancienne_valeur' => ['date_fin' => $contrat->date_fin?->format('Y-m-d')],
                    'nouvelle_valeur' => ['date_fin' => $data['date_fin']],
                ];
            }

            // Créer l'avenant si modification significative
            if ($avenantData) {
                $avenantData['contrat_id'] = $contrat->id;
                $avenantData['motif'] = $data['motif_modification'] ?? 'Modification contrat';
                $avenantData['date_effet'] = $data['date_effet'] ?? now();
                $avenantData['created_by'] = $updatedBy;

                ContratAvenant::create($avenantData);
            }

            // Mise à jour partielle
            $updateData = array_filter($data, fn($v) => $v !== null);

            $contrat = $this->repository->update($contrat, $updateData);

            // Logger
            ActivityLog::create([
                'user_id' => $updatedBy,
                'action' => 'update_contract',
                'model' => 'Contrat',
                'model_id' => $contrat->id,
                'old_values' => $oldValues,
                'new_values' => $updateData,
            ]);

            DB::commit();

            return $contrat->fresh(['employe', 'avenants']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function terminateContract(Contrat $contrat, string $motif, ?Carbon $date = null, int $terminatedBy): Contrat
    {
        try {
            DB::beginTransaction();

            $contrat->terminer($motif, $date);

            // Logger
            ActivityLog::create([
                'user_id' => $terminatedBy,
                'action' => 'terminate_contract',
                'model' => 'Contrat',
                'model_id' => $contrat->id,
                'new_values' => [
                    'motif' => $motif,
                    'date' => $date?->format('Y-m-d'),
                ],
            ]);

            // Notifier RH et Directeur
            $rhUsers = User::role(['rh', 'admin'])->get();
            foreach ($rhUsers as $user) {
                $user->userNotifications()->create([
                    'type' => 'contract_terminated',
                    'data' => [
                        'contrat_id' => $contrat->id,
                        'employe_nom' => $contrat->employe->name,
                        'motif' => $motif,
                        'message' => "Le contrat de {$contrat->employe->name} a été terminé.",
                    ],
                ]);
            }

            DB::commit();

            return $contrat->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getExpiringContracts(int $jours): Collection
    {
        return $this->repository->getExpiringContracts($jours);
    }

    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    public function sendExpirationAlerts(): void
    {
        $contrats = $this->repository->getExpiringContracts(30);

        foreach ($contrats as $contrat) {
            $rhUsers = User::role(['rh', 'admin'])->get();

            Notification::send($rhUsers, new ContractExpiringNotification($contrat));

            // Marquer comme notifié (ajouter un champ notified_at si nécessaire)
        }
    }

    public function hasOverlappingContract(int $employeId, Carbon $dateDebut, ?Carbon $dateFin, ?int $excludeId = null): bool
    {
        return $this->repository->hasOverlappingContract($employeId, $dateDebut, $dateFin, $excludeId);
    }
}
