<?php
/**
 * Chastity Tracker — Migration v3.7.2
 * Réordonne les modules ACP : "Sauvegarde – Restauration" doit toujours être en dernier.
 * Pour cela on supprime puis recrée le module backup, ce qui le replace à la fin.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v372 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Considéré installé si le module backup est positionné APRÈS keyholders
        $sql = 'SELECT module_id, module_mode, left_id FROM ' . MODULES_TABLE . "
                WHERE module_class = 'acp' AND module_mode IN ('backup', 'keyholders')";
        $r = $this->db->sql_query($sql);
        $backup_left = null;
        $kh_left = null;
        while ($row = $this->db->sql_fetchrow($r)) {
            if ($row['module_mode'] === 'backup')     { $backup_left = (int) $row['left_id']; }
            if ($row['module_mode'] === 'keyholders') { $kh_left     = (int) $row['left_id']; }
        }
        $this->db->sql_freeresult($r);

        // Si backup n'existe pas ou est déjà après keyholders → considéré OK
        if ($backup_left === null || $kh_left === null) { return true; }
        return $backup_left > $kh_left;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v371'];
    }

    public function update_data()
    {
        return [
            // Supprimer puis recréer le module backup pour le replacer en dernier
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
