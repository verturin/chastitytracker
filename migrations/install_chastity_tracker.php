<?php
namespace verturin\chastitytracker\migrations;

class install_chastity_tracker extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_periods');
    }

    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v320\v320'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_users' => [
                    'COLUMNS' => [
                        'user_id'                  => ['UINT', 0],
                        'username'                 => ['VCHAR:255', ''],
                        'user_colour'              => ['VCHAR:6', ''],
                        'chastity_status'          => ['VCHAR:20', 'free'],
                        'chastity_current_period'  => ['UINT', 0],
                        'chastity_total_days'      => ['UINT', 0],
                        'created_time'             => ['TIMESTAMP', 0],
                        'updated_time'             => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'user_id',
                    'KEYS' => [
                        'cu_status'      => ['INDEX', 'chastity_status'],
                        'cu_total_days'  => ['INDEX', 'chastity_total_days'],
                    ],
                ],
                
                $this->table_prefix . 'chastity_periods' => [
                    'COLUMNS' => [
                        'period_id'              => ['UINT', null, 'auto_increment'],
                        'user_id'                => ['UINT', 0],
                        'start_date'             => ['TIMESTAMP', 0],
                        'end_date'               => ['TIMESTAMP', 0],
                        'status'                 => ['VCHAR:20', 'active'],
                        'is_permanent'           => ['BOOL', 0],
                        'is_locktober'           => ['BOOL', 0],
                        'locktober_year'         => ['UINT', 0],
                        'locktober_completed'    => ['BOOL', 0],
                        'days_count'             => ['UINT', 0],
                        'notes'                  => ['TEXT', ''],
                        'rule_masturbation'      => ['BOOL', 0],
                        'rule_ejaculation'       => ['BOOL', 0],
                        'rule_sleep_removal'     => ['BOOL', 0],
                        'rule_public_removal'    => ['BOOL', 0],
                        'rule_medical_removal'   => ['BOOL', 0],
                        'created_time'           => ['TIMESTAMP', 0],
                        'updated_time'           => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'period_id',
                    'KEYS' => [
                        'cp_user_id'     => ['INDEX', 'user_id'],
                        'cp_status'      => ['INDEX', 'status'],
                        'cp_locktober'   => ['INDEX', 'is_locktober'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'chastity_users',
                $this->table_prefix . 'chastity_periods',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_enable', 1]],
            ['config.add', ['chastity_profile_display', 1]],
            ['config.add', ['chastity_rule_masturbation_enabled', 1]],
            ['config.add', ['chastity_rule_ejaculation_enabled', 1]],
            ['config.add', ['chastity_rule_sleep_removal_enabled', 1]],
            ['config.add', ['chastity_rule_public_removal_enabled', 1]],
            ['config.add', ['chastity_rule_medical_removal_enabled', 1]],
            ['config.add', ['chastity_locktober_enabled', 1]],
            ['config.add', ['chastity_locktober_leaderboard_enabled', 1]],
            
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_CHASTITY_TITLE']],
            ['module.add', ['acp', 'ACP_CHASTITY_TITLE', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes' => ['settings', 'statistics', 'rebuild'],
            ]]],
            
            ['module.add', ['ucp', '', 'UCP_CHASTITY']],
            ['module.add', ['ucp', 'UCP_CHASTITY', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes' => ['calendar', 'statistics', 'locktober', 'add_past'],
            ]]],
            
            ['permission.add', ['u_chastity_view', true]],
            ['permission.add', ['u_chastity_manage', true]],
            ['permission.add', ['m_chastity_moderate', true]],
            
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_view']],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_manage']],
            ['permission.permission_set', ['ROLE_MOD_STANDARD', 'm_chastity_moderate']],
        ];
    }
}
