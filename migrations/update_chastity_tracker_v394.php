<?php
/**
 * Chastity Tracker — Migration v3.9.4
 * CTR — Contrat de chasteté : migration corrective ajoutant le module UCP
 * du mode "contract", oublié dans la migration v393 initiale (qui n'ajoutait
 * que la permission ACL). Nécessaire car v393 a déjà été jouée chez certains
 * utilisateurs et ne sera jamais rejouée automatiquement — d'où cette
 * migration dédiée plutôt qu'une modification de v393.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v394 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE
             . " WHERE module_basename LIKE '%chastitytracker%ucp%main_module%'"
             . " AND module_mode = 'contract'";
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v393'];
    }

    public function update_data()
    {
        return [
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['contract'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'contract']],
        ];
    }
}
