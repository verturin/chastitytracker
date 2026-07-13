# Chastity Tracker — verturin/chastitytracker

> Extension phpBB 3.3+ — Suivi de chasteté complet : calendrier, sorties/activités en cage, catalogue de cages communautaire, relations Keyholder ↔ Encagé, badges publics et statistiques

[![Version](https://img.shields.io/badge/version-3.14.21-blue.svg)](CHANGELOG.md)
[![phpBB](https://img.shields.io/badge/phpBB-%E2%89%A53.2.0-orange.svg)](https://www.phpbb.com)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.1-8892BF.svg)](https://php.net)
[![Licence](https://img.shields.io/badge/licence-GPL--2.0--only-green.svg)](LICENSE)

---

## Description

**Chastity Tracker** est une extension phpBB permettant aux membres d'un forum de suivre leurs périodes de chasteté avec calendrier visuel, statistiques détaillées, sorties et activités en cage, catalogue communautaire de cages, relations encadrées Keyholder ↔ Encagé, badges publics et préférences de confidentialité.

---

## Fonctionnalités

### Suivi personnel (UCP)

- **Calendrier mensuel** avec navigation — jours verrouillés, sorties et activités mis en évidence
- **Vue annuelle** — 12 mois sur une page, couleurs dynamiques, responsive mobile
- **Démarrage/fin de période** avec date et heure personnalisables
- **Ajout de périodes passées** (dates historiques)
- **Mode permanent** — port de cage sans date de fin prévue
- **5 règles configurables** par période
- **Statistiques personnelles** — total, périodes, meilleure année, répartition mensuelle et annuelle avec compteurs sorties/activités
- **Préférences de confidentialité** — contrôle fin sur chaque information visible
- **Token API personnel**
- **Actualisation manuelle** du cache

#### Sorties de Cage
- Enregistrement avec date/heure, motif, durée et notes
- Seuil configurable (défaut 8h) — au-delà, confirmation et clôture automatique de la période
- Vérification stricte : la date doit être couverte par une période de verrouillage
- Message d'erreur inline avec formulaire pré-rempli (pas de perte de données)
- Motifs personnels avec validation admin et **notification MP automatique**
- Couleur `#FFF3CD` (personnalisable) sur tous les calendriers

#### Activités en Cage
- Enregistrement avec date/heure, type, intensité (légère/moyenne/forte) et notes
- Mêmes vérifications de date que les sorties
- Motifs personnels avec validation admin et **notification MP automatique**
- Couleur `#EDE0F7` (personnalisable) sur tous les calendriers

#### Couleur mixte
Même jour sortie + activité → couleur `#F5E6D3` (personnalisable)

#### Catalogue de cages
- **Catalogue communautaire** filtrable par marque/matériau, photos en lightbox
- **Notation** sur 5 étoiles + commentaires (validation admin)
- **Proposition de nouvelles cages** par les membres avec photo
- **Collection personnelle** avec archivage et suivi par cage sur les périodes

#### Mon Keyholder / Mes encagés
- **Désignation Keyholder** via liste déroulante (encagé → KH)
- **Invitation multi-encagés** par une KH (sélection multiple Ctrl+clic)
- **Workflow MP automatique** : demande → accept/refuse → MP de confirmation
- **Affichage badges** : 🔒 encagé, 🔑 KH, 🔒🔑 double rôle. Cadenas teinté doré pour un encagé sous contrôle
- **Pastille K** (dorée/argentée) sur les badges PNG selon le rôle
- **Section profil** : « Encagé(e) par : X » et « Keyholder de : [liste] »
- **Historique** complet conservé (refus, ruptures, dates)

#### Récompenses & badges (v3.9)
- **Anneaux de progression** style Apple Watch (cage / messages / connexions), objectifs jour/mois/année configurables en ACP
- **Locktober** — badges Réussi/Participé, images distinctes par année
- **Journées spéciales** et **badges anniversaire** (membre ou Keyholder active)
- **Paliers jours consécutifs** (record) et **jours totaux** (cumul), seuils configurables, badges figés une fois acquis
- **Félicitations automatiques** par MP lors d'un nouveau record personnel

#### Contrat de chasteté — CTR (v3.10+)
- **Contrat symbolique** entre encagé et Keyholder, composé d'articles par catégories (base, cadre, dispositif, communication, discipline...), catégories gérables en ACP
- **Keyholder inscrite** sur le forum ou **Keyholder externe** (pseudo + email, aucun compte requis) — aperçu et code de validation envoyés par email
- **Proposition et validation mutuelle** article par article, avec validation groupée (« Tout valider / Tout refuser »)
- **Mot de sécurité** — suspension immédiate du contrat par l'une ou l'autre partie
- **Soumission avec code de validation** (MP + email pour une KH inscrite, page publique dédiée pour une KH externe, avec option de refus)
- **Export PDF/imprimable** — pagination fiable via Paged.js, pied de page avec signataires et date
- **Historique et reprise** d'un contrat archivé comme modèle
- **Pastille C** sur les badges PNG, juste à côté de la pastille K
- **Gestion ACP complète** — liste filtrable par statut, aperçu de tout contrat, fin forcée

### Affichage communautaire

- **Leaderboard** sur la page d'accueil (dépliable) — 3 colonnes : année en cours, meilleure année, all-time
- **Badge dans les posts** — cadenas 🔒 (verrouillé) et clé 🔑 (Keyholder actif) à côté du pseudo
- **Mini-calendriers profil** — 4 derniers mois avec légende
- **Tooltips au survol** — détail sortie/activité (compatibles mobile)
- **Icône cadenas** dans la barre de navigation (permission `u_chastity_view`)

### Administration (ACP)

- **Paramètres** — activer/désactiver, règles, Locktober, couleurs personnalisables, **notification MP admin**
- **Statistiques** — Top 50 utilisateurs avec calcul temps réel des périodes actives, rang et statut
- **Sorties de cage** — gestion des motifs globaux/personnels, approbation
- **Activités en cage** — gestion des motifs globaux/personnels, approbation
- **Sauvegarde/restauration** — export JSON complet des données
- **Reconstruction** — recalcul cache, historique, crons configurables

### API publique

- Endpoint JSON `/chastity/api?token=xxx`
- Statut, jours, période active, statistiques

---

## Installation

Voir [INSTALL.md](INSTALL.md) pour le guide complet.

---

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique des versions.

---

## Structure des fichiers

```
chastitytracker/
├── acp/                         # Modules ACP (paramètres, statistiques, motifs)
├── adm/style/                   # Templates ACP (Twig)
├── config/                      # routing.yml, services.yml
├── controller/                  # Contrôleur principal (API)
├── cron/task/                   # Tâches cron (cache, historique)
├── event/                       # Listener événements phpBB
├── language/                    # Traductions FR + EN
├── migrations/                  # Migrations BDD
├── service/                     # Services (cache_updater, history_updater)
├── styles/all/
│   ├── template/                # Templates UCP (Twig)
│   │   └── event/               # Templates événements (profil, posts, leaderboard)
│   └── theme/
│       ├── chastity.css         # CSS externalisé
│       └── images/              # SVG cadenas
├── ucp/                         # Modules UCP
├── composer.json
└── ext.php
```

---

## Licence

GPL-2.0-only
