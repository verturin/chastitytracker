# Chastity Tracker — verturin/chastitytracker
> Extension phpBB 3.2+ — Suivi de chasteté avec calendrier visuel, statistiques et défi Locktober

[![Version](https://img.shields.io/badge/version-3.0.29-blue.svg)](CHANGELOG.md)
[![phpBB](https://img.shields.io/badge/phpBB-%E2%89%A53.2.0-orange.svg)](https://www.phpbb.com)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.1-8892BF.svg)](https://php.net)
[![Licence](https://img.shields.io/badge/licence-GPL--2.0--only-green.svg)](LICENSE)

---

## Description

**Chastity Tracker** Extension phpBB permettant aux membres d'un forum de suivre et enregistrer leurs périodes de chasteté, avec calendrier visuel, statistiques détaillées, défi communautaire Locktober, préférences de confidentialité et token API personnel.

## Fonctionnalités

### Suivi personnel (UCP)
- **Calendrier mensuel** avec navigation mois précédent/suivant — jours de chasteté mis en évidence
- **Démarrage/fin de période** avec date et heure personnalisables
- **Ajout de périodes passées** (dates historiques antérieures)
- **Mode permanent** — port de cage sans date de fin prévue
- **5 règles configurables** par période : masturbation, éjaculation, retrait cage (sommeil, public, médical)
- **Statistiques personnelles** — total, périodes, meilleure année, répartition mensuelle et annuelle
- **Préférences de confidentialité** — contrôle fin sur chaque information visible
- **Token API personnel** — permet à des applications externes d'afficher le statut
- **Actualisation manuelle** du cache de performance et de l'historique

### Affichage communautaire
- **Badge dans les posts** — statut et jours visibles sous le pseudo
- **Statut sur le profil** — date de verrouillage, jours actuels, total, meilleure année
- **Lien de navigation** dans le header avec icône cadenas SVG
- **Défi Locktober** — classement des participants, badge de réussite, historique des éditions passées

### Administration (ACP)
- Activation/désactivation globale
- Configuration individuelle des 5 règles proposées aux utilisateurs
- Paramètres Locktober (année active, badges, classement public)
- Statistiques forum globales — top 10 utilisateurs, périodes actives, totaux
- Reconstruction manuelle des compteurs
- Gestion du cron cache et historique — intervalle, activation/désactivation, recalcul manuel

---

## Installation

Voir [INSTALL.md](INSTALL.md) pour le guide complet.

```
phpbb/ext/verturin/chastitytracker/
```

---

## Structure de la base de données

| Table | Description |
|---|---|
| `{prefix}chastity_users` | Statut courant par utilisateur |
| `{prefix}chastity_periods` | Toutes les périodes de chasteté |
| `{prefix}chastity_cache` | Cache de performance (mis à jour par cron) |
| `{prefix}chastity_history` | Historique annuel agrégé |
| `{prefix}chastity_user_prefs` | Préférences de confidentialité + token API |

---

## Permissions ACL

| Permission | Description | Rôle par défaut |
|---|---|---|
| `u_chastity_view` | Voir le suivi de chasteté | ROLE_USER_STANDARD |
| `u_chastity_manage` | Gérer ses propres périodes | ROLE_USER_STANDARD |
| `u_chastity_prefs` | Gérer ses préférences de confidentialité | ROLE_USER_STANDARD |
| `u_chastity_refresh` | Forcer l'actualisation de ses données | ROLE_USER_STANDARD |
| `m_chastity_moderate` | Modérer les périodes (modérateurs) | Manuel |

---

## Compatibilité

- phpBB 3.2.0 ou supérieur (3.3.x recommandé)
- PHP 7.1 ou supérieur
- MySQL / MariaDB
- Compatible tous les thèmes phpBB (templates dans `styles/all/`)

---

## Langues

- 🇫🇷 Français
- 🇬🇧 Anglais

---

## Licence

[GPL-2.0-only](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## Auteur

[Verturin](https://github.com/verturin)
