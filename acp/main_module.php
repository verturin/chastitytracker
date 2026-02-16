<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $config;

        $user->add_lang_ext('verturin/chastitytracker', 'common');
        $this->tpl_name = 'acp_chastity_' . $mode;
        $this->page_title = $user->lang['ACP_CHASTITY_' . strtoupper($mode)];

        $periods_table = $phpbb_container->getParameter('vendor.chastitytracker.tables.chastity_periods');

        add_form_key('acp_chastity');

        switch ($mode)
        {
            case 'settings':
                $this->settings_mode($user, $template, $request, $config);
            break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table);
            break;
        }
    }

    private function settings_mode($user, $template, $request, $config)
    {
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error('FORM_INVALID');
            }

            $config->set('chastity_enable', $request->variable('chastity_enable', 1));
            $config->set('chastity_profile_display', $request->variable('chastity_profile_display', 1));
            $config->set('chastity_min_period_days', $request->variable('chastity_min_period_days', 0));
            
            // Sauvegarder les règles activées/désactivées
            $config->set('chastity_rule_masturbation_enabled', $request->variable('chastity_rule_masturbation_enabled', 0));
            $config->set('chastity_rule_ejaculation_enabled', $request->variable('chastity_rule_ejaculation_enabled', 0));
            $config->set('chastity_rule_sleep_removal_enabled', $request->variable('chastity_rule_sleep_removal_enabled', 0));
            $config->set('chastity_rule_public_removal_enabled', $request->variable('chastity_rule_public_removal_enabled', 0));
            $config->set('chastity_rule_medical_removal_enabled', $request->variable('chastity_rule_medical_removal_enabled', 0));
            
            // Sauvegarder les paramètres Locktober
            $config->set('chastity_locktober_enabled', $request->variable('chastity_locktober_enabled', 0));
            $config->set('chastity_locktober_year', $request->variable('chastity_locktober_year', date('Y')));
            $config->set('chastity_locktober_badge_enabled', $request->variable('chastity_locktober_badge_enabled', 0));
            $config->set('chastity_locktober_leaderboard_enabled', $request->variable('chastity_locktober_leaderboard_enabled', 0));

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        $template->assign_vars(array(
            'CHASTITY_ENABLE' => $config['chastity_enable'] ?? 1,
            'CHASTITY_PROFILE_DISPLAY' => $config['chastity_profile_display'] ?? 1,
            'CHASTITY_MIN_PERIOD_DAYS' => $config['chastity_min_period_days'] ?? 0,
            
            // Variables pour les règles
            'CHASTITY_RULE_MASTURBATION_ENABLED' => $config['chastity_rule_masturbation_enabled'] ?? 1,
            'CHASTITY_RULE_EJACULATION_ENABLED' => $config['chastity_rule_ejaculation_enabled'] ?? 1,
            'CHASTITY_RULE_SLEEP_REMOVAL_ENABLED' => $config['chastity_rule_sleep_removal_enabled'] ?? 1,
            'CHASTITY_RULE_PUBLIC_REMOVAL_ENABLED' => $config['chastity_rule_public_removal_enabled'] ?? 1,
            'CHASTITY_RULE_MEDICAL_REMOVAL_ENABLED' => $config['chastity_rule_medical_removal_enabled'] ?? 1,
            
            // Variables pour Locktober
            'CHASTITY_LOCKTOBER_ENABLED' => $config['chastity_locktober_enabled'] ?? 1,
            'CHASTITY_LOCKTOBER_YEAR' => $config['chastity_locktober_year'] ?? date('Y'),
            'CHASTITY_LOCKTOBER_BADGE_ENABLED' => $config['chastity_locktober_badge_enabled'] ?? 1,
            'CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED' => $config['chastity_locktober_leaderboard_enabled'] ?? 1,
            
            'U_ACTION' => $this->u_action,
        ));
    }

    private function statistics_mode($user, $template, $db, $periods_table)
    {
        // Statistiques globales
        $sql = 'SELECT COUNT(*) as total_periods, SUM(days_count) as total_days
            FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $global_stats = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        // Nombre d'utilisateurs avec au moins une période
        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users
            FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        // Périodes actives
        $sql = 'SELECT COUNT(*) as active_periods
            FROM ' . $periods_table . '
            WHERE status = \'active\'';
        $result = $db->sql_query($sql);
        $active_periods = (int) $db->sql_fetchfield('active_periods');
        $db->sql_freeresult($result);

        // Top 10 des utilisateurs
        $sql = 'SELECT u.username, u.user_colour, SUM(p.days_count) as total_days, COUNT(p.period_id) as total_periods
            FROM ' . $periods_table . ' p
            LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
            GROUP BY p.user_id, u.username, u.user_colour
            ORDER BY total_days DESC
            LIMIT 10';
        $result = $db->sql_query($sql);
        
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('top_users', array(
                'USERNAME' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                'TOTAL_DAYS' => $row['total_days'],
                'TOTAL_PERIODS' => $row['total_periods'],
            ));
        }
        $db->sql_freeresult($result);

        $template->assign_vars(array(
            'TOTAL_PERIODS' => (int) $global_stats['total_periods'],
            'TOTAL_DAYS' => (int) $global_stats['total_days'],
            'TOTAL_USERS' => $total_users,
            'ACTIVE_PERIODS' => $active_periods,
            'AVERAGE_DAYS' => $global_stats['total_periods'] > 0 ? round($global_stats['total_days'] / $global_stats['total_periods'], 1) : 0,
        ));
    }
}
