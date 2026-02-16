# Guide de Dépannage - Chastity Tracker

## ❌ Erreur : "Un module porte déjà ce nom : UCP_CHASTITY_CALENDAR"

Cette erreur signifie que les modules de l'extension existent déjà dans la base de données. Cela peut arriver si :
- Vous avez installé une version précédente de l'extension
- L'installation précédente n'a pas été complètement désinstallée
- Vous avez renommé l'extension de `vendor` à `verturin`

### Solution 1 : Nettoyage via phpMyAdmin (Recommandé)

1. **Connectez-vous à phpMyAdmin**
2. **Sélectionnez votre base de données phpBB**
3. **Cliquez sur l'onglet SQL**
4. **Copiez-collez le contenu du fichier `cleanup_modules.sql`**
5. **IMPORTANT** : Si votre préfixe de table n'est pas `phpbb_`, remplacez-le dans le script
6. **Exécutez le script**
7. **Videz le cache phpBB** :
   - Via ACP : Maintenance → Cache → Purger le cache
   - Ou supprimez manuellement le dossier `phpbb/cache/`
8. **Réactivez l'extension** dans ACP → Gestion des extensions

### Solution 2 : Désinstallation complète puis réinstallation

1. **Désactivez l'extension** dans ACP → Gestion des extensions
2. **Cliquez sur "Supprimer les données"** (⚠️ cela supprimera TOUTES les données de chasteté)
3. **Supprimez le dossier** `phpbb/ext/verturin/chastitytracker/`
4. **Videz le cache** phpBB
5. **Uploadez la nouvelle version**
6. **Activez l'extension**

### Solution 3 : Via ligne de commande MySQL

```bash
mysql -u votre_user -p votre_base_de_donnees < cleanup_modules.sql
```

N'oubliez pas de modifier le préfixe de table dans le fichier SQL si nécessaire.

## ❌ Erreur 500 avec "UCP_CHASTITY_TRACKER"

### Causes possibles :
- Cache phpBB non vidé
- Fichiers de langue manquants
- Permissions incorrectes

### Solutions :

1. **Videz le cache** :
   ```bash
   cd /path/to/phpbb
   rm -rf cache/*
   ```

2. **Vérifiez les permissions** :
   ```bash
   cd /path/to/phpbb/ext/verturin/chastitytracker
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   ```

3. **Vérifiez les fichiers de langue** :
   - Assurez-vous que `language/fr/common.php` et `language/en/common.php` existent
   - Vérifiez qu'ils ne contiennent pas d'erreurs PHP

## ❌ Erreur : "Incorrect table definition; there can be only one auto column"

Cette erreur SQL a été corrigée dans la version actuelle.

### Solution :
1. Assurez-vous d'utiliser la dernière version de l'archive
2. Si l'erreur persiste, supprimez manuellement la table :
   ```sql
   DROP TABLE IF EXISTS phpbb_chastity_periods;
   ```
3. Réactivez l'extension

## ❌ Les menus n'apparaissent pas dans l'UCP

### Vérifications :

1. **Permissions** : ACP → Permissions → Permissions des groupes
   - Vérifiez que le groupe a `Can view chastity tracker`

2. **Modules activés** : ACP → Extensions → Chastity Tracker
   - Vérifiez que l'extension est bien activée

3. **Cache** : Videz le cache phpBB

## 🔄 Procédure de réinstallation propre

Si rien ne fonctionne, suivez cette procédure complète :

### Étape 1 : Nettoyage complet

1. Désactivez l'extension
2. Supprimez les données
3. Exécutez le script `cleanup_modules.sql` dans phpMyAdmin
4. Supprimez le dossier `ext/verturin/chastitytracker/`
5. Videz le cache

### Étape 2 : Installation propre

1. Uploadez la nouvelle version
2. Vérifiez la structure : `ext/verturin/chastitytracker/`
3. Videz le cache
4. Activez l'extension
5. Configurez les permissions

### Étape 3 : Vérification

1. Connectez-vous en tant qu'utilisateur normal
2. Allez dans le panneau utilisateur
3. Vérifiez que "Chastity Tracker" apparaît dans le menu

## 📞 Support supplémentaire

Si le problème persiste :

1. Activez le mode debug phpBB dans `config.php` :
   ```php
   @define('DEBUG', true);
   ```

2. Consultez les logs d'erreur :
   - Logs phpBB : cherchez dans la base de données, table `phpbb_log`
   - Logs serveur : `/var/log/apache2/error.log` ou similaire

3. Notez le message d'erreur exact et la ligne de code concernée

## 🛠️ Commandes utiles

### Vider le cache (SSH)
```bash
cd /path/to/phpbb
rm -rf cache/*
chmod 777 cache/
```

### Vérifier la structure des fichiers
```bash
cd /path/to/phpbb/ext/verturin/chastitytracker
ls -la
```

### Vérifier les migrations appliquées
```sql
SELECT * FROM phpbb_migrations WHERE migration_name LIKE '%chastity%';
```

### Voir les modules installés
```sql
SELECT * FROM phpbb_modules WHERE module_langname LIKE '%CHASTITY%';
```
