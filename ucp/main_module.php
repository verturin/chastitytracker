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

        // Garantir que l'utilisateur existe dans chastity_users pour tous les modes
        $this->ensure_chastity_user($user->data['user_id'], $user->data['username'], $user->data['user_colour'], $db);

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

            case 'yearview':
                $this->yearview_mode($user, $template, $request, $db, $periods_table);
            break;

            case 'chastprivacy':
                $prefs_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_user_prefs');
                $this->prefs_mode($user, $template, $request, $db, $prefs_table, $config);
            break;

            case 'add_past':
                $this->add_past_mode($user, $template, $request, $db, $periods_table, $auth, $config);
            break;

            case 'refresh':
                $cache_updater   = $phpbb_container->get('verturin.chastitytracker.cache_updater');
                $history_updater = $phpbb_container->get('verturin.chastitytracker.history_updater');
                $this->refresh_mode($user, $template, $request, $db, $auth, $phpbb_container, $cache_updater, $history_updater);
            break;
        }
    }

    /**
     * Formate une durée en secondes en chaîne lisible : Xj Yh Zmin
     */
	private function format_duration($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $days    = (int) floor($seconds / 86400);
        $hours   = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        $str = '';
        if ($days > 0)
        {
            $str .= $days . ' j';
        }
        if ($hours > 0 || ($days === 0 && $minutes > 0))
        {
            $str .= ($str ? ' ' : '') . $hours . ' h';
        }
        if ($minutes > 0)
        {
            $str .= ' ' . $minutes . ' min';
        }
        if ($str === '')
        {
            $str = '0 min';
        }
        return $str;
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

            $start_time      = $request->variable('start_time', '00:00');
            $start_timestamp = strtotime($start_date . ' ' . $start_time);
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

            $period_id    = $request->variable('period_id', 0);
            $end_date_str = $request->variable('end_date_custom', '');

            $sql = 'SELECT * FROM ' . $periods_table . '
                    WHERE period_id = ' . (int) $period_id . '
                      AND user_id = ' . (int) $user->data['user_id'] . " AND status = 'active'";
            $result = $db->sql_query($sql);
            $period = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($period)
            {
                // Date de fin : choisie par l'utilisateur ou maintenant
                 if (!empty($end_date_str))
                {
                    $end_time_str = $request->variable('end_time_custom', '');
                    $end_date = strtotime($end_date_str . (!empty($end_time_str) ? ' ' . $end_time_str : ' 23:59'));
                    if (!$end_date || $end_date > time())
                    {
                        $end_date = time();
                    }
                    if ($end_date < (int) $period['start_date'])
                    {
                        trigger_error($user->lang['CHASTITY_INVALID_DATE_RANGE']);
                    }
                }
                else
                {
                    $end_date = time();
                }

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

            // Durée précise en secondes pour affichage j/h/min
            if ($is_active)
            {
                $duration_seconds = time() - (int) $period['start_date'];
            }
            else if ((int) $period['end_date'] > 0 && (int) $period['start_date'] > 0)
            {
                $duration_seconds = (int) $period['end_date'] - (int) $period['start_date'];
            }
            else
            {
                $duration_seconds = (int) $period['days_count'] * 86400;
            }

            $template->assign_block_vars('periods', [
                'PERIOD_ID'            => $period['period_id'],
                'START_DATE'           => $user->format_date((int) $period['start_date'], 'd/m/Y H:i'),
                'END_DATE'             => ((int) $period['end_date'] > 0) ? $user->format_date((int) $period['end_date'], 'd/m/Y H:i') : '-',
                'STATUS'               => $user->lang['CHASTITY_STATUS_' . strtoupper($period['status'])],
                'DAYS_COUNT'           => $this->format_duration($duration_seconds),
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
            // Normaliser à midi pour éviter le décalage heure d'été/hiver
                $d = strtotime('12:00:00', $start);
                $end_noon = strtotime('12:00:00', $end);
                while ($d <= $end_noon) {
                    $locked_days[date('Y-m-d', $d)] = true;
                    $d = strtotime('+1 day', $d);
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
$datetime_months = [
    1 => 'January', 2 => 'February', 3 => 'March',
    4 => 'April',   5 => 'May',      6 => 'June',
    7 => 'July',    8 => 'August',   9 => 'September',
    10 => 'October', 11 => 'November', 12 => 'December',
];
$month_names = [];
foreach ($datetime_months as $num => $key) {
    $month_names[$num] = isset($user->lang['datetime'][$key])
        ? $user->lang['datetime'][$key]
        : $key;
}


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

            $start_time_past = $request->variable('start_time', '00:00');
            $end_time_past   = $request->variable('end_time', '23:59');
            $start_ts = strtotime($start_date . ' ' . $start_time_past);
            $end_ts   = strtotime($end_date_str . ' ' . $end_time_past);

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
			$start_year = (int) date('Y', (int) $period['start_date']);
			if (!isset($year_stats[$start_year]))
			{
				$year_stats[$start_year] = ['days' => 0, 'periods' => 0];
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
			$total_days += $days;
			$longest = max($longest, $days);

			// Répartir les jours ET les périodes par année — chevauchements gérés
			$p_start_ts  = (int) $period['start_date'];
			$p_end_ts    = ($period['status'] === 'active') ? time() : (int) $period['end_date'];
			$start_year  = (int) date('Y', $p_start_ts);
			$end_year    = (int) date('Y', $p_end_ts);
			for ($y = $start_year; $y <= $end_year; $y++)
			{
				if (!isset($year_stats[$y]))
				{
					$year_stats[$y] = ['days' => 0, 'periods' => 0];
				}
				$y_start = mktime(0, 0, 0, 1, 1, $y);
				$y_next  = mktime(0, 0, 0, 1, 1, $y + 1);
				$days_in_year = (int) floor(
					(min($p_end_ts, $y_next) - max($p_start_ts, $y_start)) / 86400
				);
				$year_stats[$y]['days'] += max(0, $days_in_year);
				$year_stats[$y]['periods']++;
			}
		}

        $average     = $total_periods > 0 ? round($total_days / $total_periods, 1) : 0;
        $month_stats = array_fill(1, 12, 0);

		foreach ($periods as $period)
		{
			$p_start_ts = (int) $period['start_date'];
			$p_end_ts   = ($period['status'] === 'active') ? time() : (int) $period['end_date'];
			for ($m = 1; $m <= 12; $m++)
			{
				$m_start = mktime(0, 0, 0, $m,     1, $current_year);
				$m_next  = mktime(0, 0, 0, $m + 1, 1, $current_year);
				if ($p_start_ts < $m_next && $p_end_ts > $m_start)
				{
					$days_in_month = (int) floor(
						(min($p_end_ts, $m_next) - max($p_start_ts, $m_start)) / 86400
					);
					$month_stats[$m] += max(0, $days_in_month);
				}
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
                    LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
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

	// Etape 7b
    private function prefs_mode($user, $template, $request, $db, $prefs_table, $config)
    {
        $user_id = (int) $user->data['user_id'];
        if ($request->is_set_post('generate_api_token')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $tok = bin2hex(random_bytes(32));
            $r = $db->sql_query('SELECT user_id FROM '.$prefs_table.' WHERE user_id='.$user_id);
            $ex = $db->sql_fetchrow($r); $db->sql_freeresult($r);
            $ad = ['api_enabled'=>1,'api_token'=>$tok,'updated_time'=>time()];
            if ($ex) { $db->sql_query('UPDATE '.$prefs_table.' SET '.$db->sql_build_array('UPDATE',$ad).' WHERE user_id='.$user_id); }
            else { $db->sql_query('INSERT INTO '.$prefs_table.' '.$db->sql_build_array('INSERT',array_merge(['user_id'=>$user_id],$ad))); }
            trigger_error($user->lang['CHASTITY_API_TOKEN_GENERATED']);
        }
        if ($request->is_set_post('revoke_api_token')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $db->sql_query('UPDATE '.$prefs_table." SET api_enabled=0,api_token='',updated_time=".time().' WHERE user_id='.$user_id);
            trigger_error($user->lang['CHASTITY_API_TOKEN_REVOKED']);
        }
        if ($request->is_set_post('save_prefs')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $pd = ['show_status'=>$request->variable('show_status',1),
                   'show_days'=>$request->variable('show_days',1),
                   'show_total_days'=>$request->variable('show_total_days',1),
                   'show_year_stats'=>$request->variable('show_year_stats',1),
                   'show_best_year'=>$request->variable('show_best_year',1),
                   'show_best_month'=>$request->variable('show_best_month',1),
                   'show_in_posts'=>$request->variable('show_in_posts',1),
                   'show_in_contact'=>$request->variable('show_in_contact',1),
                   'updated_time'=>time()];
            $r = $db->sql_query('SELECT user_id FROM '.$prefs_table.' WHERE user_id='.$user_id);
            $ex = $db->sql_fetchrow($r); $db->sql_freeresult($r);
            if ($ex) { $db->sql_query('UPDATE '.$prefs_table.' SET '.$db->sql_build_array('UPDATE',$pd).' WHERE user_id='.$user_id); }
            else { $db->sql_query('INSERT INTO '.$prefs_table.' '.$db->sql_build_array('INSERT',array_merge(['user_id'=>$user_id],$pd))); }
            trigger_error($user->lang['CHASTITY_PREFS_SAVED']);
        }
        $r = $db->sql_query('SELECT * FROM '.$prefs_table.' WHERE user_id='.$user_id);
        $prefs = $db->sql_fetchrow($r); $db->sql_freeresult($r);
        $def = (int)($config['chastity_prefs_default']??1);
        $template->assign_vars([
            'U_ACTION'=>$this->u_action,
            'SHOW_STATUS'     => $prefs?(bool)$prefs['show_status']:(bool)$def,
            'SHOW_DAYS'       => $prefs?(bool)$prefs['show_days']:(bool)$def,
            'SHOW_TOTAL_DAYS' => $prefs?(bool)$prefs['show_total_days']:(bool)$def,
            'SHOW_YEAR_STATS' => $prefs?(bool)$prefs['show_year_stats']:(bool)$def,
            'SHOW_BEST_YEAR'  => $prefs?(bool)$prefs['show_best_year']:(bool)$def,
            'SHOW_BEST_MONTH' => $prefs?(bool)$prefs['show_best_month']:(bool)$def,
            'SHOW_IN_POSTS'   => $prefs?(bool)$prefs['show_in_posts']:(bool)$def,
            'SHOW_IN_CONTACT' => $prefs?(bool)$prefs['show_in_contact']:(bool)$def,
            'API_ENABLED'     => $prefs?(bool)$prefs['api_enabled']:false,
            'API_TOKEN'       => ($prefs&&$prefs['api_enabled'])?$prefs['api_token']:'',
            'S_USER_ID'       => $user_id,
        ]);
    }

    private function refresh_mode($user, $template, $request, $db, $auth, $phpbb_container, $cache_updater, $history_updater)
    {
        if (!$auth->acl_get('u_chastity_refresh'))
        {
            trigger_error($user->lang['NOT_AUTHORISED']);
        }

        $user_id      = (int) $user->data['user_id'];
        $refresh_done = false;

        if ($request->is_set_post('refresh_cache'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cache_updater->update_user_cache($user_id);
            $this->recalc_user_totals($db, $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_periods'), $user_id);
            $refresh_done = true;
        }

        if ($request->is_set_post('refresh_history'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $history_updater->update_user_history($user_id);
            $refresh_done = true;
        }

        $template->assign_vars([
            'U_ACTION'       => $this->u_action,
            'S_REFRESH_DONE' => $refresh_done,
        ]);
    }

    private function yearview_mode($user, $template, $request, $db, $periods_table)
    {
        $user_id      = (int) $user->data['user_id'];
        $view_year    = $request->variable('year', (int) date('Y'));
        $current_year = (int) date('Y');

        // Récupérer TOUTES les périodes de l'année
        $year_start = mktime(0,  0,  0,  1,  1,  $view_year);
        $year_end   = mktime(23, 59, 59, 12, 31, $view_year);

        $sql = 'SELECT start_date, end_date, status FROM ' . $periods_table
             . ' WHERE user_id = ' . $user_id
             . ' AND ((start_date <= ' . $year_end
             . '  AND (end_date >= ' . $year_start . " OR status = 'active'))"
             . '  OR (start_date >= ' . $year_start . ' AND start_date <= ' . $year_end . '))';
        $result  = $db->sql_query($sql);
        $periods = $db->sql_fetchrowset($result);
        $db->sql_freeresult($result);

        // Tableau global des jours verrouillés + total en secondes — filtrés sur l'année
        $locked_days        = [];
        $total_year_seconds = 0;
        foreach ($periods as $period) {
            $ps = (int) $period['start_date'];
            $pe = ($period['status'] === 'active') ? time() : (int) $period['end_date'];
            // Borner aux limites de l'année (gère les périodes à cheval sur 2 ans)
            $ps = max($ps, $year_start);
            $pe = min($pe, $year_end);
            if ($pe > $ps) {
                $total_year_seconds += ($pe - $ps);
            }
            // Marquer les jours verrouillés pour la grille (boucle journalière)
            // Normaliser à midi pour éviter le décalage heure d'été/hiver
            $d = strtotime('12:00:00', $ps);
            $pe_noon = strtotime('12:00:00', $pe);
            while ($d <= $pe_noon) {
                $day_str = date('Y-m-d', $d);
                if (substr($day_str, 0, 4) === (string) $view_year) {
                    $locked_days[$day_str] = true;
                }
                $d = strtotime('+1 day', $d);
            }
        }

        $total_locked_year  = (int) floor($total_year_seconds / 86400);
        $total_year_hours   = (int) floor(($total_year_seconds % 86400) / 3600);
        $total_year_minutes = (int) floor(($total_year_seconds % 3600) / 60);
        $today_str  = date('Y-m-d');
        $month_names = [
            1 => $user->lang['datetime']['January'],   2 => $user->lang['datetime']['February'],
            3 => $user->lang['datetime']['March'],     4 => $user->lang['datetime']['April'],
            5 => $user->lang['datetime']['May'],       6 => $user->lang['datetime']['June'],
            7 => $user->lang['datetime']['July'],      8 => $user->lang['datetime']['August'],
            9 => $user->lang['datetime']['September'], 10 => $user->lang['datetime']['October'],
            11 => $user->lang['datetime']['November'], 12 => $user->lang['datetime']['December'],
        ];

        // Générer les 12 mois
        for ($month = 1; $month <= 12; $month++) {
            $m_first     = mktime(0, 0, 0, $month, 1, $view_year);
            $days_in_m   = (int) date('t', $m_first);
            $first_dow   = (int) date('N', $m_first); // 1=Lun
            $locked_in_m = 0;

            // Compter les jours verrouillés du mois
            for ($d = 1; $d <= $days_in_m; $d++) {
                $ds = sprintf('%04d-%02d-%02d', $view_year, $month, $d);
                if (isset($locked_days[$ds])) $locked_in_m++;
            }

            // 1. Bloc parent EN PREMIER (obligatoire en phpBB pour les block_vars imbriqués)
            $template->assign_block_vars('yearly_months', [
                'MONTH_NAME'   => $month_names[$month],
                'MONTH_NUM'    => $month,
                'LOCKED_COUNT' => $locked_in_m,
                'IS_CURRENT'   => ($month === (int) date('n') && $view_year === $current_year),
            ]);

            // 2. Cellules enfants APRÈS le parent
            // Cellules vides avant le 1er
            for ($e = 1; $e < $first_dow; $e++) {
                $template->assign_block_vars('yearly_months.month_days', [
                    'DAY' => '', 'LOCKED' => false, 'TODAY' => false, 'EMPTY' => true,
                ]);
            }
            // Jours du mois
            for ($d = 1; $d <= $days_in_m; $d++) {
                $ds     = sprintf('%04d-%02d-%02d', $view_year, $month, $d);
                $locked = isset($locked_days[$ds]);
                $template->assign_block_vars('yearly_months.month_days', [
                    'DAY'    => $d,
                    'LOCKED' => $locked,
                    'TODAY'  => ($ds === $today_str),
                    'EMPTY'  => false,
                ]);
            }
        }

        $template->assign_vars([
            'VIEW_YEAR'           => $view_year,
            'PREV_YEAR'           => $view_year - 1,
            'NEXT_YEAR'           => $view_year + 1,
            'TOTAL_LOCKED_YEAR'   => $total_locked_year,
            'TOTAL_YEAR_HOURS'    => $total_year_hours,
            'TOTAL_YEAR_MINUTES'  => $total_year_minutes,
            'S_IS_CURRENT_YEAR'   => ($view_year === $current_year),
            'U_ACTION'            => $this->u_action,
        ]);
    }

}
