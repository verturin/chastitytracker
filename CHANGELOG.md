# Changelog — verturin/chastitytracker

---

## [3.14.21] — Juillet 2026 — **Migration 100% Twig (catalogue de cages)**

### Modifié
- Les 7 derniers fichiers de templates utilisant encore la syntaxe classique phpBB (`<!-- IF -->`/`<!-- BEGIN -->`, héritée du tout début du module Cages) sont désormais entièrement convertis en Twig, comme le reste de l'extension : `ucp_chastity_cage_catalog.html`, `ucp_chastity_cage_collection.html`, `chastity_public_cages.html`, `acp_chastity_cage_catalog.html`, `acp_chastity_cage_materials.html`, `acp_chastity_cage_manufacturers.html`, `acp_chastity_cage_comments.html`. Aucun changement fonctionnel ni visuel — réécriture technique pure, chaque fichier vérifié individuellement (résidus de syntaxe, équilibre des balises, comparaison ligne à ligne avec l'original) avant remplacement.
- L'extension est désormais 100% Twig sur l'ensemble de ses templates.

---

## [3.14.20] — Juillet 2026 — **Locktober : les périodes terminées restent au classement**

### Corrigé
- Le classement Locktober ne conservait que les participants encore actuellement verrouillés (`status = 'active'`) — un participant qui termine ou arrête sa période en cours de mois disparaissait purement et simplement du classement au lieu d'y rester avec son total final de jours. La requête inclut désormais aussi les périodes `completed`, avec leur nombre de jours figé à la clôture (`days_count`).

---

## [3.14.19] — Juillet 2026 — **Locktober : tri du classement**

### Corrigé
- Le classement Locktober (leaderboard) était trié par date de début de période (`ORDER BY start_date ASC`), pas par nombre de jours effectivement tenus — sans effet pratique puisque la quasi-totalité des participants démarre autour du 1ᵉʳ octobre. Le tri se fait désormais réellement par jours décroissants (calculés en PHP pour rester portable entre les différents moteurs SQL supportés par phpBB), avec en cas d'égalité un départage par date de départ la plus ancienne.

---

## [3.14.18] — Juillet 2026 — **Affichage sub-24h pour le statut Libre**

### Corrigé
- L'affichage "depuis Xh" pour les durées inférieures à 24h ne s'appliquait qu'au statut Verrouillé. Un membre tout juste libéré affichait "0 jour" (ou restait carrément masqué dans le mini-profil des messages, confondu avec "n'a jamais eu de période") au lieu de "3h20" par exemple. Corrigé sur les 3 surfaces concernées : badge PNG (widget/signature), mini-profil des messages du forum, et profil complet (onglet Contact). Bascule vers l'affichage en jours dès 24h écoulées, comme pour le statut Verrouillé.

---

## [3.14.17] — Juillet 2026 — **Mot de sécurité obligatoire**

### Modifié
- Le mot de sécurité est désormais **obligatoire** pour soumettre un contrat en validation finale. Sans lui, aucune des deux parties ne pouvait suspendre ou arrêter le contrat par elles-mêmes une fois actif (seules la fin de la relation Keyholder ou une intervention admin le permettaient) — insuffisant pour la sécurité des deux parties. Le bouton « Soumettre pour validation » reste masqué, remplacé par un message explicite, tant qu'aucun mot de sécurité n'est défini.

---

## [3.14.16] — Juillet 2026

### Corrigé
- Section « Mot de sécurité » de l'export PDF/imprimable coupée entre deux pages (même défaut que celui déjà corrigé pour les articles) — regroupée dans un bloc insécable. Correctif préventif appliqué également au Préambule (même risque latent).

---

## [3.14.15] — Juillet 2026 — **Genre des parties, Préambule et page de signature**

### Ajouté
- Choix du genre de la Keyholder externe (case à cocher Un/Une Keyholder) à la saisie de son pseudo/email — nouvelle colonne `kh_external_gender` (migration dédiée). Pour une Keyholder inscrite, le genre déjà défini dans ses préférences d'extension (`chastity_user_prefs.gender`) est réutilisé directement, sans duplication.
- Accord grammatical complet du contrat selon le genre des deux parties (Madame/Monsieur, LA/LE KEYHOLDER, « dit »/« dite »...), appliqué à l'export PDF (HTML et TCPDF) et à la vue en ligne du contrat.
- Section « Nature du document » (encart informatif rappelant le caractère symbolique et non contraignant du contrat) affichée avant la première catégorie d'articles.
- Section « Préambule » — présente nommément les deux parties, leur civilité, et les conditions générales du contrat (majorité, consentement, durée, révocation), avant la première catégorie.
- Page de signature enrichie : « LA/LE KEYHOLDER » et « L'ENCAGÉ(E) » avec mention « Lu et approuvé, bon pour accord/engagement », et note précisant que le bon pour accord est matérialisé par signature électronique (validation par code unique).

