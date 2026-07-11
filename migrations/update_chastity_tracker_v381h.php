<?php
/**
 * Chastity Tracker — Migration v3.8.1h
 * Deux tables de paliers configurables :
 *  - chastity_streak_milestones : badges "jours consécutifs en cage"
 *    (basés sur la plus longue période unique jamais atteinte)
 *  - chastity_total_milestones  : badges "jours totaux en cage" (cumul)
 * Tous les paliers atteints sont affichés (pas seulement le plus haut).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381h extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_streak_milestones')
            && $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_total_milestones');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381g'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_milestone_tables']]],
        ];
    }

    public function create_milestone_tables()
    {
        $tables = [
            $this->table_prefix . 'chastity_streak_milestones',
            $this->table_prefix . 'chastity_total_milestones',
        ];
        foreach ($tables as $table) {
            if ($this->db_tools->sql_table_exists($table)) {
                continue;
            }
            $this->db_tools->sql_create_table($table, [
                'COLUMNS' => [
                    'milestone_id'    => ['UINT', null, 'auto_increment'],
                    'threshold'       => ['UINT', 0],
                    'milestone_label' => ['VCHAR:255', ''],
                    'milestone_image' => ['VCHAR:255', ''],
                    'updated_time'    => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'milestone_id',
            ]);
        }
    }

    public function revert_data()
    {
        return [
            ['custom', [[$this, 'drop_milestone_tables']]],
        ];
    }

    public function drop_milestone_tables()
    {
        foreach (['chastity_streak_milestones', 'chastity_total_milestones'] as $t) {
            $table = $this->table_prefix . $t;
            if ($this->db_tools->sql_table_exists($table)) {
                $this->db_tools->sql_table_drop($table);
            }
        }
    }
}
