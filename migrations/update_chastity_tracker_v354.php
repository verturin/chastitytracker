<?php
/**
 * Chastity Tracker — Migration v3.5.4
 * - Ajout is_validated dans chastity_cage_ratings (modération des commentaires)
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v354 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_cage_ratings', 'is_validated');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v352'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_cage_ratings' => [
                    'is_validated' => ['BOOL', 1],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_cage_ratings' => ['is_validated'],
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Fix : valider toutes les photos des cages déjà validées
            ['custom', [[$this, 'fix_validate_photos_of_validated_cages']]],
            // Module UCP nouvelle page Widget / Token (anciennement Accès API)
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['api_access'],
            ]]],
            // Réordonner les modules ACP pour placer "Sauvegarde – Restauration" en dernier
            ['custom', [[$this, 'reorder_acp_modules']]],
        ];
    }

    public function fix_validate_photos_of_validated_cages()
    {
        $catalog = $this->table_prefix . 'chastity_cage_catalog';
        $photos  = $this->table_prefix . 'chastity_cage_photos';
        $this->db->sql_query("UPDATE $photos p
            INNER JOIN $catalog c ON c.catalog_id = p.catalog_id
            SET p.is_validated = 1
            WHERE c.is_validated = 1");
    }

    /**
     * Replace le module ACP "backup" tout à la fin de la catégorie ACP_CHASTITY_TRACKER.
     * Utilise l'API de gestion des modules de phpBB.
     */
    public function reorder_acp_modules()
    {
        // Trouver l'ID du module parent ACP_CHASTITY_TRACKER
        $sql = 'SELECT m.module_id FROM ' . MODULES_TABLE . " m
                WHERE m.module_langname = 'ACP_CHASTITY_TRACKER'
                  AND m.module_class = 'acp'
                  AND m.module_basename = ''";
        $r = $this->db->sql_query($sql);
        $parent = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        if (!$parent) { return; }

        $parent_id = (int) $parent['module_id'];

        // Trouver le module "backup"
        $sql = 'SELECT module_id, left_id, right_id FROM ' . MODULES_TABLE . " m
                WHERE m.parent_id = $parent_id
                  AND m.module_mode = 'backup'
                  AND m.module_class = 'acp'";
        $r = $this->db->sql_query($sql);
        $backup = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        if (!$backup) { return; }

        // Trouver le module avec le plus grand left_id (dernier enfant)
        $sql = 'SELECT MAX(left_id) AS max_left FROM ' . MODULES_TABLE . "
                WHERE parent_id = $parent_id AND module_mode <> 'backup' AND module_class = 'acp'";
        $r = $this->db->sql_query($sql);
        $maxrow = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        if (!$maxrow) { return; }

        $backup_id = (int) $backup['module_id'];

        // Déplacer le module backup à la fin via l'API phpBB acp_modules
        global $phpbb_container;
        try {
            $module_manager = $phpbb_container->get('acp.modules');
            $module_manager->module_class = 'acp';

            // Calculer combien de fois descendre
            $sql = 'SELECT COUNT(*) AS cnt FROM ' . MODULES_TABLE . "
                    WHERE parent_id = $parent_id
                      AND module_class = 'acp'
                      AND left_id > " . (int) $backup['left_id'];
            $r = $this->db->sql_query($sql);
            $cnt = (int) $this->db->sql_fetchfield('cnt');
            $this->db->sql_freeresult($r);

            // Déplacer vers le bas
            for ($i = 0; $i < $cnt; $i++) {
                $module_manager->move_module_by($backup_id, 'move_down');
            }
        } catch (\Exception $e) {
            // Si l'API n'est pas dispo (selon version phpBB), on échoue silencieusement
        }
    }
}
