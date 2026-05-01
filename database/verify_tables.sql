-- Script de vérification des tables HRManager
-- Exécuter avec: mysql -u root -p hrmanager < database/verify_tables.sql

-- Vérification des tables existantes
SELECT 'TABLES EXISTANTES' AS 'VERIFICATION';
SHOW TABLES;

-- Vérification de la structure de la table users
SELECT 'STRUCTURE TABLE USERS' AS 'VERIFICATION';
DESCRIBE users;

-- Vérification de la structure de la table roles
SELECT 'STRUCTURE TABLE ROLES' AS 'VERIFICATION';
DESCRIBE roles;

-- Vérification de la structure de la table permissions
SELECT 'STRUCTURE TABLE PERMISSIONS' AS 'VERIFICATION';
DESCRIBE permissions;

-- Vérification de la structure de la table conges
SELECT 'STRUCTURE TABLE CONGES' AS 'VERIFICATION';
DESCRIBE conges;

-- Vérification de la structure de la table contrats
SELECT 'STRUCTURE TABLE CONTRATS' AS 'VERIFICATION';
DESCRIBE contrats;

-- Vérification de la structure de la table notifications
SELECT 'STRUCTURE TABLE NOTIFICATIONS' AS 'VERIFICATION';
DESCRIBE notifications;

-- Vérification de la structure de la table activity_logs
SELECT 'STRUCTURE TABLE ACTIVITY_LOGS' AS 'VERIFICATION';
DESCRIBE activity_logs;

-- Vérification de la structure de la table fiches_paie
SELECT 'STRUCTURE TABLE FICHES_PAIE' AS 'VERIFICATION';
DESCRIBE fiches_paie;

-- Vérification de la structure de la table validations
SELECT 'STRUCTURE TABLE VALIDATIONS' AS 'VERIFICATION';
DESCRIBE validations;

-- Vérification des indexes sur users
SELECT 'INDEXES TABLE USERS' AS 'VERIFICATION';
SHOW INDEX FROM users;

-- Vérification des indexes sur conges
SELECT 'INDEXES TABLE CONGES' AS 'VERIFICATION';
SHOW INDEX FROM conges;

-- Vérification des indexes sur contrats
SELECT 'INDEXES TABLE CONTRATS' AS 'VERIFICATION';
SHOW INDEX FROM contrats;

-- Comptage des enregistrements
SELECT 'COMPTAGE DES ENREGISTREMENTS' AS 'VERIFICATION';
SELECT 'users' AS table_name, COUNT(*) AS count FROM users
UNION ALL
SELECT 'roles', COUNT(*) FROM roles
UNION ALL
SELECT 'permissions', COUNT(*) FROM permissions
UNION ALL
SELECT 'conges', COUNT(*) FROM conges
UNION ALL
SELECT 'contrats', COUNT(*) FROM contrats
UNION ALL
SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL
SELECT 'activity_logs', COUNT(*) FROM activity_logs
UNION ALL
SELECT 'fiches_paie', COUNT(*) FROM fiches_paie
UNION ALL
SELECT 'validations', COUNT(*) FROM validations;

-- Vérification des contraintes de clés étrangères
SELECT 'CONTRAINTES DE CLES ETRANGERES' AS 'VERIFICATION';
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY 
    TABLE_NAME, COLUMN_NAME;
