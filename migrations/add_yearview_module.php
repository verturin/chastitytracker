<?php
/**
 * Chastity Tracker — Migration C2 : ajout du module UCP Vue annuelle
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class add_yearview_module extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE . "
                WHERE module_class = 'ucp'
                AND module_langname = 'UCP_CHASTITY_YEARVIEW'";
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row;
    }

    public static function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\install_chastity_tracker'];
    }

    public function update_data()
    {
        return [
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['yearview'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'yearview']],
        ];
    }
}
