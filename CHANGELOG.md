# Changelog - Chastity Tracker Extension

## Version 1.4.1 - Modernisation des templates Twig

### Templates
- ✅ **Migration vers Twig natif** : Tous les templates convertis de l'ancienne syntaxe phpBB (`<!-- IF -->`) vers Twig (`{% if %}`), natif depuis phpBB 3.1, plus rapide à parser et mieux mis en cache
- ✅ **`lang('COLON')` partout** : Remplacement des deux-points codés en dur par la variable de langue phpBB, respectant l'espacement typographique selon la langue de l'utilisateur (ex. espace insécable en français)
- ✅ **Bloc de garde `{% if S_DISPLAY_CHASTITY %}`** : Tout le code des templates d'événements est encapsulé dans un bloc unique — si l'utilisateur n'a pas la permission, le moteur Twig ignore l'intégralité du rendu en un seul test
- ✅ **Boucles Twig** : Remplacement de `<!-- BEGIN -->...<!-- END -->` par `{% for %}...{% endfor %}`
- ✅ **Includes Twig** : `<!-- INCLUDE -->` remplacé par `{% include %}`

## Version 1.4.0 - Corrections architecturales

### Corrections critiques

#### Performance & Cache phpBB
- ✅ **Suppression des configs JSON dans phpbb_config** : Les 4 entrées `chastity_*_groups` stockées en JSON dans `phpbb_config` ont été supprimées. Cette table est chargée intégralement en mémoire à chaque requête — y stocker des données variables est une erreur de performance.
- ✅ **Système de permissions natif phpBB** : Le contrôle d'accès par groupe repose désormais exclusivement sur les permissions phpBB (`u_chastity_view`, `u_chastity_manage`, `m_chastity_moderate`), configurables depuis l'ACP → Permissions → Groupes. Ce système est optimisé, mis en cache correctement et bien connu des administrateurs.
- ✅ **Suppression de `check_group_permission()`** : La méthode redondante qui dupliquait le système ACL phpBB a été retirée du listener.

#### Structure des templates
- ✅ **Migration vers le thème universel `all`** : Tous les templates ont été déplacés de `styles/prosilver/template/` vers `styles/all/template/`. Le dossier `all` est le fallback phpBB qui s'applique à tous les thèmes sans exception (prosilver, subsilver2, thèmes tiers, etc.).
- ✅ **Suppression du dossier `adm/style/`** : Les templates ACP sont maintenant dans `styles/all/template/`, conforme aux bonnes pratiques phpBB 3.2+.
- ✅ **CSS déplacé** : `chastity.css` déplacé dans `styles/all/theme/`.

