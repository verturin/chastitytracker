<?php
/**
 * Chastity Tracker — Migration v3.5.2
 * Ajout d'une phrase personnalisée affichée sur les badges API
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v352 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'badge_tagline');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v351'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => [
                    'badge_tagline' => ['VCHAR:150', ''],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => ['badge_tagline'],
            ],
        ];
    }
}
