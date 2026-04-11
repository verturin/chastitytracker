<?php
/**
 * Chastity Tracker — Migration d'installation complète
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class install_chastity_tracker extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return
            $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_users') &&
            $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_periods') &&
            $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_cache') &&
            $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_history') &&
            $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_user_prefs');
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
                        'user_id'                 => ['UINT:11', 0],
                        'username'                => ['VCHAR:255', ''],
                        'user_colour'             => ['VCHAR:6', ''],
                        'chastity_status'         => ['VCHAR:20', 'free'],
                        'chastity_current_period' => ['UINT:11', 0],
                        'chastity_total_days'     => ['UINT:11', 0],
                        'created_time'            => ['UINT:11', 0],
                        'updated_time'            => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'user_id',
                    'KEYS' => [
                        'cu_status'     => ['INDEX', 'chastity_status'],
                        'cu_total_days' => ['INDEX', 'chastity_total_days'],
                    ],
                ],

                $this->table_prefix . 'chastity_periods' => [
                    'COLUMNS' => [
                        'period_id'            => ['UINT:11', null, 'auto_increment'],
                        'user_id'              => ['UINT:11', 0],
                        'start_date'           => ['UINT:11', 0],
                        'end_date'             => ['UINT:11', 0],
                        'status'               => ['VCHAR:20', 'active'],
                        'is_permanent'         => ['TINT:1', 0],
                        'is_locktober'         => ['TINT:1', 0],
                        'locktober_year'       => ['UINT:11', 0],
                        'locktober_completed'  => ['TINT:1', 0],
                        'days_count'           => ['UINT:11', 0],
                        'notes'                => ['TEXT', ''],
                        'rule_masturbation'    => ['TINT:1', 0],
                        'rule_ejaculation'     => ['TINT:1', 0],
                        'rule_sleep_removal'   => ['TINT:1', 0],
                        'rule_public_removal'  => ['TINT:1', 0],
                        'rule_medical_removal' => ['TINT:1', 0],
                        'created_time'         => ['UINT:11', 0],
                        'updated_time'         => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'period_id',
                    'KEYS' => [
                        'cp_user_id'   => ['INDEX', 'user_id'],
                        'cp_status'    => ['INDEX', 'status'],
                        'cp_locktober' => ['INDEX', 'is_locktober'],
                    ],
                ],

                $this->table_prefix . 'chastity_cache' => [
                    'COLUMNS' => [
                        'user_id'             => ['UINT:11', 0],
                        'days_current_period' => ['UINT:11', 0],
                        'days_current_year'   => ['UINT:11', 0],
                        'days_since_last_end' => ['UINT:11', 0],
                        'last_update'         => ['UINT:11', 0],
                        'next_update'         => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'user_id',
                    'KEYS' => ['cc_next_update' => ['INDEX', 'next_update']],
                ],

                $this->table_prefix . 'chastity_history' => [
                    'COLUMNS' => [
                        'id'          => ['UINT:11', null, 'auto_increment'],
                        'user_id'     => ['UINT:11', 0],
                        'year'        => ['UINT:11', 0],
                        'total_days'  => ['UINT:11', 0],
                        'last_update' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'id',
                    'KEYS' => [
                        'ch_user_year' => ['UNIQUE', ['user_id', 'year']],
                        'ch_year'      => ['INDEX', 'year'],
                    ],
                ],

                $this->table_prefix . 'chastity_user_prefs' => [
                    'COLUMNS' => [
                        'user_id'         => ['UINT:11', 0],
                        'show_status'     => ['TINT:1', 1],
                        'show_days'       => ['TINT:1', 1],
                        'show_total_days' => ['TINT:1', 1],
                        'show_year_stats' => ['TINT:1', 1],
                        'show_best_year'  => ['TINT:1', 1],
                        'show_best_month' => ['TINT:1', 1],
                        'show_in_posts'   => ['TINT:1', 1],
                        'show_in_contact' => ['TINT:1', 1],
                        'api_enabled'     => ['TINT:1', 0],
                        'api_token'       => ['VCHAR:64', ''],
                        'updated_time'    => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'user_id',
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
                $this->table_prefix . 'chastity_cache',
                $this->table_prefix . 'chastity_history',
                $this->table_prefix . 'chastity_user_prefs',
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Configs générales
            ['config.add', ['chastity_enable',                       1]],
            ['config.add', ['chastity_profile_display',              1]],
            ['config.add', ['chastity_rule_masturbation_enabled',    1]],
            ['config.add', ['chastity_rule_ejaculation_enabled',     1]],
            ['config.add', ['chastity_rule_sleep_removal_enabled',   1]],
            ['config.add', ['chastity_rule_public_removal_enabled',  1]],
            ['config.add', ['chastity_rule_medical_removal_enabled', 1]],
            ['config.add', ['chastity_locktober_enabled',            1]],
            ['config.add', ['chastity_locktober_leaderboard_enabled',1]],
            ['config.add', ['chastity_min_period_days',              0]],
            ['config.add', ['chastity_locktober_year',    (int) date('Y')]],
            ['config.add', ['chastity_locktober_badge_enabled',      1]],
            ['config.add', ['chastity_prefs_default',                1]],

            // Configs cron
            ['config.add', ['chastity_cache_cron_enabled',    0,     false]],
            ['config.add', ['chastity_history_cron_enabled',  0,     false]],
            ['config.add', ['chastity_cache_gc',        60,   false]],
            ['config.add', ['chastity_history_gc',      1440, false]],
            ['config.add', ['chastity_cache_last_gc',   time(), true]],
            ['config.add', ['chastity_history_last_gc', time(), true]],

            // Permissions
            ['permission.add', ['u_chastity_view',     true]],
            ['permission.add', ['u_chastity_manage',   true]],
            ['permission.add', ['u_chastity_prefs',    true]],
            ['permission.add', ['m_chastity_moderate', false]],
            ['permission.add', ['u_chastity_refresh',   true]],			
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_view']],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_manage']],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_prefs']],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_refresh']],			

            // Module UCP — 0 crée un nouvel onglet racine
            ['module.add', ['ucp', 0, 'UCP_CHASTITY_TRACKER']],
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes' => ['calendar', 'add_past', 'statistics', 'locktober', 'chastprivacy', 'refresh'],
            ]]],

            // Module ACP
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_CHASTITY_TRACKER']],
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes' => ['settings', 'statistics', 'rebuild'],
            ]]],

            // Initialisation des tables
            ['custom', [[$this, 'init_users']]],
            ['custom', [[$this, 'init_cache']]],
            ['custom', [[$this, 'init_history']]],
            ['custom', [[$this, 'init_prefs']]],
        ];
    }

    public function revert_data()
    {
        return [
            // UCP — modes d'abord, parent ensuite
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'refresh']],			
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'chastprivacy']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'locktober']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'statistics']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'add_past']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'calendar']],
            ['module.remove', ['ucp', 0, 'UCP_CHASTITY_TRACKER']],

            // ACP — modes d'abord, parent ensuite
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'rebuild']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'statistics']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'settings']],
            ['module.remove', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_CHASTITY_TRACKER']],

            // Permissions
            ['permission.remove', ['u_chastity_view']],
            ['permission.remove', ['u_chastity_manage']],
            ['permission.remove', ['u_chastity_prefs']],
            ['permission.remove', ['m_chastity_moderate']],
            ['permission.remove', ['u_chastity_refresh']],			

            // Configs
            ['config.remove', ['chastity_enable']],
            ['config.remove', ['chastity_profile_display']],
            ['config.remove', ['chastity_rule_masturbation_enabled']],
            ['config.remove', ['chastity_rule_ejaculation_enabled']],
            ['config.remove', ['chastity_rule_sleep_removal_enabled']],
            ['config.remove', ['chastity_rule_public_removal_enabled']],
            ['config.remove', ['chastity_rule_medical_removal_enabled']],
            ['config.remove', ['chastity_locktober_enabled']],
            ['config.remove', ['chastity_locktober_leaderboard_enabled']],
            ['config.remove', ['chastity_min_period_days']],
            ['config.remove', ['chastity_locktober_year']],
            ['config.remove', ['chastity_locktober_badge_enabled']],
            ['config.remove', ['chastity_prefs_default']],
            ['config.remove', ['chastity_cache_cron_enabled']],
            ['config.remove', ['chastity_history_cron_enabled']],
            ['config.remove', ['chastity_cache_gc']],
            ['config.remove', ['chastity_history_gc']],
            ['config.remove', ['chastity_cache_last_gc']],
            ['config.remove', ['chastity_history_last_gc']],
        ];
    }

     public function init_users()
     {
         $users_table = $this->table_prefix . 'chastity_users';
         $now         = time();
         // INSERT en masse — une seule requête pour tous les utilisateurs
         $this->db->sql_query(
             'INSERT IGNORE INTO ' . $users_table . '
             (user_id, username, user_colour, chastity_status,
              chastity_current_period, chastity_total_days, created_time, updated_time)
             SELECT user_id, username, user_colour, \'free\', 0, 0, user_regdate, ' . $now . '
             FROM ' . USERS_TABLE . '
             WHERE user_type IN (0, 3)'
         );
     }

     public function init_cache()
     {
         $cache_table = $this->table_prefix . 'chastity_cache';
         $now         = time();
         // INSERT en masse — une seule requête pour tous les utilisateurs
         $this->db->sql_query(
             'INSERT IGNORE INTO ' . $cache_table . '
             (user_id, days_current_period, days_current_year,
              days_since_last_end, last_update, next_update)
             SELECT user_id, 0, 0, 0, ' . $now . ', 0
             FROM ' . USERS_TABLE . '
             WHERE user_type IN (0, 3)'
         );
     }

     public function init_history()
     {
         $history_table = $this->table_prefix . 'chastity_history';
         $now           = time();
         $year          = (int) date('Y');
         // INSERT en masse — une seule requête pour tous les utilisateurs
         $this->db->sql_query(
             'INSERT IGNORE INTO ' . $history_table . '
             (user_id, year, total_days, last_update)
             SELECT user_id, ' . $year . ', 0, ' . $now . '
             FROM ' . USERS_TABLE . '
             WHERE user_type IN (0, 3)'
         );
     }

    public function init_prefs()
    {
        $sql = 'INSERT IGNORE INTO ' . $this->table_prefix . 'chastity_user_prefs (user_id)
                SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_type IN (0, 3)';
        $this->db->sql_query($sql);
    }
}
