<?php
/**
 * Chastity Tracker — Migration v3.7.10
 * Ajoute le module ACP "Supprimer une période" (delperiod).
 * Replace ensuite le module "Sauvegarde" (backup) en dernier dans le menu.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3710 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE . "
                WHERE module_class = 'acp' AND module_mode = 'delperiod'";
        $r = $this->db->sql_query($sql);
        $exists = (bool) $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        return $exists;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v379'];
    }

    public function update_data()
    {
        return [
            // Ajouter le module "Supprimer une période"
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['delperiod'],
            ]]],
            // Replacer "Sauvegarde" en dernier
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['backup'],
            ]]],
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['backup'],
            ]]],
        ];
    }
}
