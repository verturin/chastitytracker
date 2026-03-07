<?php
/**
 * Chastity Tracker - ACP Module
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;
    private $chastity_users_table;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $config;

        $user->add_lang_ext('verturin/chastitytracker', 'common');
        $this->tpl_name   = 'acp_chastity_' . $mode;
        $this->page_title = $user->lang['ACP_CHASTITY_' . strtoupper($mode)];

        $this->chastity_users_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');
        $periods_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_periods');

        add_form_key('acp_chastity');

        switch ($mode)
        {
            case 'settings':
                $this->settings_mode($user, $template, $request, $config);
            break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table);
            break;

            case 'rebuild':
                $this->rebuild_mode($user, $template, $request, $db, $periods_table);
            break;
        }
    }

    private function settings_mode($user, $template, $request, $config)
    {
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_enable',                       $request->variable('chastity_enable', 1));
            $config->set('chastity_profile_display',              $request->variable('chastity_profile_display', 0));
            $config->set('chastity_min_period_days',              $request->variable('chastity_min_period_days', 0));
            $config->set('chastity_rule_masturbation_enabled',    $request->variable('chastity_rule_masturbation_enabled', 0));
            $config->set('chastity_rule_ejaculation_enabled',     $request->variable('chastity_rule_ejaculation_enabled', 0));
            $config->set('chastity_rule_sleep_removal_enabled',   $request->variable('chastity_rule_sleep_removal_enabled', 0));
            $config->set('chastity_rule_public_removal_enabled',  $request->variable('chastity_rule_public_removal_enabled', 0));
            $config->set('chastity_rule_medical_removal_enabled', $request->variable('chastity_rule_medical_removal_enabled', 0));
            $config->set('chastity_locktober_enabled',            $request->variable('chastity_locktober_enabled', 0));
            $config->set('chastity_locktober_year',               $request->variable('chastity_locktober_year', (int) date('Y')));
            $config->set('chastity_locktober_badge_enabled',      $request->variable('chastity_locktober_badge_enabled', 0));
            $config->set('chastity_locktober_leaderboard_enabled',$request->variable('chastity_locktober_leaderboard_enabled', 0));

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        $template->assign_vars([
            'CHASTITY_ENABLE'                         => $config['chastity_enable'] ?? 1,
            'CHASTITY_PROFILE_DISPLAY'                => $config['chastity_profile_display'] ?? 0,
            'CHASTITY_MIN_PERIOD_DAYS'                => $config['chastity_min_period_days'] ?? 0,
            'CHASTITY_RULE_MASTURBATION_ENABLED'      => $config['chastity_rule_masturbation_enabled'] ?? 1,
            'CHASTITY_RULE_EJACULATION_ENABLED'       => $config['chastity_rule_ejaculation_enabled'] ?? 1,
            'CHASTITY_RULE_SLEEP_REMOVAL_ENABLED'     => $config['chastity_rule_sleep_removal_enabled'] ?? 1,
            'CHASTITY_RULE_PUBLIC_REMOVAL_ENABLED'    => $config['chastity_rule_public_removal_enabled'] ?? 1,
            'CHASTITY_RULE_MEDICAL_REMOVAL_ENABLED'   => $config['chastity_rule_medical_removal_enabled'] ?? 1,
            'CHASTITY_LOCKTOBER_ENABLED'              => $config['chastity_locktober_enabled'] ?? 1,
            'CHASTITY_LOCKTOBER_YEAR'                 => $config['chastity_locktober_year'] ?? date('Y'),
            'CHASTITY_LOCKTOBER_BADGE_ENABLED'        => $config['chastity_locktober_badge_enabled'] ?? 1,
            'CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED'  => $config['chastity_locktober_leaderboard_enabled'] ?? 1,
            'U_ACTION'                                => $this->u_action,
        ]);
    }

    private function rebuild_mode($user, $template, $request, $db, $periods_table)
    {
        $rebuilt = 0;

        if ($request->is_set_post('rebuild'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            // Récupérer tous les utilisateurs ayant des périodes
            $sql = 'SELECT DISTINCT user_id FROM ' . $periods_table;
            $result = $db->sql_query($sql);
            $user_ids = [];
            while ($row = $db->sql_fetchrow($result))
            {
                $user_ids[] = (int) $row['user_id'];
            }
            $db->sql_freeresult($result);

            foreach ($user_ids as $uid)
            {
                // Calculer le total des jours de toutes les périodes complétées
                $sql = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                        WHERE user_id = $uid AND status = 'completed'";
                $result = $db->sql_query($sql);
                $total_days = (int) $db->sql_fetchfield('total');
                $db->sql_freeresult($result);

                // Récupérer la période active éventuelle
                $sql = 'SELECT period_id, start_date FROM ' . $periods_table . "
                        WHERE user_id = $uid AND status = 'active'
                        ORDER BY start_date DESC LIMIT 1";
                $result = $db->sql_query($sql);
                $active = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                if ($active)
                {
                    $active_days = (int) floor((time() - (int) $active['start_date']) / 86400);
                    $total_days += $active_days;

                    $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                        SET chastity_status = 'locked',
                            chastity_current_period = " . (int) $active['period_id'] . ",
                            chastity_total_days = $total_days
                        WHERE user_id = $uid");
                }
                else
                {
                    $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                        SET chastity_status = 'free',
                            chastity_current_period = 0,
                            chastity_total_days = $total_days
                        WHERE user_id = $uid");
                }

                $rebuilt++;
            }

            trigger_error(sprintf($user->lang['ACP_CHASTITY_REBUILD_DONE'], $rebuilt) . adm_back_link($this->u_action));
        }

        // Statistiques pour affichage avant rebuild
        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(*) as active FROM ' . $periods_table . " WHERE status = 'active'";
        $result = $db->sql_query($sql);
        $active_count = (int) $db->sql_fetchfield('active');
        $db->sql_freeresult($result);

        $template->assign_vars([
            'REBUILD_TOTAL_USERS'   => $total_users,
            'REBUILD_ACTIVE_PERIODS'=> $active_count,
            'U_ACTION'              => $this->u_action,
        ]);
    }

    private function statistics_mode($user, $template, $db, $periods_table)
    {
        $sql = 'SELECT COUNT(*) as total_periods, SUM(days_count) as total_days FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $global = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(*) as active_periods FROM ' . $periods_table . " WHERE status = 'active'";
        $result = $db->sql_query($sql);
        $active_periods = (int) $db->sql_fetchfield('active_periods');
        $db->sql_freeresult($result);

        $sql = 'SELECT u.user_id, u.username, u.user_colour, SUM(p.days_count) as total_days, COUNT(p.period_id) as total_periods
                FROM ' . $periods_table . ' p
                LEFT JOIN ' . $this->chastity_users_table . ' u ON u.user_id = p.user_id
                GROUP BY p.user_id, u.username, u.user_colour, u.user_id
                ORDER BY total_days DESC
                LIMIT 10';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('top_users', [
                'USERNAME'      => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                'TOTAL_DAYS'    => (int) $row['total_days'],
                'TOTAL_PERIODS' => (int) $row['total_periods'],
            ]);
        }
        $db->sql_freeresult($result);

        $total = (int) $global['total_periods'];
        $days  = (int) $global['total_days'];

        $template->assign_vars([
            'TOTAL_PERIODS'  => $total,
            'TOTAL_DAYS'     => $days,
            'TOTAL_USERS'    => $total_users,
            'ACTIVE_PERIODS' => $active_periods,
            'AVERAGE_DAYS'   => $total > 0 ? round($days / $total, 1) : 0,
        ]);
    }
}
