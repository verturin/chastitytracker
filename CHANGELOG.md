# Changelog — verturin/chastitytracker

---

## [3.0.29] — Avril 2026 — Version de référence Final

### État
Version de référence stable avant nouvelles modifications. Tous les fichiers ont été vérifiés et documentés.

### Inclus dans cette version (cumul 3.0.x)
- ✅ 5 tables DB : `chastity_users`, `chastity_periods`, `chastity_cache`, `chastity_history`, `chastity_user_prefs`
- ✅ 5 permissions ACL : `u_chastity_view`, `u_chastity_manage`, `u_chastity_prefs`, `u_chastity_refresh`, `m_chastity_moderate`
- ✅ 6 modes UCP : `calendar`, `add_past`, `statistics`, `locktober`, `chastprivacy`, `refresh`
- ✅ 3 modes ACP : `settings`, `statistics`, `rebuild`
- ✅ 2 tâches cron phpBB : cache (configurable, défaut 60 min) + historique (configurable, défaut 1440 min)
- ✅ 2 services : `cache_updater` + `history_updater` (update global + update par user)
- ✅ Mode UCP `chastprivacy` (et non `privacy` pour éviter conflit phpBB natif)
- ✅ Toutes les colonnes booléennes en `TINT:1`
- ✅ Templates dans `styles/all/template/` (compatibles tous les thèmes)
- ✅ Navigation header avec icône SVG cadenas
- ✅ Token API personnel (`bin2hex(random_bytes(32))` = 64 hex chars)
- ✅ Préférences de confidentialité avec 8 flags de visibilité
- ✅ Calendrier mensuel avec navigation mois précédent/suivant
- ✅ Calcul durée en jours/heures/minutes (`format_duration()`)
- ✅ Locktober avec classement (JOIN sur `USERS_TABLE` pour pseudos à jour)
- ✅ 20 clés de configuration phpBB
- ✅ Langues FR et EN complètes (170+ clés)
- ✅ Migration unique `install_chastity_tracker.php` avec `init_users()`, `init_cache()`, `init_history()`, `init_prefs()`

---

## [3.0.26] — Nettoyage cron et doublon listener

### Corrections
- ✅ **Suppression du doublon de cron** : Les méthodes `maybe_update_cache()` et `maybe_update_history()` dans le listener (`core.page_header`) ont été supprimées. Le cron phpBB natif (`cron/task/`) est désormais le seul mécanisme de mise à jour automatique.
- ✅ **Migration de nettoyage** : La migration v3026 supprime les 4 configs orphelines (`chastity_last_cache_update`, `chastity_last_history_update`, `chastity_cache_interval`, `chastity_history_interval`) sur les forums existants.
- ✅ **Leaderboard Locktober** : Correction du JOIN sur `USERS_TABLE` au lieu de `chastity_users` pour les pseudos et couleurs toujours à jour.
- ✅ **Nettoyage du listener** : Suppression des commentaires de développement résiduels.

---

## [3.0.25] — Version de référence initiale (série 3.0.x)

### Architecture
- ✅ Renommage préfixe tables de `cetc_` vers `{table_prefix}chastity_*`
- ✅ Migration de toutes les colonnes `BOOL` vers `TINT:1` (compatibilité phpBB 3.3+)
- ✅ Renommage mode UCP de `privacy` vers `chastprivacy` (évite conflit avec module phpBB natif)
- ✅ Correction ordre `add_permissions()` : catégorie ajoutée avant les permissions
- ✅ 5 tables indépendantes créées par migration unique

---

## [1.4.1] — Modernisation des templates Twig

### Templates
- ✅ Migration vers Twig natif (depuis l'ancienne syntaxe `<!-- IF -->`)
- ✅ `lang('COLON')` partout — espacement typographique selon la langue
- ✅ Bloc de garde `{% if S_DISPLAY_CHASTITY %}` dans les event templates
- ✅ Boucles `{% for %}` / `{% endfor %}` remplacent `<!-- BEGIN -->...<!-- END -->`

---

## [1.4.0] — Corrections architecturales

### Performance & Permissions
- ✅ Suppression des configs JSON dans `phpbb_config` (table chargée en mémoire entièrement)
- ✅ Système de permissions natif phpBB exclusif (`u_chastity_view`, `u_chastity_manage`, `m_chastity_moderate`)
- ✅ Suppression de `check_group_permission()` redondante

### Structure
- ✅ Migration vers le thème universel `styles/all/` (tous thèmes sans exception)
- ✅ CSS déplacé dans `styles/all/theme/`
- ✅ Suppression de l'onglet ACP "Permissions" (doublon du système natif phpBB)

---

## [1.3.1] — Corrections namespace et base de données

- ✅ Namespace `vendor` → `verturin`
- ✅ Colonnes `TIMESTAMP` → `UINT:11` (évite l'erreur MySQL "only one auto column")
- ✅ Chemin d'installation : `ext/verturin/chastitytracker/`

---

## [1.3.0] — Intégration du Locktober 🎃

- ✅ Page UCP dédiée Locktober avec classement temps réel
- ✅ Démarrage automatique en octobre, compteur Jour X/31
- ✅ Badge de réussite après 31 jours
- ✅ Colonnes `is_locktober`, `locktober_year`, `locktober_completed`
- ✅ Configuration ACP (activation, année active, badges, leaderboard)

---

## [1.2.0] — Gestion des règles par l'administrateur

- ✅ 5 règles configurables dans l'ACP (activation individuelle)
- ✅ Affichage dynamique selon les règles activées
- ✅ Stockage en base de données par période

---

## [1.1.0] — Système de règles et mode permanent

- ✅ 5 règles personnalisables par période
- ✅ Mode permanent (port de cage sans date de fin)
- ✅ Affichage dépliable des règles par période

---

## [1.0.0] — Version initiale

- ✅ Gestion des périodes (démarrage, fin, suppression, notes)
- ✅ Calendrier UCP et statistiques (total, années, mois, moyenne, record)
- ✅ Affichage statut sur profil et dans les posts
- ✅ Module ACP (paramètres, statistiques globales, top 10)
- ✅ Permissions ACL par groupe
- ✅ Migration automatique à l'activation / suppression à la désinstallation
- ✅ Langues FR et EN
- ✅ Sécurité : tokens CSRF, protection SQL injection, validation des dates
