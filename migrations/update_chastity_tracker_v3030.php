<?php
/**
 * Chastity Tracker — Migration v3.0.30
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3030 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT auth_option_id FROM ' . $this->table_prefix . 'acl_options
                WHERE auth_option = \'u_chastity_leaderboard\'';
        $result = $this->db->sql_query($sql);
        $exists = (bool) $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return $exists;
    }

    public static function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\install_chastity_tracker'];
    }

    public function update_data()
    {
        return [
            ['permission.add', ['u_chastity_leaderboard', true]],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_leaderboard']],
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes' => ['backup'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'backup']],
            ['permission.remove', ['u_chastity_leaderboard']],
        ];
    }
}
