<?php
/**
 * Chastity Tracker — Migration v3.8.1d
 * Ajoute la colonne reward_show_level à chastity_locktober_rewards :
 *   1 = afficher seulement aux "Réussi" (défaut)
 *   2 = afficher aux "Réussi" ET "Participé"
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381d extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_locktober_rewards', 'reward_show_level');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381c'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_locktober_rewards' => [
                    'reward_show_level' => ['UINT', 1],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_locktober_rewards' => [
                    'reward_show_level',
                ],
            ],
        ];
    }
}
