<?php
/**
 * Chastity Tracker — Migration v3.8.1
 * Système de Récompenses / Anneaux (style Apple Watch) :
 *  - Table chastity_active_days : suivi des jours de connexion (tous membres).
 *  - Table chastity_rewards_history : historique annuel des anneaux complétés.
 *  - 9 objectifs configurables (cage / posts / connexions × jour / mois / an).
 *  - Module ACP dédié "Récompenses" + module UCP "Récompenses".
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_active_days');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v380'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_active_days' => [
                    'COLUMNS' => [
                        'user_id'  => ['UINT:11', 0],
                        'day_date' => ['UINT:11', 0], // format AAAAMMJJ
                    ],
                    'PRIMARY_KEY' => ['user_id', 'day_date'],
                ],
                $this->table_prefix . 'chastity_rewards_history' => [
                    'COLUMNS' => [
                        'reward_id'    => ['UINT:11', null, 'auto_increment'],
                        'user_id'      => ['UINT:11', 0],
                        'reward_year'  => ['UINT:11', 0],
                        'ring_type'    => ['VCHAR:20', ''],  // cage / posts / logins
                        'ring_period'  => ['VCHAR:10', ''],  // day / month / year
                        'goal_value'   => ['UINT:11', 0],
                        'reached_value'=> ['UINT:11', 0],
                        'completed_at' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'reward_id',
                ],
                $this->table_prefix . 'chastity_locktober_rewards' => [
                    'COLUMNS' => [
                        'locktober_year' => ['UINT:11', 0],
                        'reward_label'   => ['VCHAR:255', ''],
                        'reward_image'   => ['VCHAR:255', ''],
                        'updated_time'   => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'locktober_year',
                ],
                $this->table_prefix . 'chastity_locktober_milestones' => [
                    'COLUMNS' => [
                        'milestone_id'   => ['UINT:11', null, 'auto_increment'],
                        'threshold'      => ['UINT:11', 0],
                        'milestone_label'=> ['VCHAR:255', ''],
                        'milestone_image'=> ['VCHAR:255', ''],
                        'updated_time'   => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'milestone_id',
                ],
                $this->table_prefix . 'chastity_special_days' => [
                    'COLUMNS' => [
                        'sday_id'      => ['UINT:11', null, 'auto_increment'],
                        'sday_day'     => ['UINT:11', 1],
                        'sday_month'   => ['UINT:11', 1],
                        'sday_label'   => ['VCHAR:255', ''],
                        'sday_image'   => ['VCHAR:255', ''],
                        'updated_time' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'sday_id',
                ],
                $this->table_prefix . 'chastity_perfect_counts' => [
                    'COLUMNS' => [
                        'user_id'     => ['UINT:11', 0],
                        'pscale'      => ['VCHAR:10', ''],   // day / month / year
                        'pcount'      => ['UINT:11', 0],
                        'last_period' => ['UINT:11', 0],     // AAAAMMJJ / AAAAMM / AAAA
                    ],
                    'PRIMARY_KEY' => ['user_id', 'pscale'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'chastity_active_days',
                $this->table_prefix . 'chastity_rewards_history',
                $this->table_prefix . 'chastity_locktober_rewards',
                $this->table_prefix . 'chastity_locktober_milestones',
                $this->table_prefix . 'chastity_special_days',
                $this->table_prefix . 'chastity_perfect_counts',
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Activation globale du système de récompenses
            ['config.add', ['chastity_rewards_enabled', 0]],

            // Horodatage de dernière exécution du cron Locktober (complétion J31)
            ['config.add', ['chastity_locktober_last_gc', 0]],
            // Horodatage de dernière exécution du cron récompenses (périodes parfaites)
            ['config.add', ['chastity_rewards_last_gc', 0]],

            // Objectifs CAGE (en heures)
            ['config.add', ['chastity_goal_cage_day', 24]],
            ['config.add', ['chastity_goal_cage_month', 720]],
            ['config.add', ['chastity_goal_cage_year', 8760]],

            // Objectifs POSTS (nombre de messages)
            ['config.add', ['chastity_goal_posts_day', 3]],
            ['config.add', ['chastity_goal_posts_month', 50]],
            ['config.add', ['chastity_goal_posts_year', 500]],

            // Objectifs CONNEXIONS (jours actifs)
            ['config.add', ['chastity_goal_logins_day', 1]],
            ['config.add', ['chastity_goal_logins_month', 20]],
            ['config.add', ['chastity_goal_logins_year', 200]],

            // Module ACP dédié "Récompenses"
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['rewards'],
            ]]],

            // Replacer "Sauvegarde" en dernier
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['backup'],
            ]]],
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['backup'],
            ]]],

            // Module UCP "Récompenses"
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['rewards'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['chastity_rewards_enabled']],
            ['config.remove', ['chastity_locktober_last_gc']],
            ['config.remove', ['chastity_rewards_last_gc']],
            ['config.remove', ['chastity_goal_cage_day']],
            ['config.remove', ['chastity_goal_cage_month']],
            ['config.remove', ['chastity_goal_cage_year']],
            ['config.remove', ['chastity_goal_posts_day']],
            ['config.remove', ['chastity_goal_posts_month']],
            ['config.remove', ['chastity_goal_posts_year']],
            ['config.remove', ['chastity_goal_logins_day']],
            ['config.remove', ['chastity_goal_logins_month']],
            ['config.remove', ['chastity_goal_logins_year']],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'rewards']],
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', 'rewards']],
        ];
    }
}
