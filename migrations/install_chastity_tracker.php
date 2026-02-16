<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\migrations;

class install_chastity_tracker extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_periods');
    }

    static public function depends_on()
    {
        return array(
            '\phpbb\db\migration\data\v320\v320',
            '\verturin\chastitytracker\migrations\v1_0_0_clean_modules'
        );
    }
    
    public function revert_data()
    {
        return array(
            // Supprimer les modules dans l'ordre inverse
            array('module.remove', array(
                'ucp',
                'UCP_CHASTITY_TRACKER',
                array(
                    'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                    'modes' => array('calendar', 'statistics', 'locktober'),
                ),
            )),
            array('module.remove', array(
                'ucp',
                0,
                'UCP_CHASTITY_TRACKER'
            )),
            array('module.remove', array(
                'acp',
                'ACP_CHASTITY_TRACKER',
                array(
                    'module_basename' => '\verturin\chastitytracker\acp\main_module',
                    'modes' => array('settings', 'statistics'),
                ),
            )),
            array('module.remove', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_CHASTITY_TRACKER'
            )),
        );
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'chastity_periods' => array(
                    'COLUMNS' => array(
                        'period_id' => array('UINT', null, 'auto_increment'),
                        'user_id' => array('UINT', 0),
                        'start_date' => array('UINT:11', 0),
                        'end_date' => array('UINT:11', 0),
                        'status' => array('VCHAR:20', 'active'),
                        'is_permanent' => array('BOOL', 0),
                        'is_locktober' => array('BOOL', 0),
                        'locktober_year' => array('UINT', 0),
                        'locktober_completed' => array('BOOL', 0),
                        'days_count' => array('UINT', 0),
                        'notes' => array('TEXT', ''),
                        'rule_masturbation' => array('BOOL', 0),
                        'rule_ejaculation' => array('BOOL', 0),
                        'rule_sleep_removal' => array('BOOL', 0),
                        'rule_public_removal' => array('BOOL', 0),
                        'rule_medical_removal' => array('BOOL', 0),
                        'created_time' => array('UINT:11', 0),
                        'updated_time' => array('UINT:11', 0),
                    ),
                    'PRIMARY_KEY' => 'period_id',
                    'KEYS' => array(
                        'user_id' => array('INDEX', 'user_id'),
                        'status' => array('INDEX', 'status'),
                        'is_locktober' => array('INDEX', 'is_locktober'),
                    ),
                ),
            ),
            'add_columns' => array(
                $this->table_prefix . 'users' => array(
                    'chastity_status' => array('VCHAR:20', 'free'),
                    'chastity_current_period_id' => array('UINT', 0),
                    'chastity_total_days' => array('UINT', 0),
                ),
            ),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_columns' => array(
                $this->table_prefix . 'users' => array(
                    'chastity_status',
                    'chastity_current_period_id',
                    'chastity_total_days',
                ),
            ),
            'drop_tables' => array(
                $this->table_prefix . 'chastity_periods',
            ),
        );
    }

    public function update_data()
    {
        return array(
            // Ajouter les permissions
            array('permission.add', array('u_chastity_view', true)),
            array('permission.add', array('u_chastity_manage', true)),
            array('permission.add', array('m_chastity_moderate', true)),
            
            // Définir les permissions par défaut
            array('permission.permission_set', array('ROLE_USER_FULL', 'u_chastity_view')),
            array('permission.permission_set', array('ROLE_MOD_FULL', 'm_chastity_moderate')),
            
            // Ajouter les configurations pour les règles
            array('config.add', array('chastity_enable', 1)),
            array('config.add', array('chastity_profile_display', 1)),
            array('config.add', array('chastity_min_period_days', 0)),
            array('config.add', array('chastity_rule_masturbation_enabled', 1)),
            array('config.add', array('chastity_rule_ejaculation_enabled', 1)),
            array('config.add', array('chastity_rule_sleep_removal_enabled', 1)),
            array('config.add', array('chastity_rule_public_removal_enabled', 1)),
            array('config.add', array('chastity_rule_medical_removal_enabled', 1)),
            
            // Configurations pour le Locktober
            array('config.add', array('chastity_locktober_enabled', 1)),
            array('config.add', array('chastity_locktober_year', date('Y'))),
            array('config.add', array('chastity_locktober_badge_enabled', 1)),
            array('config.add', array('chastity_locktober_leaderboard_enabled', 1)),
            
            // Ajouter le module ACP
            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_CHASTITY_TRACKER'
            )),
            array('module.add', array(
                'acp',
                'ACP_CHASTITY_TRACKER',
                array(
                    'module_basename' => '\verturin\chastitytracker\acp\main_module',
                    'modes' => array('settings', 'statistics'),
                ),
            )),
            
            // Ajouter le module UCP
            array('module.add', array(
                'ucp',
                0,
                'UCP_CHASTITY_TRACKER'
            )),
            array('module.add', array(
                'ucp',
                'UCP_CHASTITY_TRACKER',
                array(
                    'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                    'modes' => array('calendar', 'statistics', 'locktober'),
                ),
            )),
        );
    }
}
