<?php

namespace App\Modules\Leaves\Http\Resources;

use App\Models\Conge;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
{
    /**
     * Mapping des états backend vers les statuts frontend.
     */
    private const ETAT_MAP = [
        Conge::ETAT_BROUILLON        => 'BROUILLON',
        Conge::ETAT_SOUMIS           => 'EN_ATTENTE_N1',
        Conge::ETAT_VALIDE_MANAGER   => 'EN_ATTENTE_N2',
        Conge::ETAT_VALIDE_RH        => 'EN_ATTENTE_N3',
        Conge::ETAT_APPROUVE         => 'APPROUVE_N3',
        Conge::ETAT_REFUSE_MANAGER   => 'REFUSE_N1',
        Conge::ETAT_REFUSE_RH        => 'REFUSE_N2',
        Conge::ETAT_REFUSE_DIRECTEUR => 'REFUSE_N3',
        Conge::ETAT_ANNULE           => 'ANNULE',
    ];

    /**
     * Mapping des types backend vers les types frontend.
     */
    private const TYPE_MAP = [
        Conge::TYPE_CONGE_PAYE       => 'ANNUEL',
        Conge::TYPE_RTT              => 'RTT',
        Conge::TYPE_CONGE_SANS_SOLDE => 'SANS_SOLDE',
        Conge::TYPE_MALADIE          => 'MALADIE',
        Conge::TYPE_FORMATION        => 'FORMATION',
        Conge::TYPE_MATERNITE        => 'MATERNITE',
        Conge::TYPE_PATERNITE        => 'PATERNITE',
    ];

    public function toArray($request): array
    {
        $etatFrontend = self::ETAT_MAP[$this->etat] ?? strtoupper($this->etat);
        $typeFrontend = self::TYPE_MAP[$this->type] ?? strtoupper($this->type);

        // Construire le workflow depuis les validations
        $workflow = null;
        if ($this->relationLoaded('validations')) {
            $validations = $this->validations;
            $etapes = $validations->map(function ($v) {
                return [
                    'niveau'        => $v->niveau,
                    'niveauLabel'   => $v->niveau_label,
                    'action'        => $v->action,
                    'motif'         => $v->motif,
                    'validateur'    => $v->relationLoaded('validateur') && $v->validateur ? [
                        'id'     => $v->validateur->id,
                        'nom'    => $v->validateur->nom,
                        'prenom' => $v->validateur->prenom,
                    ] : null,
                    'dateDecision'  => $v->date_validation?->toISOString(),
                ];
            });

            $workflow = [
                'niveauActuel' => $validations->count() > 0 ? $validations->last()->niveau : 0,
                'etapes'       => $etapes->values()->toArray(),
            ];
        }

        // Construire l'objet employé si chargé
        $employe = null;
        if ($this->relationLoaded('employe') && $this->employe) {
            $emp = $this->employe;
            $employe = [
                'id'          => (string) $emp->id,
                'nom'         => $emp->nom,
                'prenom'      => $emp->prenom,
                'email'       => $emp->email,
                'matricule'   => $emp->matricule ?? '',
                'telephone'   => $emp->telephone ?? '',
                'avatar'      => $emp->avatar ?? null,
                'statut'      => strtoupper($emp->statut ?? 'ACTIF'),
                'managerId'   => $emp->manager_id ? (string) $emp->manager_id : null,
            ];
        }

        return [
            'id'          => (string) $this->id,
            'employeId'   => (string) $this->employe_id,
            'employe'     => $employe,
            'type'        => $typeFrontend,
            'statut'      => $etatFrontend,
            'etat'        => $this->etat,      // état brut backend
            'dateDebut'   => $this->date_debut?->toDateString(),
            'dateFin'     => $this->date_fin?->toDateString(),
            'nombreJours' => $this->nombre_jours,
            'motif'       => $this->raison,
            'commentaire' => $this->commentaire,
            'motifAnnulation' => $this->motif_annulation,
            'workflow'    => $workflow,
            'createdAt'   => $this->created_at?->toISOString(),
            'updatedAt'   => $this->updated_at?->toISOString(),
            'deletedAt'   => $this->deleted_at?->toISOString(),
        ];
    }
}
