<?php
/**
 * Chastity Tracker — Migration v3.8.0
 * Refonte Locktober :
 *  - Ajoute le réglage `chastity_locktober_test_mode` (mode test admin :
 *    Locktober actif pour les admins n'importe quel mois).
 *  - Ajoute le module ACP dédié "Locktober" (mode `locktober`).
 *  - Replace ensuite le module "Sauvegarde" (backup) en dernier dans le menu.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v380 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE . "
                WHERE module_class = 'acp' AND module_mode = 'locktober'";
        $r = $this->db->sql_query($sql);
        $exists = (bool) $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        return $exists;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3710'];
    }

    public function update_data()
    {
        return [
            // Nouveau réglage : mode test admin (Locktober tout mois pour les admins)
            ['config.add', ['chastity_locktober_test_mode', 0]],

            // Ajouter le module ACP dédié "Locktober"
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['locktober'],
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

    public function revert_data()
    {
        return [
            ['config.remove', ['chastity_locktober_test_mode']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'locktober']],
        ];
    }
}
