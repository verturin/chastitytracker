<?php
/**
 * Chastity Tracker — Migration v3.7.9
 * Ajoute la colonne `gender` (male / female / other) dans chastity_user_prefs.
 * Utilisée pour l'accord orthographique des libellés (Keyholder/Keyholdeuse,
 * encagé/encagée) dans l'API, le badge PNG et les affichages.
 * Défaut : 'male'. 'other' = accord masculin (comme 'male').
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v379 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'gender');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v372'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => [
                    'gender' => ['VCHAR:8', 'male'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => ['gender'],
            ],
        ];
    }
}
