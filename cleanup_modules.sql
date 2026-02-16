-- Script de nettoyage manuel pour Chastity Tracker
-- À exécuter dans phpMyAdmin ou en ligne de commande MySQL si vous avez des erreurs de modules

-- IMPORTANT : Remplacez 'phpbb_' par le préfixe de votre table si différent

-- Supprimer tous les modules UCP Chastity Tracker existants
DELETE FROM phpbb_modules 
WHERE module_basename = '\\verturin\\chastitytracker\\ucp\\main_module'
   OR module_basename = '\\vendor\\chastitytracker\\ucp\\main_module';

-- Supprimer la catégorie UCP Chastity Tracker
DELETE FROM phpbb_modules 
WHERE module_class = 'ucp' 
  AND module_langname = 'UCP_CHASTITY_TRACKER';

-- Supprimer tous les modules ACP Chastity Tracker existants
DELETE FROM phpbb_modules 
WHERE module_basename = '\\verturin\\chastitytracker\\acp\\main_module'
   OR module_basename = '\\vendor\\chastitytracker\\acp\\main_module';

-- Supprimer la catégorie ACP Chastity Tracker
DELETE FROM phpbb_modules 
WHERE module_class = 'acp' 
  AND module_langname = 'ACP_CHASTITY_TRACKER';

-- Supprimer les migrations existantes (optionnel, pour réinstallation complète)
-- DELETE FROM phpbb_migrations 
-- WHERE migration_name LIKE '%chastitytracker%';

-- Après avoir exécuté ce script :
-- 1. Videz le cache phpBB (supprimez le contenu du dossier cache/)
-- 2. Réactivez l'extension dans ACP -> Gestion des extensions
