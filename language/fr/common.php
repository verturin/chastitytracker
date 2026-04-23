<?php
/**
 * Chastity Tracker - Langue française
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(

    // Général
    'CHASTITY_TRACKER'          => 'Suivi de Chasteté',
    'CHASTITY_STATUS'           => 'Statut de Chasteté',
    'CHASTITY_STATUS_FREE'      => 'Libre',
    'CHASTITY_STATUS_LOCKED'    => 'Verrouillé',
    'CHASTITY_STATUS_ACTIVE'    => 'Actif',
    'CHASTITY_STATUS_COMPLETED' => 'Terminé',

    // UCP - Menus
    'UCP_CHASTITY'           => 'Suivi de Chasteté',
    'UCP_CHASTITY_TRACKER'   => 'Suivi de Chasteté',
    'UCP_CHASTITY_CALENDAR'  => 'Calendrier',
    'UCP_CHASTITY_STATISTICS'=> 'Statistiques',
    'UCP_CHASTITY_LOCKTOBER' => 'Locktober',
    'UCP_CHASTITY_ADD_PAST'  => 'Ajouter une période passée',

    // UCP - Calendrier
    'CHASTITY_CALENDAR'          => 'Calendrier de Chasteté',
    'CHASTITY_ADD_PERIOD'        => 'Démarrer une nouvelle période',
    'CHASTITY_ADD_PAST_PERIOD'   => 'Période passée',
    'CHASTITY_END_PERIOD'        => 'Terminer la période',
    'CHASTITY_DELETE_PERIOD'     => 'Supprimer la période',
    'CHASTITY_START_DATE'        => 'Date de début',
    'CHASTITY_END_DATE'          => 'Date de fin',
    'CHASTITY_END_DATE_CUSTOM'   => 'Date de libération',
    'CHASTITY_END_DATE_OPTIONAL' => '(laisser vide = aujourd\'hui)',
	'CHASTITY_DAYS'              => 'Jours',
	'CHASTITY_DAYS_SINCE'        => 'depuis',
    'CHASTITY_NOTES'             => 'Notes',
    'CHASTITY_NO_PERIODS'        => 'Aucune période enregistrée pour le moment.',
    'CHASTITY_CURRENT_PERIOD'    => 'Période en cours',
    'CHASTITY_CURRENT_DAYS'      => 'Jours actuels en chasteté',
    'CHASTITY_PERMANENT'         => 'Port permanent (sans date de fin)',
    'CHASTITY_PERMANENT_MODE'    => 'Mode permanent',
    'CHASTITY_TEMPORARY_MODE'    => 'Mode temporaire',
    'CHASTITY_PERMANENT_EXPLAIN' => 'En mode permanent, il n\'y a pas de date de fin prévue (mais vous pouvez toujours arrêter la période manuellement)',
    'CHASTITY_VIEW_RULES'        => 'Voir les règles',
    'CHASTITY_HIDE_RULES'        => 'Masquer les règles',

    // UCP - Ajout période passée
    'UCP_CHASTITY_ADD_PAST_EXPLAIN'  => 'Ajoutez ici une période de chasteté passée dont vous souhaitez conserver l\'historique. Les statistiques seront recalculées automatiquement.',
    'CHASTITY_PAST_PERIOD_ADDED'     => 'Période passée ajoutée et statistiques recalculées.',
    'CHASTITY_INVALID_DATE_RANGE'    => 'La date de fin doit être postérieure à la date de début.',

    // UCP - Statistiques
    'CHASTITY_TOTAL_DAYS'      => 'Total de jours',
    'CHASTITY_TOTAL_PERIODS'   => 'Total de périodes',
    'CHASTITY_YEAR_DAYS'       => 'Jours cette année',
    'CHASTITY_LONGEST_PERIOD'  => 'Période la plus longue',
    'CHASTITY_AVERAGE_PERIOD'  => 'Durée moyenne',
    'CHASTITY_STATS_BY_YEAR'   => 'Statistiques par année',
    'CHASTITY_STATS'           => 'Statistiques',
    'CHASTITY_BEST_YEAR'       => 'Meilleure année',
    'CHASTITY_STATS_BY_MONTH'  => 'Statistiques par mois (année en cours)',
    'CHASTITY_YEAR'            => 'Année',
    'CHASTITY_MONTH'           => 'Mois',
    'CHASTITY_PERIODS'         => 'Périodes',

    // Règles
    'CHASTITY_RULES'                 => 'Règles de cette période',
    'CHASTITY_RULES_EXPLAIN'         => 'Définissez les règles pour cette mise en cage',
    'CHASTITY_RULE_MASTURBATION'     => 'Peut se masturber',
    'CHASTITY_RULE_EJACULATION'      => 'Peut éjaculer',
    'CHASTITY_RULE_SLEEP_REMOVAL'    => 'Peut retirer la cage pour dormir',
    'CHASTITY_RULE_PUBLIC_REMOVAL'   => 'Peut retirer la cage (vestiaires, plages naturistes...)',
    'CHASTITY_RULE_MEDICAL_REMOVAL'  => 'Peut retirer la cage pour urgences médicales',
    'CHASTITY_YES'                   => 'Autorisé',
    'CHASTITY_NO'                    => 'Interdit',

    // Locktober
    'CHASTITY_LOCKTOBER'                  => 'Locktober',
    'CHASTITY_LOCKTOBER_CHALLENGE'        => 'Défi Locktober',
    'CHASTITY_LOCKTOBER_PARTICIPATE'      => 'Participer au Locktober',
    'CHASTITY_LOCKTOBER_EXPLAIN'          => 'Le Locktober est un défi annuel de chasteté pendant tout le mois d\'octobre (31 jours)',
    'CHASTITY_LOCKTOBER_START'            => 'Démarrer le Locktober',
    'CHASTITY_LOCKTOBER_ACTIVE'           => 'Locktober en cours',
    'CHASTITY_LOCKTOBER_COMPLETED'        => 'Locktober réussi ! 🎉',
    'CHASTITY_LOCKTOBER_FAILED'           => 'Locktober abandonné',
    'CHASTITY_LOCKTOBER_PARTICIPANTS'     => 'Participants au Locktober',
    'CHASTITY_LOCKTOBER_LEADERBOARD'      => 'Classement Locktober',
    'CHASTITY_LOCKTOBER_DAY'              => 'Jour %d/31',
    'CHASTITY_LOCKTOBER_WINNERS'          => 'Gagnants du Locktober',
    'CHASTITY_LOCKTOBER_BADGE'            => 'Badge Locktober',
    'CHASTITY_LOCKTOBER_YEAR'             => 'Locktober %d',
    'CHASTITY_LOCKTOBER_JOIN_EXPLAIN'     => 'Rejoignez le défi Locktober et tentez de rester en chasteté pendant les 31 jours d\'octobre !',
    'CHASTITY_LOCKTOBER_WAIT'             => 'Le défi Locktober n\'est disponible qu\'en octobre.',
    'CHASTITY_LOCKTOBER_NEXT_YEAR'        => 'Revenez en octobre pour participer au prochain défi !',
    'CHASTITY_LOCKTOBER_COMPLETE_MESSAGE' => 'Félicitations ! Vous avez terminé le défi Locktober avec succès !',
    'CHASTITY_LOCKTOBER_STARTED'          => 'Vous avez rejoint le défi Locktober ! Bonne chance !',
    'CHASTITY_LOCKTOBER_NOT_OCTOBER'      => 'Le Locktober ne peut être démarré qu\'en octobre.',
    'CHASTITY_LOCKTOBER_DISABLED'         => 'Le Locktober est actuellement désactivé.',

    // Messages système
    'CHASTITY_PERIOD_ADDED'     => 'Période de chasteté démarrée avec succès.',
    'CHASTITY_PERIOD_ENDED'     => 'Période de chasteté terminée avec succès.',
    'CHASTITY_PERIOD_DELETED'   => 'Période supprimée avec succès.',
    'CHASTITY_ALREADY_ACTIVE'   => 'Vous avez déjà une période de chasteté active.',
    'CHASTITY_INVALID_DATE'     => 'Date invalide. La date ne peut pas être dans le futur.',
    'CHASTITY_PERIOD_NOT_FOUND' => 'Période non trouvée.',
    'CHASTITY_END_PERIOD_CONFIRM' => 'Êtes-vous sûr de vouloir terminer cette période de chasteté ?',

    // Profil
    'CHASTITY_PROFILE_STATUS'      => 'Statut de chasteté',
    'CHASTITY_PROFILE_LOCKED_SINCE'=> 'Verrouillé depuis',
    'CHASTITY_PROFILE_TOTAL_DAYS'  => 'Total de jours en chasteté',
    'CHASTITY_PROFILE_CALENDAR'    => 'Calendrier',
	'CHASTITY_PROFILE_MONTH'	   => 'Derniers Mois',	

	// Navlink
    'CHASTITY_NAV_LINK_LABEL' => 'Mon suivi',
    'CHASTITY_NAV_LINK_TITLE' => 'Accéder à mon suivi de chasteté',


    // ACP - Menus
    'ACP_CHASTITY_TITLE'      => 'Suivi de Chasteté',
    'ACP_CHASTITY_TRACKER'    => 'Suivi de Chasteté',
    'ACP_CHASTITY_SETTINGS'   => 'Paramètres',
    'ACP_CHASTITY_STATISTICS' => 'Statistiques',
    'ACP_CHASTITY_REBUILD'    => 'Reconstruire les compteurs',


'ACP_CHASTITY_BACKUP'                 => 'Sauvegarde',
'ACP_CHASTITY_BACKUP_EXPLAIN'          => 'Exportez ou restaurez les données de chasteté (membres et périodes).',
'ACP_CHASTITY_BACKUP_EXPORT'           => 'Exporter les données',
'ACP_CHASTITY_BACKUP_EXPORT_EXPLAIN'   => 'Le fichier contiendra %d membres et %d périodes.',
'ACP_CHASTITY_BACKUP_EXPORT_BTN'       => 'Télécharger la sauvegarde (.sql)',
'ACP_CHASTITY_BACKUP_RESTORE'          => 'Restaurer une sauvegarde',
'ACP_CHASTITY_BACKUP_RESTORE_WARN'     => 'La restauration efface toutes les données existantes avant de les remplacer.',
'ACP_CHASTITY_BACKUP_FILE'             => 'Fichier de sauvegarde (.sql)',
'ACP_CHASTITY_BACKUP_RESTORE_BTN'      => 'Restaurer',
'ACP_CHASTITY_BACKUP_RESTORE_CONFIRM'  => 'Confirmer la restauration ? Les données actuelles seront effacées.',
'ACP_CHASTITY_BACKUP_RESTORED'         => 'Restauration terminée — %d enregistrements importés.',
'ACP_CHASTITY_BACKUP_NO_FILE'          => 'Aucun fichier sélectionné.',
'ACP_CHASTITY_BACKUP_INVALID'          => "Ce n'est pas une sauvegarde Chastity Tracker.",
'CHASTITY_LEADERBOARD_TITLE'           => '🏆 Classement Chasteté',
'CHASTITY_LEADERBOARD_YEAR'            => 'Top %d — Meilleure période',
'CHASTITY_LEADERBOARD_BEST_YEAR'       => 'Top — Meilleure année',
'CHASTITY_LEADERBOARD_ALLTIME'         => 'Top — Tous temps',

	

    // ACP - Paramètres
    'ACP_CHASTITY_SETTINGS_EXPLAIN'          => 'Configurez les paramètres de l\'extension Suivi de Chasteté.',
    'ACP_CHASTITY_ENABLE'                    => 'Activer le suivi de chasteté',
    'ACP_CHASTITY_PROFILE_DISPLAY'           => 'Afficher le statut sur les profils et posts',
    'ACP_CHASTITY_MIN_PERIOD_DAYS'           => 'Jours minimum par période',
    'ACP_CHASTITY_MIN_PERIOD_DAYS_EXPLAIN'   => 'Nombre minimum de jours requis pour valider une période (0 = aucune limite)',
    'ACP_CHASTITY_RULES_SETTINGS'            => 'Configuration des règles',
    'ACP_CHASTITY_RULES_SETTINGS_EXPLAIN'    => 'Choisissez quelles règles seront disponibles aux utilisateurs lors de la création d\'une période.',
    'ACP_CHASTITY_RULE_ENABLE_EXPLAIN'       => 'Activer cette règle pour qu\'elle soit proposée aux utilisateurs',

    // ACP - Locktober
    'ACP_CHASTITY_LOCKTOBER'                      => 'Locktober',
    'ACP_CHASTITY_LOCKTOBER_SETTINGS'             => 'Paramètres Locktober',
    'ACP_CHASTITY_LOCKTOBER_SETTINGS_EXPLAIN'     => 'Configurez les paramètres pour le défi annuel Locktober.',
    'ACP_CHASTITY_LOCKTOBER_ENABLED'              => 'Activer le Locktober',
    'ACP_CHASTITY_LOCKTOBER_ENABLED_EXPLAIN'      => 'Permet aux utilisateurs de participer au défi Locktober',
    'ACP_CHASTITY_LOCKTOBER_YEAR'                 => 'Année Locktober active',
    'ACP_CHASTITY_LOCKTOBER_YEAR_EXPLAIN'         => 'Année en cours pour le défi Locktober',
    'ACP_CHASTITY_LOCKTOBER_BADGE_ENABLED'        => 'Afficher les badges Locktober',
    'ACP_CHASTITY_LOCKTOBER_BADGE_EXPLAIN'        => 'Affiche un badge spécial pour les participants et gagnants',
    'ACP_CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED'  => 'Activer le classement Locktober',
    'ACP_CHASTITY_LOCKTOBER_LEADERBOARD_EXPLAIN'  => 'Affiche un classement public des participants',

    // ACP - Statistiques
    'ACP_CHASTITY_STATISTICS_EXPLAIN' => 'Vue globale des données de chasteté sur le forum.',
    'ACP_CHASTITY_GLOBAL_STATS'       => 'Statistiques globales',
    'ACP_CHASTITY_TOP_USERS'          => 'Meilleurs utilisateurs',
    'ACP_CHASTITY_ACTIVE_PERIODS'     => 'Périodes actives',
    'ACP_CHASTITY_TOTAL_USERS'        => 'Utilisateurs participants',
    'ACP_CHASTITY_AVERAGE_DAYS'       => 'Durée moyenne par période',

    // ACP - Rebuild
    'ACP_CHASTITY_REBUILD_EXPLAIN'  => 'Recalcule tous les totaux de jours et statuts pour l\'ensemble des utilisateurs. À utiliser si des incohérences sont détectées dans les statistiques.',
    'ACP_CHASTITY_REBUILD_STATUS'   => 'État actuel',
    'ACP_CHASTITY_REBUILD_WARNING'  => 'Cette opération peut prendre quelques secondes selon le nombre d\'utilisateurs.',
    'ACP_CHASTITY_REBUILD_SUBMIT'   => 'Lancer la reconstruction',
    'ACP_CHASTITY_REBUILD_CONFIRM'  => 'Confirmer la reconstruction de tous les compteurs ?',
    'ACP_CHASTITY_REBUILD_DONE'     => 'Reconstruction terminée : %d utilisateur(s) mis à jour.',

	// Maintenance / cache manuel
	'ACP_CHASTITY_CACHE_UPDATE_EXPLAIN'   => 'Lancer manuellement le recalcul du cache pour tous les utilisateurs.',
	'ACP_CHASTITY_CACHE_UPDATE_SUBMIT'    => 'Recalculer le cache',
	'ACP_CHASTITY_HISTORY_UPDATE_EXPLAIN' => 'Lancer manuellement le recalcul de l\'historique annuel.',
	'ACP_CHASTITY_HISTORY_UPDATE_SUBMIT'  => 'Recalculer l\'historique',

    // Permissions ACL
    'ACL_U_CHASTITY_VIEW'     => 'Peut voir le suivi de chasteté',
    'ACL_U_CHASTITY_MANAGE'   => 'Peut gérer ses propres périodes de chasteté',
    'ACL_M_CHASTITY_MODERATE' => 'Peut modérer les périodes de chasteté',
    
    // Maintenance v3.0.18
    'ACP_CHASTITY_MAINTENANCE' => 'Maintenance',
    'ACP_CHASTITY_MAINTENANCE_EXPLAIN' => 'Mise à jour manuelle des tables cache et historique.',
    'ACP_CHASTITY_CACHE' => 'Cache de performance',
    'ACP_CHASTITY_CACHE_ENTRIES' => 'Entrées en cache',
    'ACP_CHASTITY_CACHE_INFO' => 'Informations',
    'ACP_CHASTITY_CACHE_EXPLAIN' => 'Le cache stocke les statistiques actuelles pour affichage rapide dans les posts et profils.',
    'ACP_CHASTITY_UPDATE_CACHE' => '🔄 Mettre à jour le cache',
    'ACP_CHASTITY_HISTORY' => 'Historique annuel',
    'ACP_CHASTITY_HISTORY_ENTRIES' => 'Entrées historique',
    'ACP_CHASTITY_HISTORY_INFO' => 'Informations',
    'ACP_CHASTITY_HISTORY_EXPLAIN' => 'L\'historique stocke les totaux par année pour chaque utilisateur.',
    'ACP_CHASTITY_UPDATE_HISTORY' => '📊 Mettre à jour l\'historique',
    'ACP_CHASTITY_USERS' => 'utilisateurs',
    'ACP_CHASTITY_ENTRIES' => 'entrées',

    // E1 — Intervalles de mise à jour
    'ACP_CHASTITY_INTERVALS'                => 'Intervalles de mise à jour automatique',
    'ACP_CHASTITY_INTERVALS_EXPLAIN'        => 'Fréquence de mise à jour du cache et de l\'historique lors des visites du forum.',
    'ACP_CHASTITY_CACHE_INTERVAL'           => 'Intervalle du cache',
    'ACP_CHASTITY_CRON_ENABLED'  => '✅ Cron actif — recalcul automatique en cours',
    'ACP_CHASTITY_CRON_DISABLED' => '🔴 Cron désactivé — aucun recalcul automatique',
    'ACP_CHASTITY_CRON_ENABLE'   => 'Activer le cron',
    'ACP_CHASTITY_CRON_DISABLE'  => 'Désactiver le cron',
    'ACP_CHASTITY_CACHE_INTERVAL_EXPLAIN'   => 'Délai minimum en minutes entre deux recalculs automatiques du cache.',
    'ACP_CHASTITY_HISTORY_INTERVAL'         => 'Intervalle de l\'historique',
    'ACP_CHASTITY_HISTORY_INTERVAL_EXPLAIN' => 'Délai minimum en minutes entre deux recalculs automatiques des totaux annuels.',
    'ACP_CHASTITY_MINUTES'                  => 'minutes',

    // E2 — Confidentialité
    'UCP_CHASTITY_REFRESH'              => 'Actualiser mes données',
    'UCP_CHASTITY_REFRESH_EXPLAIN'       => 'Forcer la mise à jour de votre cache de performance et de votre historique annuel.',
    'CHASTITY_REFRESH_CACHE'             => 'Actualiser le cache',
    'CHASTITY_REFRESH_CACHE_EXPLAIN'     => 'Met à jour vos statistiques de performance (jours actuels, jours de l\'année).',
    'CHASTITY_REFRESH_HISTORY'           => 'Actualiser l\'historique',
    'CHASTITY_REFRESH_HISTORY_EXPLAIN'   => 'Met à jour vos totaux annuels.',
    'CHASTITY_REFRESH_DONE'              => 'Vos données ont été mises à jour.',
    'UCP_CHASTITY_CHASTPRIVACY'                => 'Confidentialité',
    'UCP_CHASTITY_CHASTPRIVACY_EXPLAIN'        => 'Choisissez quelles informations sont visibles par les autres membres.',
    'CHASTITY_PREFS_PROFILE'            => 'Informations du profil',
    'CHASTITY_PREFS_VISIBILITY'         => 'Visibilité',
    'CHASTITY_PREFS_SHOW_STATUS'        => 'Afficher mon statut (verrouillé/libre)',
    'CHASTITY_PREFS_SHOW_DAYS'          => 'Afficher le nombre de jours',
    'CHASTITY_PREFS_SHOW_TOTAL'         => 'Afficher le total de jours',
    'CHASTITY_PREFS_SHOW_YEAR_STATS'    => 'Afficher les jours de l\'année en cours',
    'CHASTITY_PREFS_SHOW_BEST_YEAR'     => 'Afficher la meilleure année',
    'CHASTITY_PREFS_SHOW_BEST_MONTH'    => 'Afficher le meilleur mois',
    'CHASTITY_PREFS_SHOW_IN_POSTS'      => 'Afficher le badge dans mes messages',
    'CHASTITY_PREFS_SHOW_IN_CONTACT'    => 'Afficher le statut sur ma page de contact',
    'CHASTITY_PREFS_SAVED'              => 'Préférences enregistrées.',

    // Meilleur mois — absent dans la version serveur
    'CHASTITY_BEST_MONTH'                 => 'Meilleur mois',

    // H1 — Heure facultative
    'CHASTITY_TIME_OPTIONAL'              => '(heure facultative)',

    // API2 — Nécessaire pour le template ucp_chastity_prefs.html
    'CHASTITY_API_ACCESS'          => 'Accès API externe',
    'CHASTITY_API_EXPLAIN'         => 'Permettez à des applications externes d\'afficher votre statut.',
    'CHASTITY_API_ACTIVE'          => 'Accès API activé',
    'CHASTITY_API_DISABLED'        => 'Accès API désactivé',
    'CHASTITY_API_TOKEN_LABEL'     => 'Votre token personnel',
    'CHASTITY_API_URL_EXAMPLE'     => 'URL d\'appel',
    'CHASTITY_API_GENERATE'        => 'Activer l\'accès API et générer un token',
    'CHASTITY_API_REVOKE'          => 'Révoquer l\'accès API',
    'CHASTITY_API_REVOKE_CONFIRM'  => 'Révoquer ? Les applications utilisant ce token perdront l\'accès.',
    'CHASTITY_API_TOKEN_GENERATED' => 'Token généré. Copiez-le maintenant.',
    'CHASTITY_API_TOKEN_REVOKED'   => 'Accès API révoqué.',

    // Messages retour ACP maintenance
    'ACP_CHASTITY_CACHE_UPDATED'   => 'Cache recalculé pour %d utilisateur(s).',
    'ACP_CHASTITY_HISTORY_UPDATED' => 'Historique recalculé pour %d entrée(s).',

    // C2 — Calendrier annuel UCP
    'UCP_CHASTITY_YEARVIEW'             => 'Vue annuelle',
    'CHASTITY_YEARVIEW_TOTAL'           => 'jours verrouillés en',
    'CHASTITY_YEARVIEW_LEGEND_LOCKED'   => 'Verrouillé',
    'CHASTITY_YEARVIEW_LEGEND_FREE'     => 'Libre',
    'CHASTITY_YEARVIEW_LEGEND_TODAY'    => 'Aujourd\'hui',

));
