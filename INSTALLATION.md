# Installation de l'extension Chastity Tracker

## ⚠️ IMPORTANT - Structure des fichiers

Cette extension doit être installée dans le répertoire :
```
phpbb/ext/verturin/chastitytracker/
```

## 📦 Installation

### Méthode 1 : Upload ZIP

1. Téléchargez le fichier `verturin_chastitytracker.zip`
2. Extrayez l'archive sur votre ordinateur
3. Uploadez le dossier `verturin` dans `phpbb/ext/`
4. La structure finale doit être :
   ```
   phpbb/
   └── ext/
       └── verturin/
           └── chastitytracker/
               ├── acp/
               ├── config/
               ├── controller/
               ├── event/
               ├── language/
               ├── migrations/
               ├── styles/
               ├── ucp/
               ├── composer.json
               └── ext.php
   ```

### Méthode 2 : FTP

1. Extrayez l'archive `verturin_chastitytracker.zip`
2. Connectez-vous en FTP à votre serveur
3. Créez le dossier `ext/verturin/` s'il n'existe pas
4. Uploadez le contenu du dossier `chastitytracker` dans `ext/verturin/chastitytracker/`

### Méthode 3 : Ligne de commande (SSH)

```bash
cd /path/to/phpbb/ext
mkdir -p verturin
cd verturin
# Uploadez l'archive puis :
unzip verturin_chastitytracker.zip
mv verturin/chastitytracker ./
rmdir verturin
```

## ✅ Activation

1. Connectez-vous au **Panneau d'Administration** (ACP)
2. Allez dans **Personnalisation** → **Gestion des extensions**
3. Trouvez **Chastity Tracker** dans la liste
4. Cliquez sur **Activer**

⚡ L'extension créera automatiquement les tables de base de données.

## 🔧 Configuration

1. Allez dans **Extensions** → **Chastity Tracker** → **Paramètres**
2. Configurez les options selon vos besoins :
   - Activation globale
   - Affichage sur profils
   - Règles disponibles (masturbation, éjaculation, etc.)
   - Paramètres Locktober

3. Allez dans **Permissions** → **Permissions des groupes**
4. Accordez les permissions aux groupes souhaités :
   - **Can view chastity tracker** : Voir le suivi
   - **Can manage own chastity periods** : Gérer ses périodes
   - **Can moderate chastity periods** : Modération

## ❌ Erreurs courantes

### Erreur 1: "Incorrect table definition; there can be only one auto column"

**Solution** : Assurez-vous d'avoir extrait la dernière version du fichier ZIP qui contient les corrections de la table SQL.

### Erreur 2: Erreur 500 avec "UCP_CHASTITY_TRACKER"

**Causes possibles** :
1. L'extension n'a pas été complètement activée
2. Le cache n'a pas été vidé
3. Les modules UCP ne sont pas correctement enregistrés

**Solutions** :
1. Désactivez l'extension dans ACP → Gestion des extensions
2. Cliquez sur "Supprimer les données"
3. Videz le cache phpBB (ACP → Maintenance → Cache → Purger le cache)
4. Réactivez l'extension
5. Videz à nouveau le cache

Si le problème persiste :
```bash
# En SSH, supprimez le cache manuellement
cd /path/to/phpbb
rm -rf cache/*
```

### Erreur 3: Les menus UCP ne s'affichent pas

**Solution** : Allez dans ACP → Permissions → Permissions des groupes et assurez-vous que les groupes ont la permission `Can view chastity tracker`.

## 🆘 Support

Si vous rencontrez des problèmes :
1. Vérifiez que la structure des dossiers est correcte
2. Vérifiez les permissions des fichiers (644 pour fichiers, 755 pour dossiers)
3. Consultez les logs d'erreur de phpBB
4. Désactivez l'extension, supprimez les données, puis réinstallez

## 📄 Licence

GPL-2.0-only
