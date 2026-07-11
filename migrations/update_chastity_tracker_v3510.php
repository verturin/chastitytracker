<?php
/**
 * Chastity Tracker — Migration v3.5.10
 * Ajoute la colonne show_calendar_details à chastity_user_prefs.
 * Permet à l'utilisateur de masquer les détails (motif, durée, notes)
 * affichés dans les tooltips au survol des dates sur son profil.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3510 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'show_calendar_details');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v358'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => [
                    'show_calendar_details' => ['BOOL', 1],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => ['show_calendar_details'],
            ],
        ];
    }
}
