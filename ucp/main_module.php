<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\ucp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $auth;

        $user->add_lang_ext('verturin/chastitytracker', 'common');
        $this->tpl_name = 'ucp_chastity_' . $mode;
        $this->page_title = $user->lang['UCP_CHASTITY_' . strtoupper($mode)];

        $periods_table = $phpbb_container->getParameter('vendor.chastitytracker.tables.chastity_periods');

        add_form_key('ucp_chastity');

        switch ($mode)
        {
            case 'calendar':
                $this->calendar_mode($user, $template, $request, $db, $periods_table, $auth);
            break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table);
            break;
            
            case 'locktober':
                $this->locktober_mode($user, $template, $request, $db, $periods_table, $auth);
            break;
        }
    }

    private function calendar_mode($user, $template, $request, $db, $periods_table, $auth)
    {
        global $config;
        
        // Gérer l'ajout d'une nouvelle période
        if ($request->is_set_post('add_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error('FORM_INVALID');
            }

            $start_date = $request->variable('start_date', '');
            $notes = $request->variable('notes', '', true);
            $is_permanent = $request->variable('is_permanent', 0);
            
            // Récupérer les règles
            $rule_masturbation = $request->variable('rule_masturbation', 0);
            $rule_ejaculation = $request->variable('rule_ejaculation', 0);
            $rule_sleep_removal = $request->variable('rule_sleep_removal', 0);
            $rule_public_removal = $request->variable('rule_public_removal', 0);
            $rule_medical_removal = $request->variable('rule_medical_removal', 0);

            // Vérifier qu'il n'y a pas déjà une période active
            $sql = 'SELECT COUNT(*) as active_count
                FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                    AND status = \'active\'';
            $result = $db->sql_query($sql);
            $active_count = (int) $db->sql_fetchfield('active_count');
            $db->sql_freeresult($result);

            if ($active_count > 0)
            {
                trigger_error('CHASTITY_ALREADY_ACTIVE');
            }

            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp || $start_timestamp > time())
            {
                trigger_error('CHASTITY_INVALID_DATE');
            }

            // Insérer la nouvelle période
            $sql_ary = array(
                'user_id' => (int) $user->data['user_id'],
                'start_date' => $start_timestamp,
                'end_date' => null,
                'status' => 'active',
                'is_permanent' => (int) $is_permanent,
                'days_count' => 0,
                'notes' => $notes,
                'rule_masturbation' => (int) $rule_masturbation,
                'rule_ejaculation' => (int) $rule_ejaculation,
                'rule_sleep_removal' => (int) $rule_sleep_removal,
                'rule_public_removal' => (int) $rule_public_removal,
                'rule_medical_removal' => (int) $rule_medical_removal,
                'created_time' => time(),
                'updated_time' => time(),
            );

            $sql = 'INSERT INTO ' . $periods_table . ' ' . $db->sql_build_array('INSERT', $sql_ary);
            $db->sql_query($sql);
            $period_id = $db->sql_nextid();

            // Mettre à jour le profil utilisateur
            $sql = 'UPDATE ' . USERS_TABLE . '
                SET chastity_status = \'locked\',
                    chastity_current_period_id = ' . (int) $period_id . '
                WHERE user_id = ' . (int) $user->data['user_id'];
            $db->sql_query($sql);

            trigger_error($user->lang['CHASTITY_PERIOD_ADDED']);
        }

        // Gérer la fin d'une période
        if ($request->is_set_post('end_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error('FORM_INVALID');
            }

            $period_id = $request->variable('period_id', 0);

            // Vérifier que la période appartient à l'utilisateur
            $sql = 'SELECT *
                FROM ' . $periods_table . '
                WHERE period_id = ' . (int) $period_id . '
                    AND user_id = ' . (int) $user->data['user_id'] . '
                    AND status = \'active\'';
            $result = $db->sql_query($sql);
            $period = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($period)
            {
                $end_date = time();
                $days_count = floor(($end_date - $period['start_date']) / 86400);

                // Mettre à jour la période
                $sql = 'UPDATE ' . $periods_table . '
                    SET end_date = ' . $end_date . ',
                        status = \'completed\',
                        days_count = ' . (int) $days_count . ',
                        updated_time = ' . time() . '
                    WHERE period_id = ' . (int) $period_id;
                $db->sql_query($sql);

                // Calculer le total de jours
                $sql = 'SELECT SUM(days_count) as total_days
                    FROM ' . $periods_table . '
                    WHERE user_id = ' . (int) $user->data['user_id'];
                $result = $db->sql_query($sql);
                $total_days = (int) $db->sql_fetchfield('total_days');
                $db->sql_freeresult($result);

                // Mettre à jour le profil utilisateur
                $sql = 'UPDATE ' . USERS_TABLE . '
                    SET chastity_status = \'free\',
                        chastity_current_period_id = 0,
                        chastity_total_days = ' . $total_days . '
                    WHERE user_id = ' . (int) $user->data['user_id'];
                $db->sql_query($sql);

                trigger_error($user->lang['CHASTITY_PERIOD_ENDED']);
            }
        }

        // Supprimer une période
        if ($request->is_set_post('delete_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error('FORM_INVALID');
            }

            $period_id = $request->variable('period_id', 0);

            // Vérifier que la période appartient à l'utilisateur et n'est pas active
            $sql = 'DELETE FROM ' . $periods_table . '
                WHERE period_id = ' . (int) $period_id . '
                    AND user_id = ' . (int) $user->data['user_id'] . '
                    AND status != \'active\'';
            $db->sql_query($sql);

            if ($db->sql_affectedrows() > 0)
            {
                trigger_error($user->lang['CHASTITY_PERIOD_DELETED']);
            }
        }

        // Récupérer toutes les périodes
        $sql = 'SELECT *
            FROM ' . $periods_table . '
            WHERE user_id = ' . (int) $user->data['user_id'] . '
            ORDER BY start_date DESC';
        $result = $db->sql_query($sql);
        $periods = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);

        $has_active = false;
        $current_days = 0;

        foreach ($periods as $period)
        {
            $is_active = ($period['status'] == 'active');
            if ($is_active)
            {
                $has_active = true;
                $current_days = floor((time() - $period['start_date']) / 86400);
            }

            $template->assign_block_vars('periods', array(
                'PERIOD_ID' => $period['period_id'],
                'START_DATE' => $user->format_date($period['start_date'], 'd/m/Y'),
                'START_DATE_ISO' => date('Y-m-d', $period['start_date']),
                'END_DATE' => $period['end_date'] ? $user->format_date($period['end_date'], 'd/m/Y') : '-',
                'END_DATE_ISO' => $period['end_date'] ? date('Y-m-d', $period['end_date']) : '',
                'STATUS' => $user->lang['CHASTITY_STATUS_' . strtoupper($period['status'])],
                'DAYS_COUNT' => $is_active ? $current_days : $period['days_count'],
                'NOTES' => $period['notes'],
                'IS_ACTIVE' => $is_active,
                'IS_PERMANENT' => (bool) $period['is_permanent'],
                'CAN_DELETE' => !$is_active,
                
                // Règles
                'RULE_MASTURBATION' => (bool) $period['rule_masturbation'],
                'RULE_EJACULATION' => (bool) $period['rule_ejaculation'],
                'RULE_SLEEP_REMOVAL' => (bool) $period['rule_sleep_removal'],
                'RULE_PUBLIC_REMOVAL' => (bool) $period['rule_public_removal'],
                'RULE_MEDICAL_REMOVAL' => (bool) $period['rule_medical_removal'],
            ));
        }

        $template->assign_vars(array(
            'HAS_ACTIVE_PERIOD' => $has_active,
            'CURRENT_DAYS' => $current_days,
            'U_ACTION' => $this->u_action,
            'TODAY_DATE' => date('Y-m-d'),
            
            // Règles activées
            'S_RULE_MASTURBATION_ENABLED' => (bool) ($config['chastity_rule_masturbation_enabled'] ?? 1),
            'S_RULE_EJACULATION_ENABLED' => (bool) ($config['chastity_rule_ejaculation_enabled'] ?? 1),
            'S_RULE_SLEEP_REMOVAL_ENABLED' => (bool) ($config['chastity_rule_sleep_removal_enabled'] ?? 1),
            'S_RULE_PUBLIC_REMOVAL_ENABLED' => (bool) ($config['chastity_rule_public_removal_enabled'] ?? 1),
            'S_RULE_MEDICAL_REMOVAL_ENABLED' => (bool) ($config['chastity_rule_medical_removal_enabled'] ?? 1),
        ));
    }

    private function statistics_mode($user, $template, $db, $periods_table)
    {
        // Récupérer toutes les périodes
        $sql = 'SELECT *
            FROM ' . $periods_table . '
            WHERE user_id = ' . (int) $user->data['user_id'] . '
            ORDER BY start_date DESC';
        $result = $db->sql_query($sql);
        $periods = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);

        // Calculer les statistiques globales
        $total_days = 0;
        $total_periods = count($periods);
        $current_days = 0;
        $status = 'free';
        $longest_period = 0;
        $average_period = 0;

        // Statistiques par année
        $year_stats = array();
        $current_year = date('Y');

        foreach ($periods as $period)
        {
            $year = date('Y', $period['start_date']);
            
            if (!isset($year_stats[$year]))
            {
                $year_stats[$year] = array(
                    'days' => 0,
                    'periods' => 0,
                );
            }

            $days = 0;
            if ($period['status'] == 'active')
            {
                $status = 'locked';
                $days = floor((time() - $period['start_date']) / 86400);
                $current_days = $days;
            }
            else
            {
                $days = (int) $period['days_count'];
            }

            $total_days += $days;
            $year_stats[$year]['days'] += $days;
            $year_stats[$year]['periods']++;

            if ($days > $longest_period)
            {
                $longest_period = $days;
            }
        }

        if ($total_periods > 0)
        {
            $average_period = round($total_days / $total_periods, 1);
        }

        // Statistiques par mois pour l'année en cours
        $month_stats = array();
        for ($i = 1; $i <= 12; $i++)
        {
            $month_stats[$i] = 0;
        }

        foreach ($periods as $period)
        {
            if (date('Y', $period['start_date']) == $current_year)
            {
                $month = (int) date('m', $period['start_date']);
                
                if ($period['status'] == 'active')
                {
                    $days = floor((time() - $period['start_date']) / 86400);
                }
                else
                {
                    $days = (int) $period['days_count'];
                }
                
                $month_stats[$month] += $days;
            }
        }

        // Assigner les variables au template
        $template->assign_vars(array(
            'TOTAL_DAYS' => $total_days,
            'TOTAL_PERIODS' => $total_periods,
            'CURRENT_DAYS' => $current_days,
            'CHASTITY_STATUS' => $user->lang['CHASTITY_STATUS_' . strtoupper($status)],
            'LONGEST_PERIOD' => $longest_period,
            'AVERAGE_PERIOD' => $average_period,
            'CURRENT_YEAR_DAYS' => isset($year_stats[$current_year]) ? $year_stats[$current_year]['days'] : 0,
        ));

        // Statistiques par année
        krsort($year_stats);
        foreach ($year_stats as $year => $stats)
        {
            $template->assign_block_vars('year_stats', array(
                'YEAR' => $year,
                'DAYS' => $stats['days'],
                'PERIODS' => $stats['periods'],
            ));
        }

        // Statistiques par mois
        $month_names = array(
            1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
            5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
            9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER'
        );

        foreach ($month_stats as $month => $days)
        {
            $template->assign_block_vars('month_stats', array(
                'MONTH' => $user->lang[$month_names[$month]],
                'DAYS' => $days,
            ));
        }
    }
    
    private function locktober_mode($user, $template, $request, $db, $periods_table, $auth)
    {
        global $config;
        
        // Vérifier si Locktober est activé
        if (!($config['chastity_locktober_enabled'] ?? 1))
        {
            trigger_error('CHASTITY_LOCKTOBER_DISABLED');
        }
        
        $current_year = (int) ($config['chastity_locktober_year'] ?? date('Y'));
        $current_month = (int) date('m');
        $is_october = ($current_month == 10);
        
        // Gérer le démarrage du Locktober
        if ($request->is_set_post('start_locktober'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error('FORM_INVALID');
            }
            
            // Vérifier qu'il n'y a pas déjà une période active
            $sql = 'SELECT COUNT(*) as active_count
                FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                    AND status = \'active\'';
            $result = $db->sql_query($sql);
            $active_count = (int) $db->sql_fetchfield('active_count');
            $db->sql_freeresult($result);
            
            if ($active_count > 0)
            {
                trigger_error('CHASTITY_ALREADY_ACTIVE');
            }
            
            // Créer la période Locktober (1er octobre de l'année en cours ou aujourd'hui)
            $day_of_month = (int) date('d');
            $start_day = ($is_october && $day_of_month > 1) ? $day_of_month : 1;
            $start_date = mktime(0, 0, 0, 10, $start_day, $current_year);
            
            $sql_ary = array(
                'user_id' => (int) $user->data['user_id'],
                'start_date' => $start_date,
                'end_date' => null,
                'status' => 'active',
                'is_permanent' => 0,
                'is_locktober' => 1,
                'locktober_year' => $current_year,
                'locktober_completed' => 0,
                'days_count' => 0,
                'notes' => $user->lang['CHASTITY_LOCKTOBER_CHALLENGE'] . ' ' . $current_year,
                'rule_masturbation' => 0,
                'rule_ejaculation' => 0,
                'rule_sleep_removal' => 0,
                'rule_public_removal' => 0,
                'rule_medical_removal' => 1,
                'created_time' => time(),
                'updated_time' => time(),
            );
            
            $sql = 'INSERT INTO ' . $periods_table . ' ' . $db->sql_build_array('INSERT', $sql_ary);
            $db->sql_query($sql);
            $period_id = $db->sql_nextid();
            
            // Mettre à jour le profil utilisateur
            $sql = 'UPDATE ' . USERS_TABLE . '
                SET chastity_status = \'locked\',
                    chastity_current_period_id = ' . (int) $period_id . '
                WHERE user_id = ' . (int) $user->data['user_id'];
            $db->sql_query($sql);
            
            trigger_error($user->lang['CHASTITY_PERIOD_ADDED']);
        }
        
        // Récupérer la période Locktober active de l'utilisateur
        $sql = 'SELECT *
            FROM ' . $periods_table . '
            WHERE user_id = ' . (int) $user->data['user_id'] . '
                AND is_locktober = 1
                AND locktober_year = ' . $current_year . '
                AND status = \'active\'';
        $result = $db->sql_query($sql);
        $active_locktober = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        
        $current_day = 0;
        $has_active_locktober = false;
        
        if ($active_locktober)
        {
            $has_active_locktober = true;
            $current_day = floor((time() - $active_locktober['start_date']) / 86400) + 1;
        }
        
        // Récupérer les Locktober complétés de l'utilisateur
        $sql = 'SELECT *
            FROM ' . $periods_table . '
            WHERE user_id = ' . (int) $user->data['user_id'] . '
                AND is_locktober = 1
                AND locktober_completed = 1
            ORDER BY locktober_year DESC';
        $result = $db->sql_query($sql);
        $completed_locktober = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);
        
        foreach ($completed_locktober as $period)
        {
            $template->assign_block_vars('completed_locktober', array(
                'YEAR' => $period['locktober_year'],
                'DAYS' => $period['days_count'],
            ));
        }
        
        // Classement Locktober (si activé)
        if ($config['chastity_locktober_leaderboard_enabled'] ?? 1)
        {
            $sql = 'SELECT u.username, u.user_colour, u.user_id, p.locktober_year, p.start_date
                FROM ' . $periods_table . ' p
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
                WHERE p.is_locktober = 1
                    AND p.locktober_year = ' . $current_year . '
                    AND p.status = \'active\'
                ORDER BY p.start_date ASC
                LIMIT 20';
            $result = $db->sql_query($sql);
            
            $rank = 1;
            while ($row = $db->sql_fetchrow($result))
            {
                $days_locked = floor((time() - $row['start_date']) / 86400) + 1;
                
                $template->assign_block_vars('leaderboard', array(
                    'RANK' => $rank,
                    'USERNAME' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                    'DAYS' => $days_locked,
                ));
                
                $rank++;
            }
            $db->sql_freeresult($result);
        }
        
        $template->assign_vars(array(
            'LOCKTOBER_YEAR' => $current_year,
            'IS_OCTOBER' => $is_october,
            'HAS_ACTIVE_LOCKTOBER' => $has_active_locktober,
            'LOCKTOBER_CURRENT_DAY' => $current_day,
            'LOCKTOBER_LEADERBOARD_ENABLED' => (bool) ($config['chastity_locktober_leaderboard_enabled'] ?? 1),
            'U_ACTION' => $this->u_action,
        ));
    }
}
