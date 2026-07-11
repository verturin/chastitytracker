# Guide d'installation — Chastity Tracker v3.4.2

---

## Prérequis

| Composant | Version minimale |
|---|---|
| phpBB | 3.2.0 (3.3.x recommandé) |
| PHP | 7.1.0 |
| Base de données | MySQL / MariaDB |

---

## 1. Copier les fichiers

Décompressez l'archive et copiez le dossier dans votre installation phpBB :

```
phpbb/
└── ext/
    └── verturin/
        └── chastitytracker/
            ├── acp/
            ├── adm/style/
            ├── config/
            ├── controller/
            ├── cron/task/
            ├── event/
            ├── language/ (en/ + fr/)
            ├── migrations/
            ├── service/
            ├── styles/all/
            │   ├── template/ + event/
            │   └── theme/ + images/
            ├── ucp/
            ├── composer.json
            └── ext.php
```

> **Important** : Le dossier parent doit s'appeler `verturin` et l'extension `chastitytracker`.

---

## 2. Activer l'extension

1. ACP → **Personnalisation** → **Gestion des extensions**
2. Trouver **Chastity Tracker** dans la liste
3. Cliquer sur **Activer**

La migration s'exécute automatiquement (création des tables, permissions, modules).

---

## 3. Configurer les permissions

ACP → **Permissions** → **Permissions des groupes d'utilisateurs** :

| Permission | Description | Membres | Modérateurs | Invités |
|---|---|---|---|---|
| `u_chastity_view` | Voir les données | Oui | Oui | Non |
| `u_chastity_manage` | Gérer ses périodes | Oui | Oui | Non |
| `u_chastity_prefs` | Modifier ses préférences | Oui | Oui | Non |
| `u_chastity_refresh` | Actualiser son cache | Oui | Oui | Non |
| `m_chastity_moderate` | Modération | Non | Oui | Non |

---

## 4. Configurer les paramètres

ACP → **Chastity Tracker** → **Paramètres** :

- Activer l'extension
- Activer les sorties de cage et activités en cage
- Configurer les règles selon les besoins
- Configurer Locktober (année, options)
- **Notification MP** : sélectionner l'admin qui recevra un MP quand un membre propose un nouveau motif

---

## 5. Configurer les crons

ACP → **Chastity Tracker** → **Reconstruire** :

- Intervalle du cache : 60 minutes (recommandé)
- Intervalle de l'historique : 1440 minutes (recommandé)

---

## 6. Premier recalcul

ACP → **Chastity Tracker** → **Reconstruire** :

1. Cliquer **Lancer la reconstruction**
2. Cliquer **Recalculer le cache**
3. Cliquer **Recalculer l'historique**

---

## Mise à jour depuis v3.4.1

1. Mettre le forum en **mode maintenance**
2. Copier les nouveaux fichiers sur le FTP (écraser les anciens)
3. Supprimer `cache/production/container*` via FTP
4. ACP → **Vider le cache**
5. ACP → **Paramètres Chastity** → Sélectionner l'admin pour les notifications MP
6. Désactiver la maintenance

> **Note** : Pas de migration BDD entre v3.4.1 et v3.4.2. Le vidage de cache suffit.

---

## Mise à jour depuis v3.4.0 ou antérieure

1. Mettre le forum en **mode maintenance**
2. Copier les nouveaux fichiers sur le FTP
3. Supprimer `cache/production/container*` via FTP
4. ACP → **Vider le cache**
5. La migration `update_chastity_tracker_v341` s'exécute automatiquement (création des 4 tables sorties/activités)
6. ACP → **Chastity Tracker** → **Reconstruire** → Tout recalculer
7. Désactiver la maintenance

---

## Désinstallation

1. ACP → **Personnalisation** → **Gestion des extensions** → **Désactiver**
2. Puis **Supprimer les données**
3. Supprimer le dossier `ext/verturin/chastitytracker/` via FTP
