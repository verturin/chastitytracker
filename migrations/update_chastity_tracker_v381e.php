<?php
/**
 * Chastity Tracker — Migration v3.8.1e
 * Ajoute reward_image_part (image pour les "Participé") à
 * chastity_locktober_rewards. reward_image reste l'image des "Réussi".
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381e extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_locktober_rewards', 'reward_image_part');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381d'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_locktober_rewards' => [
                    'reward_image_part' => ['VCHAR:255', ''],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_locktober_rewards' => [
                    'reward_image_part',
                ],
            ],
        ];
    }
}
