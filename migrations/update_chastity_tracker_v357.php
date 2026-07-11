<?php
/**
 * Chastity Tracker — Migration v3.5.7
 * Ajoute les colonnes badge_alias (pseudo affiché) et badge_hide_status
 * (masquer le mot "EN CAGE" / "LIBRE") dans chastity_user_prefs.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v357 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'badge_alias')
            && $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'badge_hide_status');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v356'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => [
                    'badge_alias'       => ['VCHAR:50', ''],
                    'badge_hide_status' => ['BOOL', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => ['badge_alias', 'badge_hide_status'],
            ],
        ];
    }
}
