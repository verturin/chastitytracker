<?php
/**
 * Chastity Tracker — Migration v3.4.1
 * Feature S1 (Réalisations) + A1 (Activités sans sortie)
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v341 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists(
            $this->table_prefix . 'chastity_cageexits'
        );
    }

    public static function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\install_chastity_tracker'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [

                $this->table_prefix . 'chastity_cageexit_reasons' => [
                    'COLUMNS' => [
                        'reason_id'    => ['UINT:11', null, 'auto_increment'],
                        'label'        => ['VCHAR:100', ''],
                        'is_global'    => ['TINT:1', 1],
                        'user_id'      => ['UINT:11', 0],
                        'is_approved'  => ['TINT:1', 1],
                        'created_time' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'reason_id',
                    'KEYS' => [
                        'crr_global'   => ['INDEX', 'is_global'],
                        'crr_approved' => ['INDEX', 'is_approved'],
                    ],
                ],

                $this->table_prefix . 'chastity_cageexits' => [
                    'COLUMNS' => [
                        'cageexit_id' => ['UINT:11', null, 'auto_increment'],
                        'user_id'        => ['UINT:11', 0],
                        'period_id'      => ['UINT:11', 0],
                        'cageexit_date' => ['UINT:11', 0],
                        'duration_min'   => ['UINT:11', 0],
                        'reason_id'      => ['UINT:11', 0],
                        'notes'          => ['TEXT', ''],
                        'auto_closed'    => ['TINT:1', 0],
                        'created_time'   => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'cageexit_id',
                    'KEYS' => [
                        'cr_user_id' => ['INDEX', 'user_id'],
                        'cr_period'  => ['INDEX', 'period_id'],
                        'cr_date'    => ['INDEX', 'cageexit_date'],
                    ],
                ],

                $this->table_prefix . 'chastity_activity_reasons' => [
                    'COLUMNS' => [
                        'reason_id'    => ['UINT:11', null, 'auto_increment'],
                        'label'        => ['VCHAR:100', ''],
                        'is_global'    => ['TINT:1', 1],
                        'user_id'      => ['UINT:11', 0],
                        'is_approved'  => ['TINT:1', 1],
                        'created_time' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'reason_id',
                    'KEYS' => [
                        'car_global'   => ['INDEX', 'is_global'],
                        'car_approved' => ['INDEX', 'is_approved'],
                    ],
                ],

                $this->table_prefix . 'chastity_activities' => [
                    'COLUMNS' => [
                        'activity_id'   => ['UINT:11', null, 'auto_increment'],
                        'user_id'       => ['UINT:11', 0],
                        'period_id'     => ['UINT:11', 0],
                        'activity_date' => ['UINT:11', 0],
                        'reason_id'     => ['UINT:11', 0],
                        'intensity'     => ['VCHAR:10', 'medium'],
                        'notes'         => ['TEXT', ''],
                        'created_time'  => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'activity_id',
                    'KEYS' => [
                        'ca_user_id' => ['INDEX', 'user_id'],
                        'ca_period'  => ['INDEX', 'period_id'],
                        'ca_date'    => ['INDEX', 'activity_date'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'chastity_cageexits',
                $this->table_prefix . 'chastity_cageexit_reasons',
                $this->table_prefix . 'chastity_activities',
                $this->table_prefix . 'chastity_activity_reasons',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_cageexit_threshold', 480]],
            ['config.add', ['chastity_cageexits_enabled', 1]],
            ['config.add', ['chastity_activities_enabled', 1]],
            ['custom', [[$this, 'insert_default_cageexit_reasons']]],
            ['custom', [[$this, 'insert_default_activity_reasons']]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['chastity_cageexit_threshold']],
            ['config.remove', ['chastity_cageexits_enabled']],
            ['config.remove', ['chastity_activities_enabled']],
        ];
    }

    public function insert_default_cageexit_reasons()
    {
        $t   = $this->table_prefix . 'chastity_cageexit_reasons';
        $now = time();
        foreach (['Hygiène', 'Médical', 'Contrôle par le/la keyholder', 'Activité sportive', 'Autre'] as $l)
        {
            $this->db->sql_query("INSERT INTO $t (label,is_global,user_id,is_approved,created_time)"
                . " VALUES ('" . $this->db->sql_escape($l) . "',1,0,1,$now)");
        }
    }

    public function insert_default_activity_reasons()
    {
        $t   = $this->table_prefix . 'chastity_activity_reasons';
        $now = time();
        foreach (['Milking', 'Orgasme ruiné', 'Stimulation', 'Punition', 'Autre'] as $l)
        {
            $this->db->sql_query("INSERT INTO $t (label,is_global,user_id,is_approved,created_time)"
                . " VALUES ('" . $this->db->sql_escape($l) . "',1,0,1,$now)");
        }
    }
}
