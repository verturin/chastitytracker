<?php
/**
 * Chastity Tracker — Migration v3.5.5
 * Réorganisation des modules ACP (Sauvegarde en dernier).
 * Idempotent : vérifie l'état avant de modifier.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v355 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Vérifier si "backup" est déjà le dernier
        $parent_id = $this->find_parent_module();
        if (!$parent_id) { return true; }

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
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v354'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'reorder_acp_backup_module']]],
        ];
    }

    public function reorder_acp_backup_module()
    {
        $parent_id = $this->find_parent_module();
        if (!$parent_id) { return; }

        $sql = 'SELECT module_id, left_id FROM ' . MODULES_TABLE . "
                WHERE parent_id = $parent_id AND module_mode = 'backup' AND module_class = 'acp'";
        $r = $this->db->sql_query($sql);
        $backup = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        if (!$backup) { return; }

        $backup_id = (int) $backup['module_id'];

        try {
            global $phpbb_container;
            $module_manager = $phpbb_container->get('acp.modules');
            $module_manager->module_class = 'acp';

            $sql = 'SELECT COUNT(*) AS cnt FROM ' . MODULES_TABLE . "
                    WHERE parent_id = $parent_id
                      AND module_class = 'acp'
                      AND left_id > " . (int) $backup['left_id'];
            $r = $this->db->sql_query($sql);
            $cnt = (int) $this->db->sql_fetchfield('cnt');
            $this->db->sql_freeresult($r);

            for ($i = 0; $i < $cnt; $i++) {
                $module_manager->move_module_by($backup_id, 'move_down');
            }
        } catch (\Throwable $e) {
            // Silencieux
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