### Module ACP
- ✅ **Suppression de l'onglet "Permissions"** : Cet onglet dupliquait inutilement le système de permissions natif phpBB. Les permissions se gèrent maintenant depuis l'ACP standard (Permissions → Groupes d'utilisateurs).

### Guide de migration depuis v1.3.x
Si vous mettez à jour depuis une version précédente :
1. Désactivez l'extension depuis l'ACP
2. Remplacez les fichiers
3. Réactivez l'extension
4. Configurez les permissions dans ACP → Permissions → Groupes → choisissez les groupes autorisés pour `u_chastity_view` et `u_chastity_manage`


## Version 1.3.1 - Corrections namespace et base de données

### Corrections
- ✅ **Namespace corrigé** : Changement de `vendor` vers `verturin` pour correspondre au repo GitHub
- ✅ **Erreur MySQL corrigée** : Remplacement des colonnes TIMESTAMP par UINT:11 pour éviter l'erreur "only one auto column"
- ✅ **Structure de dossier** : Le dossier doit être nommé `chastitytracker` (sans underscore)
- ✅ **Chemin d'installation** : `phpbb/ext/verturin/chastitytracker/`

### Chemins mis à jour
- Ancien : `ext/vendor/chastitytracker/`
- Nouveau : `ext/verturin/chastitytracker/`

## Version 1.3.0 - Intégration du Locktober 🎃

### Nouvelles fonctionnalités

#### Défi Locktober complet
- ✅ **Page dédiée Locktober** : Nouvel onglet dans le panneau utilisateur
- ✅ **Démarrage automatique** : Bouton pour rejoindre le défi en octobre
- ✅ **Compteur de jours** : Affichage du jour actuel sur 31 jours (Jour X/31)
- ✅ **Règles strictes par défaut** : Toutes les règles désactivées sauf urgences médicales
- ✅ **Classement en temps réel** : Leaderboard des participants actuels avec médailles (🥇🥈🥉)
- ✅ **Historique personnel** : Liste des Locktober complétés les années précédentes
- ✅ **Badge de réussite** : Affichage spécial quand les 31 jours sont atteints
- ✅ **Design thématique** : Interface orange festive avec emojis 🎃🔒

#### Configuration administrateur
- ✅ **Activer/Désactiver le Locktober** : Option globale dans l'ACP
- ✅ **Année active** : Définir l'année du défi en cours
- ✅ **Affichage des badges** : Activer/désactiver les badges Locktober
- ✅ **Classement** : Activer/désactiver le leaderboard public
- ✅ **Section dédiée** : Paramètres Locktober dans l'ACP Settings

#### Fonctionnement technique
- ✅ **Détection automatique d'octobre** : Le bouton de participation n'apparaît qu'en octobre
- ✅ **Stockage en BDD** : Nouvelles colonnes `is_locktober`, `locktober_year`, `locktober_completed`
- ✅ **Validation automatique** : Marque comme complété après 31 jours
- ✅ **Pas de chevauchement** : Impossible de démarrer si une période est déjà active
- ✅ **Date flexible** : Peut être démarré n'importe quel jour d'octobre

#### Expérience utilisateur
- Design orange vif avec dégradés festifs
- Emojis thématiques (🎃🔒🏆🎉)
- Messages d'encouragement
- Affichage des médailles dans le classement
- Message spécial de félicitations après 31 jours
- Indication claire hors octobre (attente du prochain défi)

### Base de données
- Colonne `is_locktober` : Identifie les périodes Locktober
- Colonne `locktober_year` : Année du défi
- Colonne `locktober_completed` : Marque si le défi a été complété
- Index sur `is_locktober` pour optimiser les requêtes

### Traductions
- Tous les textes Locktober en français et anglais
- Messages spécifiques au contexte (en cours, terminé, hors période)

## Version 1.2.0 - Gestion des règles par l'administrateur

### Nouvelles fonctionnalités

#### Panneau d'administration des règles
- ✅ **Configuration des règles dans l'ACP** : L'administrateur peut activer/désactiver chaque règle individuellement
- ✅ **Affichage dynamique** : Seules les règles activées par l'administrateur apparaissent dans le formulaire utilisateur
- ✅ **Flexibilité maximale** : Permet d'adapter l'extension aux besoins spécifiques du forum
- ✅ **Interface intuitive** : Section dédiée dans les paramètres ACP avec explication de chaque règle
- ✅ **Sauvegarde en base de données** : Configuration persistante des règles activées

#### Fonctionnement
1. L'administrateur accède à **ACP → Extensions → Chastity Tracker → Paramètres**
2. Dans la section "Configuration des Règles", il active/désactive les règles souhaitées
3. Les utilisateurs ne voient que les règles activées dans leur formulaire de création de période
4. L'historique des périodes affiche également uniquement les règles activées

### Améliorations
- Meilleure organisation du panneau ACP avec section dédiée aux règles
- Textes d'explication pour guider l'administrateur
- Optimisation du code pour gérer les règles conditionnelles
- Par défaut, toutes les règles sont activées lors de l'installation

## Version 1.1.0 - Ajout des règles personnalisables

### Nouvelles fonctionnalités

#### Système de règles pour chaque période
- ✅ 5 règles personnalisables pour chaque période :
  - Peut ou ne peut pas se branler
  - Peut ou ne peut pas éjaculer
  - Peut ou ne peut pas retirer la cage pour dormir
  - Peut ou ne peut pas retirer la cage sur les plages naturistes, vestiaires, etc.
  - Peut ou ne peut pas retirer la cage pour urgences médicales
- ✅ Configuration des règles lors de la création d'une période
- ✅ Affichage des règles pour chaque période (bouton "Voir les règles")
- ✅ Indicateurs visuels (vert = autorisé, rouge = interdit)

#### Mode permanent
- ✅ Option "Port permanent" sans date de fin prévue
- ✅ Icône ♾️ pour indiquer les périodes permanentes
- ✅ Possibilité d'arrêter manuellement une période permanente à tout moment
- ✅ Différenciation visuelle entre mode temporaire et permanent

### Améliorations
- Affichage des règles dans un panneau dépliable pour ne pas surcharger l'interface
- Meilleure organisation du formulaire de création de période
- Règles stockées en base de données pour chaque période
- Traductions complètes en français et anglais

## Version 1.0.0 - Date de sortie initiale

[... reste du changelog inchangé ...]

---

**Note v1.3.0** : Intégration complète du défi Locktober avec classement, badges et design thématique festif ! 🎃🔒

## Version 1.0.0 - Date de sortie initiale

### Fonctionnalités principales

#### Gestion des périodes de chasteté
- ✅ Démarrage de nouvelles périodes avec date de début personnalisable
- ✅ Fin de période avec calcul automatique du nombre de jours
- ✅ Ajout de notes personnelles pour chaque période
- ✅ Suppression des périodes terminées
- ✅ Système de validation pour empêcher les périodes multiples actives

#### Calendrier et suivi
- ✅ Interface calendrier dans le panneau utilisateur (UCP)
- ✅ Affichage de toutes les périodes (actives et terminées)
- ✅ Compteur en temps réel pour la période active
- ✅ Vue chronologique des périodes passées

#### Statistiques détaillées
- ✅ Total de jours en chasteté (tous temps)
- ✅ Nombre total de périodes enregistrées
- ✅ Jours de chasteté par année
- ✅ Jours de chasteté par mois (année en cours)
- ✅ Période la plus longue
- ✅ Durée moyenne des périodes
- ✅ Nombre de jours actuels (période active)

#### Affichage sur le profil
- ✅ Statut visible sur la page de profil utilisateur
- ✅ Statut affiché dans les posts du forum
- ✅ Icônes visuelles (🔒 verrouillé / 🔓 libre)
- ✅ Information "verrouillé depuis" avec date
- ✅ Compteur de jours actuels pour période active
- ✅ Total de jours en chasteté sur le profil

#### Panneau d'administration (ACP)
- ✅ Module de paramètres pour configurer l'extension
- ✅ Activation/désactivation globale
- ✅ Option d'affichage sur les profils
- ✅ Paramètre de nombre minimum de jours
- ✅ Statistiques globales du forum
- ✅ Top 10 des utilisateurs par total de jours
- ✅ Nombre de périodes actives
- ✅ Nombre d'utilisateurs participants

#### Système de permissions
- ✅ Permission "Voir le suivi de chasteté" (u_chastity_view)
- ✅ Permission "Gérer ses périodes" (u_chastity_manage)
- ✅ Permission "Modérer les périodes" (m_chastity_moderate)
- ✅ Configuration par groupe d'utilisateurs
- ✅ Contrôle d'accès granulaire

#### Base de données
- ✅ Table chastity_periods pour stocker les périodes
- ✅ Colonnes ajoutées à la table users (statut, période active, total)
- ✅ Migration automatique lors de l'activation
- ✅ Suppression propre lors de la désinstallation

#### Internationalisation
- ✅ Support complet en anglais (EN)
- ✅ Support complet en français (FR)
- ✅ Tous les textes traduisibles
- ✅ Fichiers de langue séparés

#### Interface utilisateur
- ✅ Templates HTML pour tous les écrans
- ✅ Fichier CSS personnalisé
- ✅ Design responsive compatible avec le thème Prosilver
- ✅ Formulaires intuitifs et faciles à utiliser
- ✅ Messages de confirmation pour les actions importantes
- ✅ Affichage conditionnel selon le statut

#### Sécurité
- ✅ Validation des formulaires avec jetons CSRF
- ✅ Vérification des permissions à chaque action
- ✅ Protection contre les injections SQL
- ✅ Validation des dates (pas de dates futures)
- ✅ Contrôle de l'accès aux données des autres utilisateurs

#### Documentation
- ✅ README complet avec toutes les fonctionnalités
- ✅ Guide d'installation pas à pas
- ✅ Guide d'utilisation pour les membres
- ✅ Guide d'administration
- ✅ Instructions de désinstallation

### Compatibilité
- phpBB 3.2.0 ou supérieur
- PHP 7.1 ou supérieur
- Compatible avec le style Prosilver (et dérivés)

### Notes techniques
- Architecture événementielle (Event Listeners)
- Utilisation du système de services Symfony
- Migrations de base de données automatiques
- Code conforme aux standards phpBB
- Système de modules ACP/UCP

### Fonctionnalités futures potentielles (non incluses)
- Graphiques et visualisations
- Export des données (CSV, PDF)
- Comparaison entre utilisateurs
- Objectifs et défis
- Notifications
- Intégration API externe
- Application mobile

---

**Note v1.2.0** : Ajout de la gestion des règles dans le panneau d'administration pour une personnalisation totale de l'extension.
