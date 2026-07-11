<?php
/**
 * Chastity Tracker — Migration v3.4.1 modules
 * Ajoute les modules UCP/ACP cageexits et activities
 * Nécessaire pour les mises à jour depuis 3.4.0 — install_chastity_tracker ne rejoue pas ses module.add
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class add_cageexit_modules extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Vérifier si le module UCP cageexits existe déjà en base
        // LIKE '%...%' pour être robuste au format de stockage des backslashes
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE
             . " WHERE module_basename LIKE '%chastitytracker%ucp%main_module%'"
             . " AND module_mode = 'cageexits'";
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row;
    }

    public static function depends_on()
    {
        return [
            '\verturin\chastitytracker\migrations\install_chastity_tracker',
            '\verturin\chastitytracker\migrations\update_chastity_tracker_v341',
        ];
    }

    public function update_data()
    {
        return [
            // UCP — un appel séparé par mode pour éviter les conflits
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['cageexits'],
            ]]],
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['activities'],
            ]]],
            // ACP
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['cageexits'],
            ]]],
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['activities'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'activities']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'cageexits']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'activities']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'cageexits']],
        ];
    }
}
