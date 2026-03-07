<?php
/**
 * Chastity Tracker - UCP Module
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\ucp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;
    private $chastity_users_table;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $auth, $config;

        $user->add_lang_ext('verturin/chastitytracker', 'common');

        $this->tpl_name   = 'ucp_chastity_' . $mode;
        $this->page_title = $user->lang['UCP_CHASTITY_' . strtoupper($mode)];

        $periods_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_periods');
        $this->chastity_users_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');

        add_form_key('ucp_chastity');

        switch ($mode)
        {
            case 'calendar':
                $this->calendar_mode($user, $template, $request, $db, $periods_table, $auth, $config);
            break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table);
            break;

            case 'locktober':
                $this->locktober_mode($user, $template, $request, $db, $periods_table, $auth, $config);
            break;

            case 'add_past':
                $this->add_past_mode($user, $template, $request, $db, $periods_table, $auth, $config);
            break;
        }
    }

    /**
     * Recalcule et met à jour les totaux d'un utilisateur
     */
    private function recalc_user_totals($db, $periods_table, $user_id)
    {
        // Total des jours des périodes complétées
        $sql = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                WHERE user_id = " . (int) $user_id . " AND status = 'completed'";
        $result = $db->sql_query($sql);
        $total_days = (int) $db->sql_fetchfield('total');
        $db->sql_freeresult($result);

        // Période active éventuelle
        $sql = 'SELECT period_id, start_date FROM ' . $periods_table . "
                WHERE user_id = " . (int) $user_id . " AND status = 'active'
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
                WHERE user_id = " . (int) $user_id);
        }
        else
        {
            $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                SET chastity_status = 'free',
                    chastity_current_period = 0,
                    chastity_total_days = $total_days
                WHERE user_id = " . (int) $user_id);
        }
    }

    private function ensure_chastity_user($user_id, $username, $user_colour, $db)
    {
        $sql = 'SELECT user_id FROM ' . $this->chastity_users_table . '
                WHERE user_id = ' . (int) $user_id;
        $result = $db->sql_query($sql);
        $exists = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        
        if ($exists)
        {
            $sql = 'UPDATE ' . $this->chastity_users_table . '
                    SET username = \'' . $db->sql_escape($username) . '\',
                        user_colour = \'' . $db->sql_escape($user_colour) . '\',
                        updated_time = ' . time() . '
                    WHERE user_id = ' . (int) $user_id;
            $db->sql_query($sql);
        }
        else
        {
            $sql_ary = [
                'user_id' => (int) $user_id,
                'username' => $username,
                'user_colour' => $user_colour,
                'chastity_status' => 'free',
                'chastity_current_period' => 0,
                'chastity_total_days' => 0,
                'created_time' => time(),
                'updated_time' => time(),
            ];
            
            $sql = 'INSERT INTO ' . $this->chastity_users_table . ' ' . 
                   $db->sql_build_array('INSERT', $sql_ary);
            $db->sql_query($sql);
        }
    }

    private function calendar_mode($user, $template, $request, $db, $periods_table, $auth, $config)
    {
        $this->ensure_chastity_user($user->data['user_id'], $user->data['username'], $user->data['user_colour'], $db);

        if (!$auth->acl_get('u_chastity_manage'))
        {
            trigger_error($user->lang['NOT_AUTHORISED']);
        }

        // Ajouter une période
        if ($request->is_set_post('add_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $start_date           = $request->variable('start_date', '');
            $notes                = $request->variable('notes', '', true);
            $is_permanent         = $request->variable('is_permanent', 0);
            $rule_masturbation    = $request->variable('rule_masturbation', 0);
            $rule_ejaculation     = $request->variable('rule_ejaculation', 0);
            $rule_sleep_removal   = $request->variable('rule_sleep_removal', 0);
            $rule_public_removal  = $request->variable('rule_public_removal', 0);
            $rule_medical_removal = $request->variable('rule_medical_removal', 0);

            $sql = 'SELECT COUNT(*) as active_count FROM ' . $periods_table . '
                    WHERE user_id = ' . (int) $user->data['user_id'] . " AND status = 'active'";
            $result = $db->sql_query($sql);
            $active_count = (int) $db->sql_fetchfield('active_count');
            $db->sql_freeresult($result);

            if ($active_count > 0)
            {
                trigger_error($user->lang['CHASTITY_ALREADY_ACTIVE']);
            }

            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp || $start_timestamp > time())
            {
                trigger_error($user->lang['CHASTITY_INVALID_DATE']);
            }

            $sql_ary = [
                'user_id'              => (int) $user->data['user_id'],
                'start_date'           => $start_timestamp,
                'end_date'             => 0,
                'status'               => 'active',
                'is_permanent'         => (int) $is_permanent,
                'is_locktober'         => 0,
                'locktober_year'       => 0,
                'locktober_completed'  => 0,
                'days_count'           => 0,
                'notes'                => $notes,
                'rule_masturbation'    => (int) $rule_masturbation,
                'rule_ejaculation'     => (int) $rule_ejaculation,
                'rule_sleep_removal'   => (int) $rule_sleep_removal,
                'rule_public_removal'  => (int) $rule_public_removal,
                'rule_medical_removal' => (int) $rule_medical_removal,
                'created_time'         => time(),
                'updated_time'         => time(),
            ];

            $db->sql_query('INSERT INTO ' . $periods_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
            $period_id = $db->sql_nextid();

            $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                SET chastity_status = 'locked', chastity_current_period = " . (int) $period_id . "
                WHERE user_id = " . (int) $user->data['user_id']);

            $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);
            trigger_error($user->lang['CHASTITY_PERIOD_ADDED']);
        }

        // Terminer une période
        if ($request->is_set_post('end_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $period_id = $request->variable('period_id', 0);
            $sql = 'SELECT * FROM ' . $periods_table . '
                    WHERE period_id = ' . (int) $period_id . '
                      AND user_id = ' . (int) $user->data['user_id'] . " AND status = 'active'";
            $result = $db->sql_query($sql);
            $period = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($period)
            {
                $end_date   = time();
                $days_count = (int) floor(($end_date - (int) $period['start_date']) / 86400);

                $db->sql_query('UPDATE ' . $periods_table . "
                    SET end_date = $end_date, status = 'completed', days_count = $days_count, updated_time = " . time() . '
                    WHERE period_id = ' . (int) $period_id);

                $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);
                trigger_error($user->lang['CHASTITY_PERIOD_ENDED']);
            }
        }

        // Supprimer une période
        if ($request->is_set_post('delete_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $period_id = $request->variable('period_id', 0);
            $db->sql_query('DELETE FROM ' . $periods_table . '
                WHERE period_id = ' . (int) $period_id . '
                  AND user_id = ' . (int) $user->data['user_id'] . " AND status != 'active'");

            $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);
            trigger_error($user->lang['CHASTITY_PERIOD_DELETED']);
        }

        // Récupérer toutes les périodes
        $sql = 'SELECT * FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                ORDER BY start_date DESC';
        $result  = $db->sql_query($sql);
        $periods = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);

        $has_active   = false;
        $current_days = 0;

        foreach ($periods as $period)
        {
            $is_active = ($period['status'] === 'active');
            if ($is_active)
            {
                $has_active   = true;
                $current_days = (int) floor((time() - (int) $period['start_date']) / 86400);
            }

            $template->assign_block_vars('periods', [
                'PERIOD_ID'            => $period['period_id'],
                'START_DATE'           => $user->format_date((int) $period['start_date'], 'd/m/Y'),
                'END_DATE'             => ((int) $period['end_date'] > 0) ? $user->format_date((int) $period['end_date'], 'd/m/Y') : '-',
                'STATUS'               => $user->lang['CHASTITY_STATUS_' . strtoupper($period['status'])],
                'DAYS_COUNT'           => $is_active ? $current_days : (int) $period['days_count'],
                'NOTES'                => $period['notes'],
                'IS_ACTIVE'            => $is_active,
                'IS_PERMANENT'         => (bool) $period['is_permanent'],
                'IS_LOCKTOBER'         => (bool) $period['is_locktober'],
                'CAN_DELETE'           => !$is_active,
                'RULE_MASTURBATION'    => (bool) $period['rule_masturbation'],
                'RULE_EJACULATION'     => (bool) $period['rule_ejaculation'],
                'RULE_SLEEP_REMOVAL'   => (bool) $period['rule_sleep_removal'],
                'RULE_PUBLIC_REMOVAL'  => (bool) $period['rule_public_removal'],
                'RULE_MEDICAL_REMOVAL' => (bool) $period['rule_medical_removal'],
            ]);
        }

        // Lien vers ajout période passée (si permission)
        $u_add_past = '';
        if ($auth->acl_get('u_chastity_manage'))
        {
            $u_add_past = str_replace('&amp;mode=calendar', '&amp;mode=add_past', $this->u_action);
        }


        // ============================================================
        // GÉNÉRATION DU CALENDRIER VISUEL AVEC NAVIGATION
        // ============================================================
        
        // Récupérer le mois/année depuis l'URL (ou mois actuel par défaut)
        $current_month = $request->variable('month', (int) date('n'));
        $current_year = $request->variable('year', (int) date('Y'));
        
        // Calculer mois précédent
        $prev_month = $current_month - 1;
        $prev_year = $current_year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        
        // Calculer mois suivant
        $next_month = $current_month + 1;
        $next_year = $current_year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }
        
        // Timestamps du mois affiché
        $first_day_month = mktime(0, 0, 0, $current_month, 1, $current_year);
        $last_day_month = mktime(23, 59, 59, $current_month, date('t', $first_day_month), $current_year);
        
        // SQL: Récupérer TOUTES les périodes qui touchent ce mois
        $sql = 'SELECT start_date, end_date, status FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                  AND ((start_date <= ' . $last_day_month . ' AND (end_date >= ' . $first_day_month . ' OR status = \'active\'))
                       OR (start_date >= ' . $first_day_month . ' AND start_date <= ' . $last_day_month . '))';
        $result = $db->sql_query($sql);
        $period_ranges = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);
        
        // Créer un tableau associatif des jours verrouillés
        $locked_days = [];
        foreach ($period_ranges as $period) {
            $start = (int) $period['start_date'];
            $end = $period['status'] === 'active' ? time() : (int) $period['end_date'];
            
            // Parcourir tous les jours de cette période
            for ($day_timestamp = $start; $day_timestamp <= $end; $day_timestamp += 86400) {
                $day_key = date('Y-m-d', $day_timestamp);
                $locked_days[$day_key] = true;
            }
        }
        
        // Générer la grille du calendrier
        $first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
        $days_in_month = (int) date('t', $first_day_of_month);
        $day_of_week = (int) date('N', $first_day_of_month); // 1=Lundi, 7=Dimanche
        
        // Jours du mois précédent (pour remplir première ligne)
        $prev_days_count = $day_of_week - 1;
        $prev_month_total_days = (int) date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
        
        for ($i = $prev_month_total_days - $prev_days_count + 1; $i <= $prev_month_total_days; $i++) {
            $template->assign_block_vars('calendar_days', [
                'DAY' => $i,
                'OTHER_MONTH' => true,
                'IS_LOCKED' => false,
                'IS_TODAY' => false,
            ]);
        }
        
        // Jours du mois actuel
        $today = date('Y-m-d');
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
            $is_locked = isset($locked_days[$date]);
            $is_today = ($date === $today);
            
            $template->assign_block_vars('calendar_days', [
                'DAY' => $day,
                'OTHER_MONTH' => false,
                'IS_LOCKED' => $is_locked,
                'IS_TODAY' => $is_today,
            ]);
        }
        
        // Jours du mois suivant (pour compléter dernière ligne)
        $total_cells = $prev_days_count + $days_in_month;
        $remaining_cells = (7 - ($total_cells % 7)) % 7;
        
        for ($i = 1; $i <= $remaining_cells; $i++) {
            $template->assign_block_vars('calendar_days', [
                'DAY' => $i,
                'OTHER_MONTH' => true,
                'IS_LOCKED' => false,
                'IS_TODAY' => false,
            ]);
        }
        
        // Noms des mois
        $month_names = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $template->assign_vars([
            'HAS_ACTIVE_PERIOD'              => $has_active,
            'CURRENT_DAYS'                   => $current_days,
            'U_ACTION'                       => $this->u_action,
            'U_ADD_PAST'                     => $u_add_past,
            'TODAY_DATE'                     => date('Y-m-d'),
            'MONTH_NAME'                     => $month_names[$current_month],
            'CALENDAR_YEAR'                  => $current_year,
            'PREV_MONTH'                     => $prev_month,
            'PREV_YEAR'                      => $prev_year,
            'NEXT_MONTH'                     => $next_month,
            'NEXT_YEAR'                      => $next_year,
            'S_RULE_MASTURBATION_ENABLED'    => $config['chastity_rule_masturbation_enabled'] ?? 1,
            'S_RULE_EJACULATION_ENABLED'     => $config['chastity_rule_ejaculation_enabled'] ?? 1,
            'S_RULE_SLEEP_REMOVAL_ENABLED'   => $config['chastity_rule_sleep_removal_enabled'] ?? 1,
            'S_RULE_PUBLIC_REMOVAL_ENABLED'  => $config['chastity_rule_public_removal_enabled'] ?? 1,
            'S_RULE_MEDICAL_REMOVAL_ENABLED' => $config['chastity_rule_medical_removal_enabled'] ?? 1,
        ]);
    }

    private function add_past_mode($user, $template, $request, $db, $periods_table, $auth, $config)
    {
        if (!$auth->acl_get('u_chastity_manage'))
        {
            trigger_error($user->lang['NOT_AUTHORISED']);
        }

        if ($request->is_set_post('add_past_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $start_date           = $request->variable('start_date', '');
            $end_date_str         = $request->variable('end_date', '');
            $notes                = $request->variable('notes', '', true);
            $rule_masturbation    = $request->variable('rule_masturbation', 0);
            $rule_ejaculation     = $request->variable('rule_ejaculation', 0);
            $rule_sleep_removal   = $request->variable('rule_sleep_removal', 0);
            $rule_public_removal  = $request->variable('rule_public_removal', 0);
            $rule_medical_removal = $request->variable('rule_medical_removal', 0);

            $start_ts = strtotime($start_date);
            $end_ts   = strtotime($end_date_str);

            if (!$start_ts || !$end_ts)
            {
                trigger_error($user->lang['CHASTITY_INVALID_DATE']);
            }

            if ($end_ts <= $start_ts)
            {
                trigger_error($user->lang['CHASTITY_INVALID_DATE_RANGE']);
            }

            if ($end_ts > time())
            {
                trigger_error($user->lang['CHASTITY_INVALID_DATE']);
            }

            $days_count = (int) floor(($end_ts - $start_ts) / 86400);

            $sql_ary = [
                'user_id'              => (int) $user->data['user_id'],
                'start_date'           => $start_ts,
                'end_date'             => $end_ts,
                'status'               => 'completed',
                'is_permanent'         => 0,
                'is_locktober'         => 0,
                'locktober_year'       => 0,
                'locktober_completed'  => 0,
                'days_count'           => $days_count,
                'notes'                => $notes,
                'rule_masturbation'    => (int) $rule_masturbation,
                'rule_ejaculation'     => (int) $rule_ejaculation,
                'rule_sleep_removal'   => (int) $rule_sleep_removal,
                'rule_public_removal'  => (int) $rule_public_removal,
                'rule_medical_removal' => (int) $rule_medical_removal,
                'created_time'         => time(),
                'updated_time'         => time(),
            ];

            $db->sql_query('INSERT INTO ' . $periods_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));

            // Recalcul complet des totaux
            $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);

            trigger_error($user->lang['CHASTITY_PAST_PERIOD_ADDED']);
        }

        $u_calendar = str_replace('&amp;mode=add_past', '&amp;mode=calendar', $this->u_action);

        $template->assign_vars([
            'U_ACTION'                       => $this->u_action,
            'U_CALENDAR'                     => $u_calendar,
            'TODAY_DATE'                     => date('Y-m-d'),
            'S_RULE_MASTURBATION_ENABLED'    => $config['chastity_rule_masturbation_enabled'] ?? 1,
            'S_RULE_EJACULATION_ENABLED'     => $config['chastity_rule_ejaculation_enabled'] ?? 1,
            'S_RULE_SLEEP_REMOVAL_ENABLED'   => $config['chastity_rule_sleep_removal_enabled'] ?? 1,
            'S_RULE_PUBLIC_REMOVAL_ENABLED'  => $config['chastity_rule_public_removal_enabled'] ?? 1,
            'S_RULE_MEDICAL_REMOVAL_ENABLED' => $config['chastity_rule_medical_removal_enabled'] ?? 1,
        ]);
    }

    private function statistics_mode($user, $template, $db, $periods_table)
    {
        $sql = 'SELECT * FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                ORDER BY start_date DESC';
        $result  = $db->sql_query($sql);
        $periods = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);

        $total_days    = 0;
        $total_periods = count($periods);
        $current_days  = 0;
        $status        = 'free';
        $s_locked      = false;
        $longest       = 0;
        $year_stats    = [];
        $current_year  = (int) date('Y');

        foreach ($periods as $period)
        {
            $year = (int) date('Y', (int) $period['start_date']);
            if (!isset($year_stats[$year]))
            {
                $year_stats[$year] = ['days' => 0, 'periods' => 0];
            }

            if ($period['status'] === 'active')
            {
                $status       = 'locked';
                $s_locked     = true;
                $days         = (int) floor((time() - (int) $period['start_date']) / 86400);
                $current_days = $days;
            }
            else
            {
                $days = (int) $period['days_count'];
            }

            $total_days                  += $days;
            $year_stats[$year]['days']   += $days;
            $year_stats[$year]['periods']++;
            $longest = max($longest, $days);
        }

        $average     = $total_periods > 0 ? round($total_days / $total_periods, 1) : 0;
        $month_stats = array_fill(1, 12, 0);

        foreach ($periods as $period)
        {
            if ((int) date('Y', (int) $period['start_date']) === $current_year)
            {
                $month = (int) date('m', (int) $period['start_date']);
                $days  = ($period['status'] === 'active')
                    ? (int) floor((time() - (int) $period['start_date']) / 86400)
                    : (int) $period['days_count'];
                $month_stats[$month] += $days;
            }
        }

        $template->assign_vars([
            'TOTAL_DAYS'        => $total_days,
            'TOTAL_PERIODS'     => $total_periods,
            'CURRENT_DAYS'      => $current_days,
            'CHASTITY_STATUS'   => $user->lang['CHASTITY_STATUS_' . strtoupper($status)],
            'S_CHASTITY_LOCKED' => $s_locked,
            'LONGEST_PERIOD'    => $longest,
            'AVERAGE_PERIOD'    => $average,
            'CURRENT_YEAR_DAYS' => isset($year_stats[$current_year]) ? $year_stats[$current_year]['days'] : 0,
        ]);

        krsort($year_stats);
        foreach ($year_stats as $year => $stats)
        {
            $template->assign_block_vars('year_stats', [
                'YEAR'    => $year,
                'DAYS'    => $stats['days'],
                'PERIODS' => $stats['periods'],
            ]);
        }

        // Utiliser $user->lang['datetime'] pour les vrais noms de mois traduits.
        // $user->lang['JANUARY'] retourne 'January' (anglais) même en FR —
        // les vraies traductions sont dans le tableau datetime du core phpBB.
        $datetime_months = [
            1  => 'January',  2  => 'February', 3  => 'March',
            4  => 'April',    5  => 'May',       6  => 'June',
            7  => 'July',     8  => 'August',    9  => 'September',
            10 => 'October',  11 => 'November',  12 => 'December',
        ];
        foreach ($month_stats as $month => $days)
        {
            $month_name = isset($user->lang['datetime'][$datetime_months[$month]])
                ? $user->lang['datetime'][$datetime_months[$month]]
                : $datetime_months[$month];

            $template->assign_block_vars('month_stats', [
                'MONTH' => $month_name,
                'DAYS'  => $days,
            ]);
        }
    }

    private function locktober_mode($user, $template, $request, $db, $periods_table, $auth, $config)
    {
        if (empty($config['chastity_locktober_enabled']))
        {
            trigger_error($user->lang['CHASTITY_LOCKTOBER_DISABLED']);
        }

        $current_year  = (int) ($config['chastity_locktober_year'] ?? date('Y'));
        $current_month = (int) date('m');
        $is_october    = ($current_month === 10);

        if ($request->is_set_post('start_locktober'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $sql = 'SELECT COUNT(*) as active_count FROM ' . $periods_table . '
                    WHERE user_id = ' . (int) $user->data['user_id'] . " AND status = 'active'";
            $result = $db->sql_query($sql);
            $active_count = (int) $db->sql_fetchfield('active_count');
            $db->sql_freeresult($result);

            if ($active_count > 0)
            {
                trigger_error($user->lang['CHASTITY_ALREADY_ACTIVE']);
            }

            $start_day  = $is_october ? (int) date('d') : 1;
            $start_date = mktime(0, 0, 0, 10, $start_day, $current_year);

            $sql_ary = [
                'user_id'              => (int) $user->data['user_id'],
                'start_date'           => $start_date,
                'end_date'             => 0,
                'status'               => 'active',
                'is_permanent'         => 0,
                'is_locktober'         => 1,
                'locktober_year'       => $current_year,
                'locktober_completed'  => 0,
                'days_count'           => 0,
                'notes'                => $user->lang['CHASTITY_LOCKTOBER_CHALLENGE'] . ' ' . $current_year,
                'rule_masturbation'    => 0,
                'rule_ejaculation'     => 0,
                'rule_sleep_removal'   => 0,
                'rule_public_removal'  => 0,
                'rule_medical_removal' => 1,
                'created_time'         => time(),
                'updated_time'         => time(),
            ];

            $db->sql_query('INSERT INTO ' . $periods_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
            $period_id = $db->sql_nextid();

            $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                SET chastity_status = 'locked', chastity_current_period = " . (int) $period_id . "
                WHERE user_id = " . (int) $user->data['user_id']);

            trigger_error($user->lang['CHASTITY_PERIOD_ADDED']);
        }

        $sql = 'SELECT * FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                  AND is_locktober = 1
                  AND locktober_year = ' . $current_year . "
                  AND status = 'active'";
        $result           = $db->sql_query($sql);
        $active_locktober = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $current_day          = 0;
        $has_active_locktober = false;

        if ($active_locktober)
        {
            $has_active_locktober = true;
            $current_day = (int) floor((time() - (int) $active_locktober['start_date']) / 86400) + 1;
        }

        $sql = 'SELECT * FROM ' . $periods_table . '
                WHERE user_id = ' . (int) $user->data['user_id'] . '
                  AND is_locktober = 1
                  AND locktober_completed = 1
                ORDER BY locktober_year DESC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('completed_locktober', [
                'YEAR' => (int) $row['locktober_year'],
                'DAYS' => (int) $row['days_count'],
            ]);
        }
        $db->sql_freeresult($result);

        if (!empty($config['chastity_locktober_leaderboard_enabled']))
        {
            $sql = 'SELECT u.username, u.user_colour, u.user_id, p.start_date
                    FROM ' . $periods_table . ' p
                    LEFT JOIN ' . $this->chastity_users_table . ' u ON u.user_id = p.user_id
                    WHERE p.is_locktober = 1
                      AND p.locktober_year = ' . $current_year . "
                      AND p.status = 'active'
                    ORDER BY p.start_date ASC
                    LIMIT 20";
            $result = $db->sql_query($sql);
            $rank   = 1;
            while ($row = $db->sql_fetchrow($result))
            {
                $template->assign_block_vars('leaderboard', [
                    'RANK'     => $rank,
                    'USERNAME' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                    'DAYS'     => (int) floor((time() - (int) $row['start_date']) / 86400) + 1,
                ]);
                $rank++;
            }
            $db->sql_freeresult($result);
        }

        $template->assign_vars([
            'LOCKTOBER_YEAR'                => $current_year,
            'IS_OCTOBER'                    => $is_october,
            'HAS_ACTIVE_LOCKTOBER'          => $has_active_locktober,
            'LOCKTOBER_CURRENT_DAY'         => $current_day,
            'LOCKTOBER_LEADERBOARD_ENABLED' => $config['chastity_locktober_leaderboard_enabled'] ?? 1,
            'U_ACTION'                      => $this->u_action,
        ]);
    }
}