---

## [3.14.14] — Juillet 2026 — **Export PDF : refonte via Paged.js**

### Corrigé
- Un titre de section (`h2`) pouvait se retrouver seul en bas de page, séparé de ses articles — le titre et son premier article sont désormais regroupés dans un bloc insécable.
- Pages non numérotées et pied de page (signataires + date) absent, après plusieurs tentatives infructueuses basées sur le CSS d'impression natif du navigateur (Chrome/Firefox n'implémentent que partiellement la spec CSS Paged Media en impression classique). Bascule sur **Paged.js**, une bibliothèque JS qui pagine réellement le document dans le navigateur avant impression, avec pied de page et numérotation fiables via `@page { @bottom-left / @bottom-right }`.
- Bouton « Imprimer / Enregistrer en PDF » qui ne s'active qu'une fois la pagination réellement terminée (filet de sécurité à 8s).

---

## [3.14.13] — Juillet 2026

### Corrigé
- Refus d'un contrat par une Keyholder externe : les articles auto-résolus en « Validé » au moment de la soumission (faute d'interface de validation individuelle) ne repassaient pas en « En attente » après un refus — ils restaient à tort marqués comme validés sur un contrat pourtant redevenu brouillon.

---

## [3.14.12] — Juillet 2026

### Corrigé
- Badge « Sous Contrat de Chasteté » mal centré dans le mini-profil affiché à côté des messages (le `text-align:center` hérité du thème n'était pas fiable sur tous les thèmes) — centrage forcé en ligne.

---

## [3.14.11] — Juillet 2026 — **Validation différée pour Keyholder externe**

### Modifié
- Un article ajouté à un contrat (bibliothèque, personnalisé, ou copié depuis un contrat archivé repris comme modèle) est désormais **toujours** inséré en attente de validation, y compris avec une Keyholder externe. La résolution automatique (nécessaire car une KH externe n'a pas d'interface pour valider un par un) n'intervient plus qu'au moment précis de la soumission finale du contrat, avec une note explicative visible sous le bouton Soumettre.

### Ajouté
- Page publique de validation externe (lien reçu par email) : option « Je ne suis pas d'accord avec ce contrat » permettant à une Keyholder externe de refuser directement, avec motif optionnel transmis à l'encagé par message privé.

### Corrigé
- Articles coupés entre deux pages dans l'export PDF/imprimable (`page-break-inside: avoid`).
- Bandeau « Aperçu de travail » affiché à tort sur un contrat Actif/Terminé pourtant définitif.

---

## [3.14.10] — Juillet 2026 — **Correctifs critiques ACP et création de contrat**

### Corrigé
- **Bug critique ACP** : `$this->u_action` était systématiquement écrasé par une chaîne vide en tout début de `main()`, cassant tous les liens `<a href>` du module ACP (les formulaires POST fonctionnaient par coïncidence). Concerne potentiellement tous les liens ACP de l'extension, pas seulement ceux du module Contrat.
- **Bug critique création/duplication de contrat** : le bouton « Créer un contrat » et le lien « Repartir de ce contrat » se basaient sur un statut de contrat calculé tous rôles confondus (encagé OU Keyholder) — un membre Keyholder d'un contrat en cours ne pouvait plus créer ou dupliquer son propre contrat en tant qu'encagé. Scindé en deux indicateurs séparés par rôle.
- Bandeau « Aperçu de travail » également affiché à tort sur un contrat définitif (première itération du correctif, complétée en 3.14.11).
- Mention « Sous Contrat de Chasteté » déplacée au-dessus et centrée sur le profil complet (onglet Contact).

### Ajouté
- Bouton Aperçu disponible en ACP sur les contrats de tous statuts, y compris Terminé/Remplacé.
- Explication de la pastille « C » du badge, à côté de celle du « K », sur la page Widget/Token.
- Affichage séparé « Mon contrat en tant qu'encagé » / « Mes contrats en tant que Keyholder ».

---

## [3.14.9] — Juillet 2026

### Corrigé
- Pastille de badge « C » (contrat actif) repositionnée juste à côté de la pastille « K » (Keyholder) au lieu d'être isolée à l'opposé du badge.
- Bouton « Créer un contrat » toujours visible même avec un contrat déjà en cours.
- Mention « Sous Contrat de Chasteté » ajoutée au mini-profil affiché à côté des messages du forum.

### Ajouté
- Boutons « Tout valider » / « Tout refuser » pour traiter les articles proposés par l'autre partie en masse.

---

## [3.14.8] — Juillet 2026 — **KH externe : chaîne de blocages à la soumission**

### Corrigé
- La Keyholder ne pouvait pas valider les articles reçus sur un contrat encore en brouillon (statut exclu par erreur de la logique de validation).
- Keyholder externe : les articles ajoutés restaient bloqués en attente indéfiniment (personne ne pouvant les valider individuellement), empêchant la soumission du contrat et donc l'envoi de l'email de validation. *(Cette auto-résolution à l'insertion a ensuite été affinée en 3.14.11 pour n'intervenir qu'à la soumission finale.)*

---

## [3.10.0 – 3.14.6] — Été 2026 — **Développement du module Contrat de chasteté (CTR)**

Construction du module CTR de bout en bout : création de contrat entre encagé et Keyholder (inscrite ou externe), bibliothèque d'articles par catégories dynamiques (gérables en ACP), proposition et validation mutuelle article par article, mot de sécurité avec suspension immédiate, soumission avec code de validation par email/message privé, export PDF/imprimable, gestion ACP complète (liste, filtre par statut, fin forcée), et badges dédiés sur le widget public.

Points marquants des migrations dédiées :
- **v3.10.0** — Catégories d'articles dynamiques (remplace les 7 catégories codées en dur par une table gérable en ACP).
- **v3.10.1** — Suivi du traitement admin des articles personnalisés proposés par les membres.
- **v3.10.2** — Mot de sécurité visible en clair dans le contrat exporté (connu des deux parties), en plus du hash conservé pour la suspension automatique.
- **v3.10.3** — Traçabilité de l'auteur d'origine d'un article, y compris une fois versé à la bibliothèque globale.
- **v3.10.4** — Création automatique de la catégorie « Base du Contrat » à l'installation.
- **v3.10.5** — Motif de refus lors du rejet d'un contrat en attente de validation.
- **v3.10.6** — Correction du type de colonne `last_rejection_reason` (TEXT ne supporte pas de valeur DEFAULT en MySQL strict).
- **v3.10.7** — Indicateur de suspension automatique suite à la fin de la relation Keyholder, distinct d'une suspension par mot de sécurité.

---

## [3.9.0] — Juin 2026 — **Système de récompenses complet**

### Récompenses à anneaux (style Apple Watch)
- Trois anneaux concentriques (cage `#ff2d55` / messages `#a8e000` / connexions `#00b0ff`) pour les objectifs **journaliers, mensuels et annuels**, configurables dans l'ACP.
- Affichés dans l'espace membre (page Récompenses), sur le profil public et dans les cartes de message (visibles quel que soit le statut, libre ou verrouillé).

### Badges Locktober refondus
- **Réussi** = toute période couvrant l'intégralité d'octobre (du 1er au 31, quelle que soit l'heure de début/fin), même sans inscription explicite.
- **Participé** = inscrit au Locktober sans couvrir tout le mois.
- Deux images distinctes par année (Réussi / Participé), configurables et éditables dans l'ACP.
- Affichage des badges acquis sur la page Locktober du membre.

### Nouveaux badges
- **Journées spéciales** : badge par année où une période couvre une date donnée (ex. 14/01, 14/02). Ajout / modification / suppression dans l'ACP.
- **Badges anniversaire** : encagé le jour de son anniversaire ou de celui de sa keyholder active (date issue du profil phpBB).
- **Paliers « jours consécutifs en cage »** : basés sur la plus longue période unique (record). Seuils configurables.
- **Paliers « jours totaux en cage »** : basés sur le cumul. Seuils configurables.
- Pour les paliers : option **« prochain palier grisé »** (objectif à venir) et option **mode compact** (profil public uniquement : dernier obtenu + prochain ; l'espace membre reste complet).

### Périodes parfaites
- Compteurs jour / mois / année avec anneaux réels, reconstruction rétroactive complète (jour parfait = cage + messages atteints, la connexion étant déduite d'un message posté).

### Badges figés
- Tous les badges acquis sont stockés et **conservés même si la condition change ensuite** (ex. changement de keyholder). Un badge ne disparaît que si plus aucune période ne le justifie. L'affichage combine les badges figés (années passées) et le calcul à la volée (année en cours).

### Outils d'administration
- Bouton « Recalculer maintenant » : reconstruit les périodes parfaites, fige/synchronise les badges acquis, rafraîchit les images modifiées et reconstruit l'historique des connexions à partir des messages postés.
- Bouton « Recalculer les Locktober ».

### API externe
- Inactivité : si le membre n'a pas posté depuis 60 jours, l'API et le badge renvoient « Donnez des nouvelles sur le forum ! » au lieu des données.

---

## [3.7.7] — Juin 2026 — **Fonctionnalité Keyholder complète**

### Nouvelle fonctionnalité majeure : Keyholder ↔ Sub

Système complet de relations entre un Keyholder (KH, qui détient symboliquement la clé) et un soumis (sub, qui porte la cage).

#### Côté utilisateur (UCP)

- **Page « Mon Keyholder »** : un sub peut désigner un membre comme son KH via liste déroulante (zéro faute de frappe). Demande envoyée par MP automatique. Statut en attente → actif après acceptation. Bouton rompre la relation. Historique complet conservé.
- **Page « Mes soumis »** : un KH voit en plusieurs sections les demandes reçues (Accepter/Refuser), ses soumis actifs (statut chasteté + bouton fin), un formulaire pour inviter plusieurs subs en sélection multiple, ses invitations envoyées en attente (avec Annuler), et l'historique des relations passées.
- **Workflow bidirectionnel** : sub → KH (1 cible) OU KH → subs (1 ou plusieurs cibles simultanées via Ctrl+clic). Dans les deux sens, la cible doit accepter pour activer la relation.
- **MP automatiques** à chaque action (demande, acceptation, refus, fin, invitation).

#### Règles d'affichage des badges

- Sub verrouillé seul → 🔒 standard
- Sub verrouillé sous contrôle KH → 🔒 teinté doré (CSS `chastity-lock-under-kh`)
- Sub libre → rien
- KH actif (au moins un sub verrouillé) → 🔑
- KH actif + lui-même verrouillé par son propre KH → 🔒🔑
- KH sans sub verrouillé → rien
- **Un sub ne porte jamais la clé** (seul un KH désigné la porte)

#### Badges PNG (signature, widget)

- Pastille dorée « K » en haut à droite : KH actif
- Pastille argentée « K » : sub verrouillé sous contrôle d'un KH
- Si double rôle : le doré prime
- API JSON expose `is_keyholder` et `has_active_kh`

#### Affichage profil

Sous Sorties/Activités :
- 🔒 **Soumis(e) à : [pseudo du KH]** (cliquable)
- 🔑 **Keyholder de (N) : [liste des subs]** avec 🔒 pour ceux verrouillés

#### Page ACP « Duos Keyholder »

- Liste filtrable par statut (En attente / Actifs / Rompus / Refusés) avec compteurs
- Bouton « 🚫 Forcer la rupture » pour intervenir en cas d'erreur ou de conflit
- Historique complet préservé

#### Sauvegarde / Restauration

Table `chastity_keyholders` ajoutée à la liste des tables exportées dans le SAV ACP. Restauration automatique via le parseur SQL générique.

### Améliorations diverses

- Page Widget / Token : encadré explicatif ajouté pour la pastille « K » dorée/argentée
- Guide utilisateur BBCode mis à jour avec la nouvelle section Keyholder

### Corrections

- **Migrations phpBB** : règle critique apprise — `effectively_installed()` n'est consulté qu'à la première activation. Une fois la migration tracée dans `phpbb_migrations`, elle ne se rejoue jamais. Toute migration ajoutant un module UCP/ACP doit donc inclure `update_data()` avec `module.add` dès sa sortie initiale. Migration v3.7.1 séparée pour rattraper les installations v3.7.0 sans modules enregistrés.
- **phpBB bbcode_uid** : colonne limitée à 8 caractères (utiliser `substr(md5(uniqid()), 0, 8)` et non `md5(uniqid())` direct qui produit 32 chars → erreur SQL).
- **MySQL strict mode** : INSERT enrichi de toutes les colonnes avec valeurs par défaut (notamment `notes=''` pour la colonne TEXT).
- **Colonne SQL** : `cu.days_current` corrigée en `cc.days_current_period` (la colonne est dans `chastity_cache`, pas `chastity_users`).

### Migrations apportées par cette version

- `update_chastity_tracker_v370` : création de la table `chastity_keyholders`
- `update_chastity_tracker_v371` : enregistrement des modules UCP `my_keyholder`, `my_subs` et ACP `keyholders`

---

## [3.6.12] — Mai 2026

### Améliorations

- Tooltips au survol des dates compatibles mobile/tablette (tap pour ouvrir, retap pour fermer) sur les 3 calendriers (mensuel, vue annuelle, profil 4 mois)
- ACP « Gestion des commentaires » entièrement fonctionnelle (validation/refus en lot, filtres avancés)
- Confidentialité : option « Afficher les détails au survol des dates de mon profil » (Oui/Non)
- Badge/widget : personnalisation (alias, masquer EN CAGE/LIBRE, phrase personnalisée)
- Format « depuis X jours » uniformisé partout

---

## [3.5.x] — Avril 2026

- Badge cadenas 🔒 à côté du pseudo dans les posts (`core.modify_username_string`)
- API JSON publique `/chastity/api?token=xxx`
- Badge PNG dynamique `/chastity/badge.png` (4 styles : dark, medium, light, mini)
- Motifs partagés (validation admin via checkbox)
- Cron anti-inactivité : MP à 30j, suppression à 60j
- Page UCP Confidentialité avec aperçus live des badges
- CSS externalisé dans `chastity.css`

---

## [3.4.x] — Mars 2026

- Sorties de cage (releases → renommé cageexits)
- Activités en cage
- ACP top ranking en temps réel
- Notification MP à l'admin sur nouveau motif personnel

---

## [3.4.2] — Mai 2026 — **Version finale**

### Améliorations d'affichage

#### CSS externalisé
- Tous les styles inline des templates remplacés par des classes CSS dans `chastity.css`
- Compatible avec tous les thèmes phpBB (plus de styles en dur)
- CSS passé de ~60 à ~376 lignes

#### Responsive mobile
- Calendrier mensuel : cellules et légende adaptés sous 600px
- Vue annuelle : grille 4 → 3 → 2 → 1 colonne selon la largeur
- Mini-calendriers profil : espacement réduit sur petit écran

#### Traductions
- Boutons navigation calendrier "◀ Précédent / Suivant ▶" traduits via `lang()`
- Jours de la semaine traduits (Lun/Mar/Mer... et L/M/M/J/V/S/D)
- "Historique des périodes" traduit
- 17 nouvelles clés FR + EN

#### Corrections
- **Fix légende yearview** : "Réalisation + Activité" remplacé par `lang('CHASTITY_LEGEND_MIXED')`
- **Fix `<center>` déprécié** dans les posts → `<div class="chastity-post-badge">`
- **Fix légende profil** : utilise `lang('CHASTITY_LEGEND_MIXED')` au lieu du texte en dur

### Statistiques ACP — Top 50 temps réel
- Le classement des meilleurs utilisateurs est passé de Top 10 à **Top 50**
- **Calcul en temps réel** des jours des périodes actives (corrige le bug `days_count=0` pour les périodes non clôturées)
- Ajout colonne **# (rang)** et colonne **Statut** (🔒 Actif / 🔓 Libre)
- Le total global inclut les jours des périodes actives

### Notification MP — Nouveau motif à valider
- Quand un utilisateur propose un nouveau motif de sortie ou d'activité, un **MP automatique** est envoyé à l'admin sélectionné
- **Combo admin** dans ACP → Paramètres pour choisir le destinataire (ou désactiver)
- Utilise `submit_pm()` natif phpBB

### Correction technique
- **Fix `S_FORM_TOKEN`** manquant dans le premier formulaire ACP Settings (causait "Le formulaire envoyé n'est pas valide")

### Fichiers modifiés (par rapport à v3.4.1)
| Fichier | Modification |
|---|---|
| `styles/all/theme/chastity.css` | CSS externalisé complet + responsive |
| `styles/all/template/ucp_chastity_calendar.html` | Classes CSS + traductions |
| `styles/all/template/ucp_chastity_yearview.html` | Classes CSS + légende corrigée |
| `styles/all/template/event/viewtopic_body_postrow_custom_fields_after.html` | `<center>` → CSS |
| `styles/all/template/event/overall_header_page_body_before.html` | Classes CSS leaderboard |
| `styles/all/template/event/memberlist_view_contact_after.html` | Classes CSS profil + légende |
| `acp/main_module.php` | Stats Top 50 temps réel + config notify admin + liste admins |
| `adm/style/acp_chastity_settings.html` | Combo admin + fix S_FORM_TOKEN |
| `adm/style/acp_chastity_statistics.html` | Rang + statut + Top 50 |
| `ucp/main_module.php` | Notification MP après ajout motif perso |
| `language/fr/common.php` | 26 clés ajoutées (traductions + notifications) |
| `language/en/common.php` | 26 clés ajoutées |
| `composer.json` | Version 3.4.2 |

### Config phpBB ajoutée
| Clé | Défaut | Description |
|---|---|---|
| `chastity_notify_admin_id` | 0 | ID admin destinataire des notifications MP (0 = désactivé) |

---

## [3.4.1] — Mai 2026

### Nouvelles fonctionnalités

#### S1 — Sorties de Cage (CageExits)
- Enregistrement avec date/heure choisie, motif, durée (minutes), notes
- Seuil configurable dans l'ACP (défaut 8h / 480 min) — au-delà, la période est clôturée avec confirmation explicite
- Vérification stricte : la date doit être couverte par une période de verrouillage (passée ou active)
- Rejet automatique des dates futures (avant SQL + message inline)
- Message d'erreur inline avec formulaire pré-rempli (date, motif, durée, notes) — pas de perte de données
- Motifs globaux (admin) et personnels (membres — validation admin requise)
- Notification MP automatique (BBCode rendu) lors de l'approbation d'un motif
- Alerte avant suppression d'un motif utilisé (avec nombre d'enregistrements concernés)
- Bouton Global/Personnel dans l'ACP pour basculer la visibilité d'un motif
- Affichage du seuil ACP à côté du champ durée dans le formulaire UCP
- Historique avec suppression individuelle
- Couleur personnalisable dans ACP Paramètres (défaut `#FFF3CD`)

#### A1 — Activités en Cage
- Enregistrement avec date/heure choisie, type, intensité (légère/moyenne/forte), notes
- Mêmes vérifications de date que les sorties (passé/présent uniquement, période de verrouillage requise)
- Message d'erreur inline avec formulaire pré-rempli
- Motifs globaux et personnels avec validation admin + notification MP
- Alerte avant suppression d'un motif utilisé
- Bouton Global/Personnel dans l'ACP
- Historique avec suppression individuelle
- Couleur personnalisable (défaut `#EDE0F7`)

#### Couleur mixte
- Même jour sortie + activité → couleur brun `#F5E6D3` (personnalisable)

### Corrections
- Fix DELETE sortie : `$realisations_table` → `$cageexits_table`
- Fix date cageexits : lecture de `$cageexit_date_str` déplacée dans le bon bloc
- Fix date activités : normalisation `T→espace` + ajout `:00` pour `strtotime()`
- Fix color picker noir : couleurs non passées au template
- Fix yearview sans couleurs : requêtes SQL cageexit/activity manquantes
- Fix légende profil répétée : légende déplacée hors de la boucle
- Fix BBCode MP : `bbcode_uid` non interpolé

### Tables BDD ajoutées (migration `update_chastity_tracker_v341`)
| Table | Description |
|---|---|
| `{prefix}chastity_cageexit_reasons` | Motifs de sortie de cage |
| `{prefix}chastity_cageexits` | Sorties de cage enregistrées |
| `{prefix}chastity_activity_reasons` | Types d'activité |
| `{prefix}chastity_activities` | Activités enregistrées |

---

## [3.4.0] — Avril 2026

- Vue annuelle UCP (`yearview`) avec calendrier 12 mois
- Navigation par année (précédente/suivante)
- Légende verrouillé/libre/aujourd'hui
- Migration `add_yearview_module`

---

## [3.0.29] — Avril 2026 — Version de référence 3.0.x

Voir les releases GitHub pour l'historique complet des versions antérieures.
