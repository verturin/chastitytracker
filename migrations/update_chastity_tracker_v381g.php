<?php
/**
 * Chastity Tracker — Migration v3.8.1g
 * Table chastity_earned_badges : historique FIGÉ des badges acquis
 * (Locktober, journées spéciales, anniversaires, paliers). Un badge gagné
 * y est stocké avec son libellé/image figés et reste même si la condition
 * courante change (ex. changement de keyholder). Il n'est retiré au recalcul
 * que si plus AUCUNE période ne justifie son obtention.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381g extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_earned_badges');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381f'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_earned_badges' => [
                    'COLUMNS' => [
                        'eb_id'       => ['UINT', null, 'auto_increment'],
                        'user_id'     => ['UINT', 0],
                        'badge_type'  => ['VCHAR:20', ''],   // locktober/sday/birthday_self/birthday_kh/milestone
                        'badge_year'  => ['UINT', 0],
                        'badge_key'   => ['VCHAR:40', ''],   // sous-clé (jour-mois, niveau, seuil...)
                        'badge_label' => ['VCHAR:255', ''],
                        'badge_image' => ['VCHAR:255', ''],
                        'badge_level' => ['VCHAR:20', ''],   // success/participated pour locktober
                        'extra'       => ['VCHAR:255', ''],  // ex. id/nom KH figé
                        'earned_at'   => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'eb_id',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_table_safe']]],
        ];
    }

    public function create_table_safe()
    {
        $table = $this->table_prefix . 'chastity_earned_badges';
        if ($this->db_tools->sql_table_exists($table)) {
            return;
        }
        $this->db_tools->sql_create_table($table, [
            'COLUMNS' => [
                'eb_id'       => ['UINT', null, 'auto_increment'],
                'user_id'     => ['UINT', 0],
                'badge_type'  => ['VCHAR:20', ''],
                'badge_year'  => ['UINT', 0],
                'badge_key'   => ['VCHAR:40', ''],
                'badge_label' => ['VCHAR:255', ''],
                'badge_image' => ['VCHAR:255', ''],
                'badge_level' => ['VCHAR:20', ''],
                'extra'       => ['VCHAR:255', ''],
                'earned_at'   => ['TIMESTAMP', 0],
            ],
            'PRIMARY_KEY' => 'eb_id',
        ]);
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [$this->table_prefix . 'chastity_earned_badges'],
        ];
    }
}
