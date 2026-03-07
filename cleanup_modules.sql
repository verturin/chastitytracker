-- Script de nettoyage manuel pour Chastity Tracker
-- À exécuter dans phpMyAdmin ou en ligne de commande MySQL si vous avez des erreurs de modules
-- IMPORTANT : Remplacez 'phpbb_' par le préfixe de votre table si différent

-- ============================================================
-- MIGRATION DEPUIS v1.3.x : Supprimer le module permissions ACP
-- (ce module a été supprimé en v1.4.0 car redondant avec le système ACL natif phpBB)
-- ============================================================
DELETE FROM phpbb_modules
WHERE module_class    = 'acp'
  AND module_basename = '\\verturin\\chastitytracker\\acp\\main_module'
  AND module_mode     = 'permissions';

-- Supprimer aussi les configs JSON de groupes dans phpbb_config (v1.4.0)
DELETE FROM phpbb_config WHERE config_name IN (
    'chastity_view_groups',
    'chastity_manage_groups',
    'chastity_view_stats_groups',
    'chastity_view_others_groups'
);

-- Vider le cache phpBB après exécution (ou via ACP -> Maintenance -> Vider le cache)

-- ============================================================
-- REINSTALLATION COMPLÈTE (si problèmes persistants)
-- ============================================================
-- Supprimer tous les modules UCP
-- DELETE FROM phpbb_modules
-- WHERE module_basename = '\\verturin\\chastitytracker\\ucp\\main_module'
--    OR module_basename = '\\vendor\\chastitytracker\\ucp\\main_module';
-- DELETE FROM phpbb_modules WHERE module_class = 'ucp' AND module_langname = 'UCP_CHASTITY_TRACKER';

-- Supprimer tous les modules ACP
-- DELETE FROM phpbb_modules
-- WHERE module_basename = '\\verturin\\chastitytracker\\acp\\main_module'
--    OR module_basename = '\\vendor\\chastitytracker\\acp\\main_module';
-- DELETE FROM phpbb_modules WHERE module_class = 'acp' AND module_langname = 'ACP_CHASTITY_TRACKER';

-- Supprimer les migrations (réinstallation complète)
-- DELETE FROM phpbb_migrations WHERE migration_name LIKE '%chastitytracker%';
