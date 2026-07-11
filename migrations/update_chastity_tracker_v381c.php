<?php
/**
 * Chastity Tracker — Migration v3.8.1b (corrective)
 * Crée, de façon impérative et table par table, les tables ajoutées après
 * coup à v3.8.1 qui n'ont pas été créées là où v3.8.1 était déjà enregistrée.
 * L'approche impérative (sql_create_table dans update_data) évite les aléas
 * du traitement déclaratif groupé de update_schema.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381c extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_perfect_counts')
            && $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_locktober_milestones');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381b'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_missing_tables']]],
        ];
    }

    public function create_missing_tables()
    {
        $tables = [
            'chastity_locktober_rewards' => [
                'COLUMNS' => [
                    'locktober_year' => ['UINT', 0],
                    'reward_label'   => ['VCHAR:255', ''],
                    'reward_image'   => ['VCHAR:255', ''],
                    'updated_time'   => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'locktober_year',
            ],
            'chastity_locktober_milestones' => [
                'COLUMNS' => [
                    'milestone_id'    => ['UINT', null, 'auto_increment'],
                    'threshold'       => ['UINT', 0],
                    'milestone_label' => ['VCHAR:255', ''],
                    'milestone_image' => ['VCHAR:255', ''],
                    'updated_time'    => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'milestone_id',
            ],
            'chastity_special_days' => [
                'COLUMNS' => [
                    'sday_id'      => ['UINT', null, 'auto_increment'],
                    'sday_day'     => ['UINT', 1],
                    'sday_month'   => ['UINT', 1],
                    'sday_label'   => ['VCHAR:255', ''],
                    'sday_image'   => ['VCHAR:255', ''],
                    'updated_time' => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'sday_id',
            ],
            'chastity_perfect_counts' => [
                'COLUMNS' => [
                    'user_id'     => ['UINT', 0],
                    'pscale'      => ['VCHAR:10', ''],
                    'pcount'      => ['UINT', 0],
                    'last_period' => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => ['user_id', 'pscale'],
            ],
        ];

        foreach ($tables as $name => $data)
        {
            $full = $this->table_prefix . $name;
            if (!$this->db_tools->sql_table_exists($full))
            {
                $this->db_tools->sql_create_table($full, $data);
            }
        }
    }

    public function revert_data()
    {
        return [
            ['custom', [[$this, 'drop_added_tables']]],
        ];
    }

    public function drop_added_tables()
    {
        foreach ([
            'chastity_locktober_rewards',
            'chastity_locktober_milestones',
            'chastity_special_days',
            'chastity_perfect_counts',
        ] as $name)
        {
            $full = $this->table_prefix . $name;
            if ($this->db_tools->sql_table_exists($full))
            {
                $this->db_tools->sql_table_drop($full);
            }
        }
    }
}
