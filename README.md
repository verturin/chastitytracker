# Extension Chastity Tracker pour phpBB

## Description

Cette extension permet aux membres d'un forum phpBB d'enregistrer et suivre leurs périodes de chasteté avec un calendrier complet et des statistiques détaillées.

### Fonctionnalités principales

- **Calendrier des périodes** : Enregistrez vos périodes de chasteté avec dates de début et de fin
- **Suivi en temps réel** : Compteur de jours automatique pour la période active
- **Statistiques complètes** :
  - Total de jours en chasteté
  - Nombre de périodes enregistrées
  - Statistiques par année et par mois
  - Période la plus longue
  - Moyenne des périodes
- **Affichage sur le profil** : Statut visible sur le profil utilisateur et dans les posts
- **Contrôle d'accès** : Permissions par groupe d'utilisateurs
- **Interface d'administration** : Statistiques globales et paramètres

## Installation

### 1. Téléchargement

Téléchargez ou clonez cette extension dans le répertoire de votre forum phpBB.

**IMPORTANT** : Le nom du dossier doit être exactement `chastitytracker` (pas `chastity_tracker`).

### 2. Installation des fichiers

Copiez le dossier `chastity_tracker` dans le répertoire :
```
phpbb/ext/verturin/chastitytracker/
```

La structure finale devrait être :
```
phpbb/
├── ext/
│   └── verturin/
│       └── chastitytracker/
│           ├── acp/
│           ├── config/
│           ├── controller/
│           ├── event/
│           ├── language/
│           ├── migrations/
│           ├── styles/
│           ├── ucp/
│           ├── composer.json
│           └── ext.php
```

### 3. Activation de l'extension

1. Connectez-vous au panneau d'administration (ACP)
2. Allez dans l'onglet **Personnalisation**
3. Cliquez sur **Gestion des extensions**
4. Trouvez **Chastity Tracker** dans la liste
5. Cliquez sur **Activer**

L'extension créera automatiquement les tables de base de données nécessaires et installera les modules.

### 4. Configuration des permissions

Après activation, configurez les permissions :

1. Allez dans **Permissions** > **Permissions des groupes**
2. Sélectionnez les groupes qui auront accès à l'extension
3. Accordez les permissions suivantes selon vos besoins :
   - `Can view chastity tracker` : Voir le suivi de chasteté
   - `Can manage own chastity periods` : Gérer ses propres périodes
   - `Can moderate chastity periods` : Modérer les périodes (modérateurs)

### 5. Configuration des paramètres

1. Allez dans **Extensions** > **Chastity Tracker** > **Paramètres**
2. Configurez les options :
   - Activer/désactiver l'extension
   - Afficher le statut sur les profils
   - Définir un nombre minimum de jours par période

## Utilisation

### Pour les membres

#### Accès au suivi de chasteté

1. Cliquez sur votre nom d'utilisateur en haut à droite
2. Sélectionnez **Panneau de l'utilisateur**
3. Dans le menu de gauche, cliquez sur **Chastity Tracker**

#### Démarrer une période

1. Dans l'onglet **Calendrier**
2. Remplissez le formulaire :
   - Sélectionnez la date de début
   - Ajoutez des notes (optionnel)
3. Cliquez sur **Soumettre**

#### Terminer une période

1. Dans l'onglet **Calendrier**
2. Cliquez sur le bouton **Terminer la période** à côté de votre période active
3. Confirmez l'action

Le nombre de jours sera calculé automatiquement et ajouté à vos statistiques.

#### Voir les statistiques

1. Cliquez sur l'onglet **Statistiques**
2. Vous verrez :
   - Votre statut actuel (verrouillé/libre)
   - Le nombre de jours de votre période actuelle (si active)
   - Le total de jours en chasteté
   - Les statistiques par année
   - Les statistiques par mois

### Pour les administrateurs

#### Voir les statistiques globales

1. Connectez-vous au panneau d'administration
2. Allez dans **Extensions** > **Chastity Tracker** > **Statistiques**
3. Vous verrez :
   - Le nombre total de périodes
   - Le nombre total de jours
   - Le nombre d'utilisateurs participants
   - Les périodes actives
   - Le top 10 des utilisateurs

## Affichage sur les profils

Si activé dans les paramètres, le statut de chasteté s'affichera :

- **Sur le profil utilisateur** :
  - Statut actuel (verrouillé 🔒 ou libre 🔓)
  - Date de début de la période active
  - Nombre de jours actuels
  - Total de jours en chasteté

- **Dans les posts du forum** :
  - Icône et statut dans les informations du poster
  - Nombre de jours actuels (si verrouillé)

## Désactivation / Désinstallation

### Désactivation

1. Allez dans **Personnalisation** > **Gestion des extensions**
2. Trouvez **Chastity Tracker**
3. Cliquez sur **Désactiver**

Les données seront conservées dans la base de données.

### Désinstallation complète

⚠️ **Attention** : Cette action supprimera TOUTES les données de l'extension (périodes, statistiques, etc.)

1. Désactivez d'abord l'extension
2. Cliquez sur **Supprimer les données**
3. Confirmez l'action
4. Supprimez le dossier `phpbb/ext/vendor/chastitytracker/`

## Support et personnalisation

### Langues disponibles

- Anglais (EN)
- Français (FR)

Pour ajouter d'autres langues, copiez le fichier `language/en/common.php` et traduisez-le dans votre langue.

### Personnalisation du style

Le fichier CSS se trouve dans :
```
styles/prosilver/theme/chastity.css
```

Vous pouvez modifier les couleurs, tailles et styles selon vos préférences.

### Compatibilité

- phpBB 3.2.0 ou supérieur
- PHP 7.1 ou supérieur

## Sécurité et confidentialité

- Les données sont stockées de manière sécurisée dans la base de données phpBB
- L'accès est contrôlé par le système de permissions phpBB
- Les utilisateurs ne peuvent voir que leurs propres périodes (sauf modérateurs)
- Les modérateurs peuvent voir toutes les périodes selon les permissions accordées

## Structure de la base de données

L'extension crée une table principale :

**phpbb_chastity_periods** :
- period_id : Identifiant unique
- user_id : Identifiant de l'utilisateur
- start_date : Date de début
- end_date : Date de fin (null si active)
- status : Statut (active/completed)
- days_count : Nombre de jours
- notes : Notes de l'utilisateur
- created_time : Date de création
- updated_time : Date de modification

Et ajoute des colonnes à la table users :
- chastity_status : Statut actuel (free/locked)
- chastity_current_period_id : ID de la période active
- chastity_total_days : Total de jours en chasteté

## Licence

GPL-2.0-only

## Auteur

Développé pour phpBB 3.x
