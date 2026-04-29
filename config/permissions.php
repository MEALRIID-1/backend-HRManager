<?php

/**
 * Matrice complète des permissions par module HRManager
 *
 * Rôles :
 * - employe : Employé standard (accès limité à ses propres données)
 * - manager : Manager d'équipe (gestion de son équipe)
 * - rh : Responsable RH (gestion RH complète)
 * - admin : Directeur/Administrateur (accès total)
 */

return [
    /**
     * Module Employés
     */
    'employees' => [
        'permissions' => [
            'view employees' => [
                'description' => 'Voir la liste des employés',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'view employee profile' => [
                'description' => 'Voir le profil d\'un employé',
                'employe' => true, // Seulement le sien
                'manager' => true, // Son équipe
                'rh' => true,
                'admin' => true,
            ],
            'create employees' => [
                'description' => 'Créer un nouvel employé',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'edit employees' => [
                'description' => 'Modifier les informations d\'un employé',
                'employe' => false,
                'manager' => true, // Limité
                'rh' => true,
                'admin' => true,
            ],
            'delete employees' => [
                'description' => 'Supprimer un employé',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'manage employees' => [
                'description' => 'Gestion complète des employés',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module Contrats
     */
    'contracts' => [
        'permissions' => [
            'view contracts' => [
                'description' => 'Voir les contrats',
                'employe' => true, // Seulement le sien
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'create contracts' => [
                'description' => 'Créer un contrat',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'edit contracts' => [
                'description' => 'Modifier un contrat',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'terminate contracts' => [
                'description' => 'Résilier un contrat',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'renew contracts' => [
                'description' => 'Renouveler un contrat',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage contracts' => [
                'description' => 'Gestion complète des contrats',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module Congés
     */
    'conges' => [
        'permissions' => [
            'view leaves' => [
                'description' => 'Voir les demandes de congés',
                'employe' => true, // Seulement les siennes
                'manager' => true, // Son équipe
                'rh' => true,
                'admin' => true,
            ],
            'request leaves' => [
                'description' => 'Demander des congés',
                'employe' => true,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'approve leaves first' => [
                'description' => 'Approuver congés (1er niveau)',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'approve leaves final' => [
                'description' => 'Approuver congés (final)',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'reject leaves' => [
                'description' => 'Refuser des congés',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'cancel leaves' => [
                'description' => 'Annuler des congés',
                'employe' => true, // Seulement les siens
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'manage leaves' => [
                'description' => 'Gestion complète des congés',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage leave types' => [
                'description' => 'Gérer les types de congés',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'view leave balance' => [
                'description' => 'Voir le solde de congés',
                'employe' => true,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module Fiches de Paie
     */
    'fiches_paie' => [
        'permissions' => [
            'view payslips' => [
                'description' => 'Voir les fiches de paie',
                'employe' => true, // Seulement les siennes
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'create payslips' => [
                'description' => 'Créer des fiches de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'edit payslips' => [
                'description' => 'Modifier des fiches de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'delete payslips' => [
                'description' => 'Supprimer des fiches de paie',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'generate payslips' => [
                'description' => 'Générer les fiches de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'send payslips' => [
                'description' => 'Envoyer les fiches de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage payroll' => [
                'description' => 'Gestion complète de la paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage payroll settings' => [
                'description' => 'Gérer les paramètres de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module Validations
     */
    'validations' => [
        'permissions' => [
            'view validations' => [
                'description' => 'Voir les demandes de validation',
                'employe' => true,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'approve validations' => [
                'description' => 'Approuver des validations',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'reject validations' => [
                'description' => 'Rejeter des validations',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'escalate validations' => [
                'description' => 'Escalader des validations',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'view validation history' => [
                'description' => 'Voir l\'historique des validations',
                'employe' => true,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'manage validations' => [
                'description' => 'Gestion complète des validations',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module Rapports
     */
    'reports' => [
        'permissions' => [
            'view reports' => [
                'description' => 'Voir les rapports',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'generate reports' => [
                'description' => 'Générer des rapports',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'export reports' => [
                'description' => 'Exporter des rapports',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
            'schedule reports' => [
                'description' => 'Planifier des rapports',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage reports' => [
                'description' => 'Gestion complète des rapports',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'view hr reports' => [
                'description' => 'Voir les rapports RH',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'view payroll reports' => [
                'description' => 'Voir les rapports de paie',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'view leave reports' => [
                'description' => 'Voir les rapports de congés',
                'employe' => false,
                'manager' => true,
                'rh' => true,
                'admin' => true,
            ],
        ],
    ],

    /**
     * Module RBAC (Gestion des rôles et permissions)
     */
    'rbac' => [
        'permissions' => [
            'view roles' => [
                'description' => 'Voir les rôles',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'create roles' => [
                'description' => 'Créer des rôles',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'edit roles' => [
                'description' => 'Modifier des rôles',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'delete roles' => [
                'description' => 'Supprimer des rôles',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'view permissions' => [
                'description' => 'Voir les permissions',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'assign permissions' => [
                'description' => 'Assigner des permissions',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'revoke permissions' => [
                'description' => 'Révoquer des permissions',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
            'assign roles' => [
                'description' => 'Assigner des rôles',
                'employe' => false,
                'manager' => false,
                'rh' => true,
                'admin' => true,
            ],
            'manage rbac' => [
                'description' => 'Gestion complète du RBAC',
                'employe' => false,
                'manager' => false,
                'rh' => false,
                'admin' => true,
            ],
        ],
    ],
];
