<?php
/**
 * Chastity Tracker — Migration v3.5.6
 * Ajoute le module ACP "cage_comments" via le helper standard phpBB module.add.
 * Idempotent : effectively_installed vérifie si le module existe déjà.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v356 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Vérifier si le module cage_comments existe déjà sous ACP_CHASTITY_TRACKER
        $sql = 'SELECT m.module_id
                FROM ' . MODULES_TABLE . " m
                INNER JOIN " . MODULES_TABLE . " p ON p.module_id = m.parent_id
                WHERE m.module_class = 'acp'
                  AND m.module_mode = 'cage_comments'
                  AND p.module_langname = 'ACP_CHASTITY_TRACKER'";
        $r = $this->db->sql_query($sql);
        $existing = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);

        return !empty($existing);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v355'];
    }

    public function update_data()
    {
        return [
            // Helper standard phpBB qui vérifie déjà s'il existe
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['cage_comments'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['cage_comments'],
            ]]],
        ];
    }
}
