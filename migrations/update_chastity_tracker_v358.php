<?php
/**
 * Chastity Tracker — Migration v3.5.8
 * Force le module ACP "Sauvegarde – Restauration" en bas via move_module_by.
 * Idempotent : vérifie l'état avant de modifier.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v358 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Vérifier si "backup" est déjà le dernier sous ACP_CHASTITY_TRACKER
        $parent_id = $this->find_parent_module();
        if (!$parent_id) {
            // Pas de parent → l'extension n'est pas encore installée → on considère OK
            return true;
        }

        $sql = 'SELECT module_mode FROM ' . MODULES_TABLE . "
                WHERE parent_id = $parent_id AND module_class = 'acp'
                ORDER BY left_id DESC";
        $r = $this->db->sql_query_limit($sql, 1);
        $last = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);

        return $last && $last['module_mode'] === 'backup';
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v357'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'force_backup_module_last']]],
        ];
    }

    public function force_backup_module_last()
    {
        $parent_id = $this->find_parent_module();
        if (!$parent_id) { return; }

        try {
            global $phpbb_container;
            $module_manager = $phpbb_container->get('acp.modules');
            $module_manager->module_class = 'acp';

            // Trouver le module backup
            $sql = 'SELECT module_id FROM ' . MODULES_TABLE . "
                    WHERE parent_id = $parent_id
                      AND module_class = 'acp'
                      AND module_mode = 'backup'";
            $r = $this->db->sql_query($sql);
            $backup = $this->db->sql_fetchrow($r);
            $this->db->sql_freeresult($r);
            if (!$backup) { return; }

            $backup_id = (int) $backup['module_id'];

            // Combien d'éléments sont APRÈS le module backup ?
            $sql = 'SELECT left_id FROM ' . MODULES_TABLE . " WHERE module_id = $backup_id";
            $r = $this->db->sql_query($sql);
            $backup_left = (int) $this->db->sql_fetchfield('left_id');
            $this->db->sql_freeresult($r);

            $sql = 'SELECT COUNT(*) AS cnt FROM ' . MODULES_TABLE . "
                    WHERE parent_id = $parent_id
                      AND module_class = 'acp'
                      AND left_id > $backup_left";
            $r = $this->db->sql_query($sql);
            $cnt = (int) $this->db->sql_fetchfield('cnt');
            $this->db->sql_freeresult($r);

            // Le déplacer vers le bas
            for ($i = 0; $i < $cnt; $i++) {
                $module_manager->move_module_by($backup_id, 'move_down');
            }
        } catch (\Throwable $e) {
            // Silencieux — toute exception/erreur PHP
        }
    }

    private function find_parent_module()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE . "
                WHERE module_langname = 'ACP_CHASTITY_TRACKER'
                  AND module_class = 'acp'
                  AND module_basename = ''";
        $r = $this->db->sql_query($sql);
        $parent = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        return $parent ? (int) $parent['module_id'] : 0;
    }
}
