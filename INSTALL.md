# Guide d'installation — Chastity Tracker v3.0.29

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
        └── chastitytracker/      ← nom exact, sans underscore
            ├── acp/
            ├── adm/
            ├── config/
            ├── controller/
            ├── cron/
            │   └── task/
            ├── event/
            ├── language/
            │   ├── en/
            │   └── fr/
            ├── migrations/
            ├── service/
            ├── styles/
            │   └── all/
            │       ├── template/
            │       │   └── event/
            │       └── theme/
            │           └── images/
            ├── ucp/
            ├── composer.json
            └── ext.php
```

> **Important** : Le dossier parent doit s'appeler `verturin` et le dossier de l'extension `chastitytracker` (pas `chastity_tracker`).

---

## 2. Activer l'extension

1. Connectez-vous au **Panneau d'administration (ACP)**
2. Allez dans **Personnalisation** → **Gestion des extensions**
3. Trouvez **Chastity Tracker** dans la liste
4. Cliquez sur **Activer**

L'extension va automatiquement :
- Créer les 5 tables de base de données
- Ajouter les 20 configurations
- Enregistrer les 5 permissions ACL
- Créer les modules UCP (6 onglets) et ACP (3 onglets)
- Initialiser les données pour les utilisateurs existants

---

## 3. Configurer les permissions

1. Allez dans **ACP** → **Permissions** → **Permissions des groupes**
2. Sélectionnez le groupe à configurer (ex. Membres enregistrés)
3. Allez dans l'onglet **Divers** (catégorie **Chastity Tracker**)
4. Accordez les permissions souhaitées :

| Permission | Membres | Modérateurs | Administrateurs |
|---|---|---|---|
| `Peut voir le suivi de chasteté` | ✅ | ✅ | ✅ |
| `Peut gérer ses propres périodes` | ✅ | ✅ | ✅ |
| `Peut gérer ses préférences` | ✅ | ✅ | ✅ |
| `Peut forcer l'actualisation` | ✅ | ✅ | ✅ |
| `Peut modérer les périodes` | ❌ | ✅ | ✅ |

> **Note** : Les 4 premières permissions sont accordées automatiquement au rôle `ROLE_USER_STANDARD` à l'installation. Si vos membres utilisent ce rôle, aucune configuration manuelle n'est nécessaire.

---

## 4. Configurer les paramètres

1. Allez dans **ACP** → **Extensions** → **Chastity Tracker** → **Paramètres**
2. Configurez selon vos besoins :

**Général**
- Activer le suivi de chasteté : `Oui`
- Afficher le statut sur les profils et posts : selon votre préférence
- Jours minimum par période : `0` (aucune limite) ou une valeur positive

**Règles disponibles** — activez uniquement les règles que vous souhaitez proposer aux utilisateurs.

**Locktober**
- Activer le Locktober : `Oui` si vous organisez le défi
- Année active : année en cours
- Afficher les badges : selon votre préférence
- Activer le classement : selon votre préférence

---

## 5. Configurer le cron (optionnel mais recommandé)

Le cron met à jour automatiquement le cache de performance et l'historique annuel.

1. Allez dans **ACP** → **Extensions** → **Chastity Tracker** → **Reconstruire les compteurs**
2. Dans la section **Intervalles de mise à jour automatique** :
   - Définissez l'intervalle du cache (défaut : 60 minutes)
   - Définissez l'intervalle de l'historique (défaut : 1440 minutes = 24h)
   - Activez chaque cron en cliquant sur **Activer le cron**
3. Vérifiez que le cron phpBB est actif : **ACP** → **Paramètres du serveur** → **Tâches périodiques** → activé

> Si le cron phpBB n'est pas disponible sur votre hébergement, utilisez les boutons de recalcul manuel dans cette même page ACP.

---

## 6. Vérification

Après installation et configuration :

1. Connectez-vous en tant qu'utilisateur membre
2. Allez dans le **Panneau de l'utilisateur (UCP)**
3. Vérifiez la présence du menu **Suivi de Chasteté** avec ses onglets
4. Vérifiez le lien **Mon suivi** dans la navigation principale du forum

---

## Mise à jour depuis une version précédente

### Depuis 3.0.x

1. **Désactivez** l'extension dans ACP → Personnalisation → Gestion des extensions
2. **Remplacez** tous les fichiers de l'extension
3. **Réactivez** l'extension — les migrations s'exécutent automatiquement

### Depuis 1.x / 2.x

1. Désactivez l'extension
2. Cliquez sur **Supprimer les données** (attention : supprime toutes les données)
3. Supprimez les anciens fichiers
4. Copiez les nouveaux fichiers
5. Activez l'extension — installation complète depuis zéro

---

## Désinstallation

### Désactivation simple (données conservées)

1. ACP → Personnalisation → Gestion des extensions
2. Cliquez sur **Désactiver** à côté de Chastity Tracker

### Suppression complète

> ⚠️ Toutes les données seront définitivement supprimées.

1. Désactivez l'extension
2. Cliquez sur **Supprimer les données**
3. Confirmez
4. Supprimez le dossier `phpbb/ext/verturin/chastitytracker/`

---

## Support

Consultez le fichier [README.md](README.md) pour la description complète des fonctionnalités.

Pour les problèmes techniques, référez-vous à la [documentation technique](doc_technique_3029.docx) (analyse complète des fichiers et de l'architecture).
