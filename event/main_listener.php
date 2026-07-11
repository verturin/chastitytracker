<?php
namespace verturin\chastitytracker\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    protected $config;
    protected $template;
    protected $user;
    protected $db;
    protected $auth;
    protected $periods_table;
    protected $active_days_table;
    protected $lk_rewards_table;
    protected $lk_milestones_table;
    protected $special_days_table;
    protected $perfect_table;
    protected $earned_table;
    protected $streak_milestones_table;
    protected $total_milestones_table;
    protected $contracts_table;
    protected $chastity_users_table;
    protected $cache_table;
	protected $history_table;	
	protected $prefs_table;	
	protected $keyholders_table;
	protected $period_calculator;
    protected $locked_users_cache = null;
    protected $active_kh_cache = null; // user_ids des KH qui ont au moins un sub verrouillé
    protected $subs_with_active_kh_cache = null; // user_ids des subs qui ont un KH actif
    
	public function __construct($config, $template, $user, $db, $auth, $periods_table, $chastity_users_table, $cache_table, $history_table, $prefs_table = '', $keyholders_table = '', $period_calculator = null, $active_days_table = '', $lk_rewards_table = '', $lk_milestones_table = '', $special_days_table = '', $perfect_table = '', $earned_table = '', $streak_milestones_table = '', $total_milestones_table = '', $contracts_table = '')	
	
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->auth = $auth;
        $this->periods_table = $periods_table;
        $this->chastity_users_table = $chastity_users_table;
        $this->cache_table = $cache_table;
		$this->history_table = $history_table;
		$this->prefs_table = $prefs_table;
		$this->keyholders_table = $keyholders_table;
		$this->period_calculator = $period_calculator;
		$this->active_days_table = $active_days_table;
		$this->lk_rewards_table = $lk_rewards_table;
		$this->lk_milestones_table = $lk_milestones_table;
		$this->special_days_table = $special_days_table;
		$this->perfect_table = $perfect_table;
		$this->earned_table = $earned_table;
		$this->streak_milestones_table = $streak_milestones_table;
		$this->total_milestones_table = $total_milestones_table;
		$this->contracts_table = $contracts_table;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'                => 'load_language',
            'core.memberlist_view_profile'   => 'display_chastity_status_profile',
            'core.permissions'               => 'add_permissions',
            'core.viewtopic_modify_post_row' => 'set_post_row_var',
            'core.page_header'               => [
                ['display_nav_link'],
                ['display_leaderboard'],
                ['track_active_day'],
            ],
            'core.modify_username_string'     => 'add_lock_badge',
        ];
    }
    
    /**
     * Enregistre une fois par jour la présence de l'utilisateur connecté
     * (table chastity_active_days), pour l'anneau "connexions" des récompenses.
     * Une seule écriture par membre et par jour ; les appels suivants sont
     * des no-op (doublon de clé primaire user_id+day_date, ignoré).
     */
    public function track_active_day($event)
    {
        if (empty($this->active_days_table)) {
            return;
        }
        // Invités ignorés
        if (empty($this->user->data['user_id']) || (int) $this->user->data['user_id'] == ANONYMOUS) {
            return;
        }
        // Bots ignorés
        if (isset($this->user->data['user_type']) && (int) $this->user->data['user_type'] == USER_IGNORE) {
            return;
        }

        $user_id  = (int) $this->user->data['user_id'];
        $day_date = (int) date('Ymd');

        try {
            $sql = 'INSERT INTO ' . $this->active_days_table . ' ' . $this->db->sql_build_array('INSERT', [
                'user_id'  => $user_id,
                'day_date' => $day_date,
            ]);
            // Le doublon (déjà connecté aujourd'hui) lève une erreur SQL ignorée.
            $this->db->sql_return_on_error(true);
            $this->db->sql_query($sql);
            $this->db->sql_return_on_error(false);
        } catch (\Throwable $e) {}
    }

    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        
        $lang_set_ext[] = [
            'ext_name' => 'verturin/chastitytracker',
            'lang_set' => 'common',
        ];
        
        $event['lang_set_ext'] = $lang_set_ext;

        // v3.4.3 — Remettre à zéro le flag inactivity_warned si l'utilisateur se connecte
        $user_id = (int) $this->user->data['user_id'];
        if ($user_id > 1)
        {
            $this->db->sql_query(
                'UPDATE ' . $this->chastity_users_table
                . ' SET inactivity_warned = 0'
                . ' WHERE user_id = ' . $user_id
                . ' AND inactivity_warned = 1'
            );
        }
    }
    
    public function display_chastity_status_profile($event)
    {
        if (empty($this->config['chastity_enable']) || empty($this->config['chastity_profile_display']))
        {
            return;
        }
        
        if (!$this->auth->acl_get('u_chastity_view'))
        {
            return;
        }
        
        $member = $event['member'];
        
        if (empty($member['user_id']))
        {
            return;
        }
        
        $user_id = (int) $member['user_id'];
        
        $sql = 'SELECT cu.chastity_status, cu.chastity_current_period, cu.chastity_total_days, p.start_date
                FROM ' . $this->chastity_users_table . ' cu
                LEFT JOIN ' . $this->periods_table . ' p
                    ON p.period_id = cu.chastity_current_period
                WHERE cu.user_id = ' . $user_id;

        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return;
        }

        $locked = ($row['chastity_status'] === 'locked');
        $current_days = 0;
        $days_since_end = 0;
        $current_secs = 0;

        if ($locked && $row['start_date'])
        {
            $current_secs = max(0, time() - (int) $row['start_date']);
            $current_days = (int) floor($current_secs / 86400);
        }
        else
        {
            // Jours depuis la fin de la dernière période
            $sql_last = 'SELECT end_date FROM ' . $this->periods_table . "
                        WHERE user_id = $user_id AND status = 'completed' AND end_date > 0
                        ORDER BY end_date DESC LIMIT 1";
            $result_last = $this->db->sql_query($sql_last);
            $last = $this->db->sql_fetchrow($result_last);
            $this->db->sql_freeresult($result_last);
            if ($last)
            {
                $days_since_end = (int) floor((time() - (int) $last['end_date']) / 86400);
            }
        }

        // Jours de l'année en cours
        $current_year = (int) date('Y');
        $year_start   = mktime(0, 0, 0, 1, 1, $current_year);
        $year_end     = mktime(23, 59, 59, 12, 31, $current_year);

        // Récupérer toutes les périodes terminées qui touchent l'année courante
        $sql_year = 'SELECT start_date, end_date FROM ' . $this->periods_table . "
                    WHERE user_id = $user_id AND status = 'completed' AND end_date >= $year_start";
        $result_year  = $this->db->sql_query($sql_year);
        $periods_year = $this->db->sql_fetchrowset($result_year);
        $this->db->sql_freeresult($result_year);

        $year_seconds = 0;
        foreach ($periods_year as $py) {
            $ps = max((int) $py['start_date'], $year_start);
            $pe = min((int) $py['end_date'],   $year_end);
            if ($pe > $ps) {
                $year_seconds += ($pe - $ps);
            }
        }
        // Ajouter les secondes de la période active dans l'année
        if ($locked && $row['start_date'])
        {
            $active_start   = max((int) $row['start_date'], $year_start);
            $active_end     = min(time(), $year_end);
            if ($active_end > $active_start) {
                $year_seconds += ($active_end - $active_start);
            }
        }
        $year_days = (int) floor($year_seconds / 86400);

        // Meilleure année — historique cron + année courante en temps réel
        $sql_best_year = 'SELECT year, total_days FROM ' . $this->history_table . "
                         WHERE user_id = $user_id ORDER BY total_days DESC LIMIT 1";
        $result_best_year = $this->db->sql_query($sql_best_year);
        $best_year_row = $this->db->sql_fetchrow($result_best_year);
        $this->db->sql_freeresult($result_best_year);
        $best_year_days = $best_year_row ? (int) $best_year_row['total_days'] : 0;
        $best_year      = $best_year_row ? (int) $best_year_row['year'] : 0;
        // Comparer avec l'année courante calculée en temps réel (inclut période active)
        if ($year_days > $best_year_days)
        {
            $best_year_days = $year_days;
            $best_year      = $current_year;
        }

        // Préférences de confidentialité
        $prefs = null;
        if ($this->prefs_table) {
            $sql_priv = 'SELECT * FROM ' . $this->prefs_table . ' WHERE user_id = ' . $user_id;
            $res_priv = $this->db->sql_query($sql_priv);
            $prefs    = $this->db->sql_fetchrow($res_priv);
            $this->db->sql_freeresult($res_priv);
        }
        $d_show          = (int) ($this->config['chastity_prefs_default'] ?? 1);
        $show_status     = $prefs ? (bool)$prefs['show_status']     : (bool)$d_show;
        $show_days       = $prefs ? (bool)$prefs['show_days']       : (bool)$d_show;
        $show_total_days = $prefs ? (bool)$prefs['show_total_days'] : (bool)$d_show;
        $show_year_stats = $prefs ? (bool)$prefs['show_year_stats'] : (bool)$d_show;
        $show_best_year  = $prefs ? (bool)$prefs['show_best_year']  : (bool)$d_show;
        $show_in_posts   = $prefs ? (bool)$prefs['show_in_posts']   : (bool)$d_show;
        $show_in_contact = $prefs ? (bool)$prefs['show_in_contact'] : (bool)$d_show;
        // Préférence pour les tooltips détaillés sur le profil (par défaut activé)
        $show_calendar_details = ($prefs && isset($prefs['show_calendar_details'])) ? (bool)$prefs['show_calendar_details'] : (bool)$d_show;


        // CageExits et activités pour le mini-calendrier profil + détails pour tooltip
        $profile_cageexit_days = [];
        $profile_activity_days = [];
        $profile_day_tooltips  = []; // date => array de lignes
        $ce_table  = str_replace('chastity_periods', 'chastity_cageexits',  $this->periods_table);
        $act_table = str_replace('chastity_periods', 'chastity_activities', $this->periods_table);
        $ce_reasons_table  = str_replace('chastity_periods', 'chastity_cageexit_reasons',  $this->periods_table);
        $act_reasons_table = str_replace('chastity_periods', 'chastity_activity_reasons', $this->periods_table);
        $prof_start = mktime(0, 0, 0, (int)date('n') - 3, 1, (int)date('Y'));
        if ((int)date('n') - 3 <= 0) { $prof_start = mktime(0, 0, 0, (int)date('n') + 9, 1, (int)date('Y') - 1); }
        $prof_end = time();

        // Charger libellés motifs sortie
        $ce_reason_labels = [];
        try {
            $rrr = $this->db->sql_query('SELECT reason_id, label FROM ' . $ce_reasons_table);
            while ($r = $this->db->sql_fetchrow($rrr)) { $ce_reason_labels[(int)$r['reason_id']] = $r['label']; }
            $this->db->sql_freeresult($rrr);
        } catch (\Exception $e) {}

        // Charger libellés motifs activité
        $act_reason_labels = [];
        try {
            $rrr = $this->db->sql_query('SELECT reason_id, label FROM ' . $act_reasons_table);
            while ($r = $this->db->sql_fetchrow($rrr)) { $act_reason_labels[(int)$r['reason_id']] = $r['label']; }
            $this->db->sql_freeresult($rrr);
        } catch (\Exception $e) {}

        $res_ce = $this->db->sql_query('SELECT cageexit_date, duration_min, reason_id, notes FROM ' . $ce_table . ' WHERE user_id=' . $user_id . ' AND cageexit_date>=' . $prof_start . ' AND cageexit_date<=' . $prof_end . ' ORDER BY cageexit_date ASC');
        while ($r = $this->db->sql_fetchrow($res_ce)) {
            $date = date('Y-m-d', (int)$r['cageexit_date']);
            $profile_cageexit_days[$date] = true;
            $duration = (int) $r['duration_min'];
            $reason = isset($ce_reason_labels[(int)$r['reason_id']]) ? $ce_reason_labels[(int)$r['reason_id']] : '';
            $hours = floor($duration / 60); $mins = $duration % 60;
            $dur_text = $duration > 0 ? ($hours > 0 ? ($hours . 'h' . ($mins > 0 ? sprintf('%02d', $mins) : '')) : ($mins . ' min')) : '';
            $line = '🚪 ' . (isset($this->user->lang['CHASTITY_CE_TOOLTIP']) ? $this->user->lang['CHASTITY_CE_TOOLTIP'] : 'Sortie');
            if ($reason !== '')   { $line .= ' : ' . $reason; }
            if ($dur_text !== '') { $line .= ' (' . $dur_text . ')'; }
            if (!empty($r['notes'])) {
                $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                $line .= ' — ' . $note;
            }
            if (!isset($profile_day_tooltips[$date])) { $profile_day_tooltips[$date] = []; }
            $profile_day_tooltips[$date][] = $line;
        }
        $this->db->sql_freeresult($res_ce);

        $res_act = $this->db->sql_query('SELECT activity_date, reason_id, intensity, notes FROM ' . $act_table . ' WHERE user_id=' . $user_id . ' AND activity_date>=' . $prof_start . ' AND activity_date<=' . $prof_end . ' ORDER BY activity_date ASC');
        while ($r = $this->db->sql_fetchrow($res_act)) {
            $date = date('Y-m-d', (int)$r['activity_date']);
            $profile_activity_days[$date] = true;
            $reason = isset($act_reason_labels[(int)$r['reason_id']]) ? $act_reason_labels[(int)$r['reason_id']] : '';
            $intensity = (string) $r['intensity'];
            $intensity_lang_key = 'CHASTITY_INTENSITY_' . strtoupper($intensity);
            $intensity_label = isset($this->user->lang[$intensity_lang_key]) ? $this->user->lang[$intensity_lang_key] : $intensity;
            $line = '🔥 ' . (isset($this->user->lang['CHASTITY_ACT_TOOLTIP']) ? $this->user->lang['CHASTITY_ACT_TOOLTIP'] : 'Activité');
            if ($reason !== '')   { $line .= ' : ' . $reason; }
            if ($intensity !== '' && $intensity_label !== '') { $line .= ' [' . $intensity_label . ']'; }
            if (!empty($r['notes'])) {
                $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                $line .= ' — ' . $note;
            }
            if (!isset($profile_day_tooltips[$date])) { $profile_day_tooltips[$date] = []; }
            $profile_day_tooltips[$date][] = $line;
        }
        $this->db->sql_freeresult($res_act);

        // Mini-calendrier : 3 derniers mois (M-2, M-1, M courant)
        for ($offset = 3; $offset >= 0; $offset--) {
            $m = (int) date('n') - $offset;
            $y = (int) date('Y');
            if ($m <= 0) { $m += 12; $y--; }

            $m_first = mktime(0, 0, 0, $m, 1, $y);
            $m_last  = mktime(23, 59, 59, $m, (int) date('t', $m_first), $y);

            // Périodes qui touchent ce mois
            $sql_cal = 'SELECT start_date, end_date, status FROM ' . $this->periods_table
                     . ' WHERE user_id = ' . $user_id
                     . ' AND ((start_date <= ' . $m_last
                     . '  AND (end_date >= ' . $m_first . " OR status = 'active'))"
                     . '  OR (start_date >= ' . $m_first . ' AND start_date <= ' . $m_last . '))';
            $res_cal     = $this->db->sql_query($sql_cal);
            $periods_cal = $this->db->sql_fetchrowset($res_cal);
            $this->db->sql_freeresult($res_cal);

            // Jours verrouillés du mois
            $locked_cal = [];
            foreach ($periods_cal as $pc) {
                $ps = (int) $pc['start_date'];
                $pe = ($pc['status'] === 'active') ? time() : (int) $pc['end_date'];
                // Normaliser à midi pour éviter le décalage heure d'été/hiver
                $d = strtotime('12:00:00', $ps);
                $pe_noon = strtotime('12:00:00', $pe);
                while ($d <= $pe_noon) {
                    $locked_cal[date('Y-m-d', $d)] = true;
                    $d = strtotime('+1 day', $d);
                }
            }

            $days_in_month = (int) date('t', $m_first);
            $first_dow     = (int) date('N', $m_first); // 1=Lun, 7=Dim
            $today_str     = date('Y-m-d');

            // Cellules vides avant le 1er
            for ($e = 1; $e < $first_dow; $e++) {
                $this->template->assign_block_vars('profile_cal', [
                    'MONTH' => $m, 'DAY' => '', 'LOCKED' => false, 'TODAY' => false, 'EMPTY' => true,
                ]);
            }
            // Jours du mois
            for ($d = 1; $d <= $days_in_month; $d++) {
                $date_str = sprintf('%04d-%02d-%02d', $y, $m, $d);
                $tooltip = '';
                // N'envoyer le tooltip que si l'utilisateur a autorisé l'affichage détaillé
                if ($show_calendar_details && isset($profile_day_tooltips[$date_str])) {
                    $tooltip = implode("\n", $profile_day_tooltips[$date_str]);
                }
                $this->template->assign_block_vars('profile_cal', [
                    'MONTH'    => $m,
                    'DAY'      => $d,
                    'LOCKED'   => isset($locked_cal[$date_str]),
                    'TODAY'    => ($date_str === $today_str),
                    'EMPTY'    => false,
                    'CAGEEXIT' => isset($profile_cageexit_days[$date_str]),
                    'ACTIVITY' => isset($profile_activity_days[$date_str]),
                    'TOOLTIP'  => $tooltip,
                ]);
            }

            // Bloc mois pour le template
            $this->template->assign_block_vars('profile_months', [
                'MONTH_NAME' => $this->user->lang['datetime'][date('F', $m_first)],
                'MONTH_NUM'  => $m,
                'MONTH_YEAR' => $y,
            ]);
        }


        // Sorties + activités de la période en cours (si verrouillé)
        $current_period_exits = 0;
        $current_period_activities = 0;
        if ($locked && !empty($row['chastity_current_period']))
        {
            $active_period_id = (int) $row['chastity_current_period'];
            // Tables construites par convention de préfixe
            $cageexits_table  = str_replace('chastity_periods', 'chastity_cageexits',  $this->periods_table);
            $activities_table = str_replace('chastity_periods', 'chastity_activities', $this->periods_table);

            $check = $this->db->sql_query("SHOW TABLES LIKE '" . $this->db->sql_escape($cageexits_table) . "'");
            if ($this->db->sql_fetchrow($check)) {
                $this->db->sql_freeresult($check);
                $res = $this->db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $cageexits_table . '
                    WHERE user_id = ' . $user_id . ' AND period_id = ' . $active_period_id);
                $current_period_exits = (int) $this->db->sql_fetchfield('cnt');
                $this->db->sql_freeresult($res);
            } else { $this->db->sql_freeresult($check); }

            $check = $this->db->sql_query("SHOW TABLES LIKE '" . $this->db->sql_escape($activities_table) . "'");
            if ($this->db->sql_fetchrow($check)) {
                $this->db->sql_freeresult($check);
                $res = $this->db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $activities_table . '
                    WHERE user_id = ' . $user_id . ' AND period_id = ' . $active_period_id);
                $current_period_activities = (int) $this->db->sql_fetchfield('cnt');
                $this->db->sql_freeresult($res);
            } else { $this->db->sql_freeresult($check); }
        }

		// Total de jours en chasteté recalculé en TEMPS RÉEL, en sommant les
		// SECONDES RÉELLES des périodes complétées (pas les days_count déjà
		// arrondis individuellement, qui perdraient les périodes de moins de
		// 24h) + jours de la période active. Formule alignée avec
		// recalc_user_totals() de l'UCP et get_total_caged_days() du service.
		$live_total = 0;
		$res_tt = $this->db->sql_query('SELECT SUM(end_date - start_date) AS total_seconds FROM ' . $this->periods_table . "
				WHERE user_id = " . (int) $user_id . " AND status = 'completed' AND end_date > start_date");
		$live_total_seconds = (int) $this->db->sql_fetchfield('total_seconds');
		$this->db->sql_freeresult($res_tt);
		$live_total = (int) floor($live_total_seconds / 86400);
		$res_tt = $this->db->sql_query('SELECT start_date FROM ' . $this->periods_table . "
				WHERE user_id = " . (int) $user_id . " AND status = 'active'
				ORDER BY start_date DESC LIMIT 1");
		$act_tt = $this->db->sql_fetchrow($res_tt);
		$this->db->sql_freeresult($res_tt);
		if ($act_tt && (int) $act_tt['start_date'] > 0) {
			$live_total += (int) floor((time() - (int) $act_tt['start_date']) / 86400);
		}

		// Contrat de chasteté actif (valide même si le membre est libre)
		$has_active_contract = false;
		if (!empty($this->contracts_table)) {
			try {
				$c_sql = 'SELECT 1 FROM ' . $this->contracts_table . "
						  WHERE encage_user_id = " . (int) $user_id . " AND status = 'active' LIMIT 1";
				$c_res = $this->db->sql_query($c_sql);
				$has_active_contract = (bool) $this->db->sql_fetchrow($c_res);
				$this->db->sql_freeresult($c_res);
			} catch (\Throwable $e) {}
		}

		$this->template->assign_vars([
            'CHASTITY_STATUS'          => $this->user->lang[$locked ? 'CHASTITY_STATUS_LOCKED' : 'CHASTITY_STATUS_FREE'],
            'CHASTITY_CURRENT_DAYS'    => $current_days,
            'CHASTITY_SINCE_TEXT'      => ($this->period_calculator !== null)
                ? ($locked ? $this->period_calculator->format_duration($current_secs > 0 ? $current_secs : 1) : $this->period_calculator->format_duration($current_days * 86400))
                : (($current_secs > 0 && $current_secs < 86400)
                    ? ((int) floor($current_secs / 3600)) . 'h' . sprintf('%02d', (int) floor(($current_secs % 3600) / 60))
                    : ($current_days . ' ' . ((int) $current_days === 1 ? (isset($this->user->lang['CHASTITY_DAY']) ? $this->user->lang['CHASTITY_DAY'] : 'jour') : (isset($this->user->lang['CHASTITY_DAYS']) ? $this->user->lang['CHASTITY_DAYS'] : 'jours')))),
            'CHASTITY_TOTAL_DAYS'      => $live_total,
            'CHASTITY_DAYS_SINCE_END'  => $days_since_end,
            'CHASTITY_FREE_SINCE_TEXT' => $days_since_end . ' ' . ((int) $days_since_end === 1
                ? (isset($this->user->lang['CHASTITY_DAY']) ? $this->user->lang['CHASTITY_DAY'] : 'jour')
                : (isset($this->user->lang['CHASTITY_DAYS']) ? $this->user->lang['CHASTITY_DAYS'] : 'jours')),
            'S_CHASTITY_LOCKED'        => $locked,
            'S_DISPLAY_CHASTITY'       => true,
            'S_HAS_ACTIVE_CONTRACT'    => $has_active_contract,
            'CHASTITY_LOCKED_SINCE'    => ($locked && $row['start_date'])
                ? $this->user->format_date((int) $row['start_date'], 'd/m/Y') : '',
            'CHASTITY_YEAR_DAYS'       => $year_days,
            'CHASTITY_YEAR_HOURS'      => (int) floor(($year_seconds % 86400) / 3600),
            'CHASTITY_YEAR_MINUTES'    => (int) floor(($year_seconds % 3600) / 60),
            'CHASTITY_CURRENT_YEAR'    => $current_year,
            'CHASTITY_BEST_YEAR_DAYS'  => $best_year_days,
            'CHASTITY_BEST_YEAR'       => $best_year,
            'PROFILE_CAL_YEAR'  	   => (int) date('Y'),
            'COLOR_CAGEEXIT'           => $this->config['chastity_color_cageexit'] ?? 'FFF3CD',
            'COLOR_ACTIVITY'           => $this->config['chastity_color_activity'] ?? 'EDE0F7',
            'COLOR_MIXED'              => $this->config['chastity_color_mixed'] ?? 'F5E6D3',
            'S_SHOW_STATUS'            => $show_status,
            'S_SHOW_DAYS'              => $show_days,
            'S_SHOW_TOTAL_DAYS'        => $show_total_days,
            'S_SHOW_YEAR_STATS'        => $show_year_stats,
            'S_SHOW_BEST_YEAR'         => $show_best_year,
            'S_SHOW_IN_POSTS'          => $show_in_posts,
            'S_SHOW_IN_CONTACT'        => $show_in_contact,
            'CHASTITY_CURRENT_PERIOD_EXITS'      => $current_period_exits,
            'CHASTITY_CURRENT_PERIOD_ACTIVITIES' => $current_period_activities,
        ]);

        // ─── Mini-anneaux de récompense sur le profil ────────────────
        if (!empty($this->config['chastity_rewards_enabled']) && !empty($this->active_days_table)) {
            try {
                $calc = new \verturin\chastitytracker\service\rewards_calculator(
                    $this->db, $this->config, $this->periods_table, $this->active_days_table, $this->lk_rewards_table, $this->lk_milestones_table, $this->special_days_table, $this->earned_table
                );
                $calc->set_milestone_tables($this->streak_milestones_table, $this->total_milestones_table);
                // Synchroniser les badges acquis (fige les années passées) avant lecture
                try { $calc->sync_earned_badges($user_id, $this->keyholders_table); } catch (\Throwable $e) {}
                $all_badges = $calc->get_all_badges($user_id, $this->keyholders_table, true);
                $rings = $calc->get_rings($user_id);
                $colors = ['cage' => '#ff2d55', 'posts' => '#a8e000', 'logins' => '#00b0ff'];
                $period_lang = ['day' => 'CHASTITY_RING_PERIOD_DAY', 'month' => 'CHASTITY_RING_PERIOD_MONTH', 'year' => 'CHASTITY_RING_PERIOD_YEAR'];
                foreach (['day', 'month', 'year'] as $period) {
                    $this->template->assign_block_vars('chastity_profile_ring', [
                        'PERIOD_NAME' => $this->user->lang($period_lang[$period]),
                        'CAGE_PCT'    => $rings[$period]['cage']['pct'],
                        'POSTS_PCT'   => $rings[$period]['posts']['pct'],
                        'LOGINS_PCT'  => $rings[$period]['logins']['pct'],
                        'CAGE_VAL'    => $rings[$period]['cage']['value'],
                        'CAGE_GOAL'   => $rings[$period]['cage']['goal'],
                        'POSTS_VAL'   => $rings[$period]['posts']['value'],
                        'POSTS_GOAL'  => $rings[$period]['posts']['goal'],
                        'LOGINS_VAL'  => $rings[$period]['logins']['value'],
                        'LOGINS_GOAL' => $rings[$period]['logins']['goal'],
                        'CAGE_COLOR'  => $colors['cage'],
                        'POSTS_COLOR' => $colors['posts'],
                        'LOGINS_COLOR'=> $colors['logins'],
                    ]);
                }
                $this->template->assign_var('S_CHASTITY_PROFILE_RINGS', true);

                // Badges spéciaux Locktober (figés + année courante)
                $lk_badges = $all_badges['locktober'];
                foreach ($lk_badges as $b) {
                    $this->template->assign_block_vars('chastity_profile_badge', [
                        'YEAR'        => $b['year'],
                        'SUCCESS'     => ($b['level'] === 'success'),
                        'REWARD_LABEL'=> $b['reward_label'] ?? '',
                        'REWARD_IMAGE'=> $b['reward_image'] ?? '',
                    ]);
                }
                if (!empty($lk_badges)) {
                    $this->template->assign_var('S_CHASTITY_PROFILE_BADGES', true);
                }

                // Badge de palier de fidélité (plus haut atteint)
                $milestone = $calc->get_milestone_badge($user_id);
                if ($milestone) {
                    $this->template->assign_vars([
                        'S_CHASTITY_PROFILE_MILESTONE'     => true,
                        'CHASTITY_PROFILE_MILESTONE_LABEL' => $milestone['label'],
                        'CHASTITY_PROFILE_MILESTONE_IMAGE' => $milestone['image'],
                        'CHASTITY_PROFILE_MILESTONE_COUNT' => $milestone['count'],
                    ]);
                }

                // Badges journée spéciale (par année)
                $sdays = $all_badges['sday'];
                foreach ($sdays as $sd) {
                    $this->template->assign_block_vars('chastity_profile_sday', [
                        'DATE'  => $sd['date'],
                        'YEAR'  => $sd['year'],
                        'LABEL' => $sd['label'],
                        'IMAGE' => $sd['image'],
                    ]);
                }
                if (!empty($sdays)) {
                    $this->template->assign_var('S_CHASTITY_PROFILE_SDAYS', true);
                }

                // Badges anniversaire (membre + keyholder)
                $bdays = $all_badges['birthday'];
                foreach ($bdays as $bd) {
                    $this->template->assign_block_vars('chastity_profile_birthday', [
                        'TYPE'  => $bd['type'],
                        'DATE'  => $bd['date'],
                        'YEAR'  => $bd['year'],
                        'LABEL' => $bd['label'],
                        'IMAGE' => $bd['image'],
                    ]);
                }
                if (!empty($bdays)) {
                    $this->template->assign_var('S_CHASTITY_PROFILE_BIRTHDAY', true);
                }

                // Badges jours consécutifs et jours totaux
                foreach ($all_badges['streak'] as $b) {
                    $this->template->assign_block_vars('chastity_profile_streak', [
                        'THRESHOLD' => $b['threshold'],
                        'LABEL'     => $b['label'],
                        'IMAGE'     => $b['image'],
                        'EARNED'    => !empty($b['earned']),
                    ]);
                }
                if (!empty($all_badges['streak'])) {
                    $this->template->assign_var('S_CHASTITY_PROFILE_STREAK', true);
                }
                foreach ($all_badges['total'] as $b) {
                    $this->template->assign_block_vars('chastity_profile_total', [
                        'THRESHOLD' => $b['threshold'],
                        'LABEL'     => $b['label'],
                        'IMAGE'     => $b['image'],
                        'EARNED'    => !empty($b['earned']),
                    ]);
                }
                if (!empty($all_badges['total'])) {
                    $this->template->assign_var('S_CHASTITY_PROFILE_TOTAL', true);
                }

                // Périodes parfaites : compteurs + anneaux réels par échelle
                if ($this->perfect_table !== '') {
                    $perfect = ['day' => 0, 'month' => 0, 'year' => 0];
                    $this->db->sql_return_on_error(true);
                    $pres = $this->db->sql_query('SELECT pscale, pcount FROM ' . $this->perfect_table . ' WHERE user_id = ' . $user_id);
                    $this->db->sql_return_on_error(false);
                    if ($pres !== false) {
                        while ($prow = $this->db->sql_fetchrow($pres)) {
                            $perfect[$prow['pscale']] = (int) $prow['pcount'];
                        }
                        $this->db->sql_freeresult($pres);
                    }
                    if (($perfect['day'] + $perfect['month'] + $perfect['year']) > 0) {
                        $this->template->assign_var('S_CHASTITY_PROFILE_PERFECT', true);
                        $prings = $calc->get_rings($user_id);
                        $plabels = [
                            'day'   => $this->user->lang('CHASTITY_PERFECT_DAYS_LBL'),
                            'month' => $this->user->lang('CHASTITY_PERFECT_MONTHS_LBL'),
                            'year'  => $this->user->lang('CHASTITY_PERFECT_YEARS_LBL'),
                        ];
                        foreach (['day', 'month', 'year'] as $scale) {
                            $this->template->assign_block_vars('chastity_profile_perfect', [
                                'LABEL'      => $plabels[$scale],
                                'COUNT'      => (int) $perfect[$scale],
                                'CAGE_PCT'   => $prings[$scale]['cage']['pct'],
                                'POSTS_PCT'  => $prings[$scale]['posts']['pct'],
                                'LOGINS_PCT' => $prings[$scale]['logins']['pct'],
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Affichée uniquement si Locktober est activé et que le membre
        // regardé a une période Locktober active pour l'année configurée.
        if (!empty($this->config['chastity_locktober_enabled'])) {
            try {
                $lk_year = (int) ($this->config['chastity_locktober_year'] ?? date('Y'));
                $sql = 'SELECT start_date FROM ' . $this->periods_table . '
                        WHERE user_id = ' . $user_id . '
                          AND is_locktober = 1
                          AND locktober_year = ' . $lk_year . "
                          AND status = 'active'
                        LIMIT 1";
                $r = $this->db->sql_query($sql);
                $lk_row = $this->db->sql_fetchrow($r);
                $this->db->sql_freeresult($r);
                if ($lk_row) {
                    $lk_day = (int) floor((time() - (int) $lk_row['start_date']) / 86400) + 1;
                    if ($lk_day < 0) { $lk_day = 0; }
                    if ($lk_day > 31) { $lk_day = 31; }
                    $lk_pct = (int) round(($lk_day / 31) * 100);
                    $this->template->assign_vars([
                        'S_CHASTITY_LOCKTOBER_PROGRESS' => true,
                        'CHASTITY_LOCKTOBER_PROGRESS_YEAR' => $lk_year,
                        'CHASTITY_LOCKTOBER_PROGRESS_DAY'  => $lk_day,
                        'CHASTITY_LOCKTOBER_PROGRESS_PCT'  => $lk_pct,
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        // ─── Info Keyholder/Sub sur le profil ──────────────────────
        if (!empty($this->keyholders_table)) {
            try {
                // Le profil regardé a-t-il un KH actif ? (il est sub)
                $sql = 'SELECT k.kh_user_id, u.username, u.user_colour
                        FROM ' . $this->keyholders_table . ' k
                        INNER JOIN ' . USERS_TABLE . " u ON u.user_id = k.kh_user_id
                        WHERE k.sub_user_id = $user_id AND k.status = 'active' LIMIT 1";
                $r = $this->db->sql_query($sql);
                $kh_row = $this->db->sql_fetchrow($r);
                $this->db->sql_freeresult($r);
                if ($kh_row) {
                    $this->template->assign_vars([
                        'S_PROFILE_HAS_KH'    => true,
                        'PROFILE_KH_USERNAME' => $kh_row['username'],
                        'PROFILE_KH_COLOUR'   => $kh_row['user_colour'],
                        'PROFILE_KH_USER_ID'  => (int) $kh_row['kh_user_id'],
                    ]);
                }

                // Le profil regardé est-il KH de quelqu'un ?
                $sql = 'SELECT k.sub_user_id, u.username, u.user_colour, cu.chastity_status
                        FROM ' . $this->keyholders_table . ' k
                        INNER JOIN ' . USERS_TABLE . " u ON u.user_id = k.sub_user_id
                        LEFT JOIN " . $this->chastity_users_table . " cu ON cu.user_id = k.sub_user_id
                        WHERE k.kh_user_id = $user_id AND k.status = 'active'
                        ORDER BY u.username_clean ASC";
                $r = $this->db->sql_query($sql);
                $sub_count = 0;
                while ($srow = $this->db->sql_fetchrow($r)) {
                    $sub_count++;
                    $this->template->assign_block_vars('profile_subs', [
                        'USERNAME'    => $srow['username'],
                        'USER_COLOUR' => $srow['user_colour'],
                        'USER_ID'     => (int) $srow['sub_user_id'],
                        'IS_LOCKED'   => ($srow['chastity_status'] === 'locked'),
                    ]);
                }
                $this->db->sql_freeresult($r);
                if ($sub_count > 0) {
                    $profile_gender = ($prefs && isset($prefs['gender']) && $prefs['gender'] !== '') ? $prefs['gender'] : 'male';
                    $kh_lbl = ($profile_gender === 'female')
                        ? (isset($this->user->lang['CHASTITY_PROFILE_SUBS_F']) ? $this->user->lang['CHASTITY_PROFILE_SUBS_F'] : 'Keyholdeuse de')
                        : (isset($this->user->lang['CHASTITY_PROFILE_SUBS']) ? $this->user->lang['CHASTITY_PROFILE_SUBS'] : 'Keyholder de');
                    $this->template->assign_vars([
                        'S_PROFILE_IS_KH'  => true,
                        'PROFILE_SUB_COUNT' => $sub_count,
                        'PROFILE_KH_LABEL'  => $kh_lbl,
                    ]);
                }
            } catch (\Throwable $e) {}
        }
    }
    
    public function add_permissions($event)
    {
        $categories = $event['categories'];
        $permissions = $event['permissions'];
        
        // Ajouter catégorie
        $categories['chastity'] = 'ACL_CAT_CHASTITY';
        
        $permissions['u_chastity_view'] = [
            'lang' => 'ACL_U_CHASTITY_VIEW',
            'cat'  => 'chastity'
        ];
        
        $permissions['u_chastity_manage'] = [
            'lang' => 'ACL_U_CHASTITY_MANAGE',
            'cat'  => 'chastity'
        ];
        
        $permissions['m_chastity_moderate'] = [
            'lang' => 'ACL_M_CHASTITY_MODERATE',
            'cat'  => 'chastity'
        ];
      
		$permissions['u_chastity_prefs'] = [
            'lang' => 'ACL_U_CHASTITY_PREFS',
            'cat'  => 'chastity'
        ];

        $permissions['u_chastity_refresh'] = [
            'lang' => 'ACL_U_CHASTITY_REFRESH',
            'cat'  => 'chastity'
        ];

        $permissions['u_chastity_leaderboard'] = [
            'lang' => 'ACL_U_CHASTITY_LEADERBOARD',
            'cat'  => 'chastity'
        ];

        $permissions['u_chastity_lock_badge'] = [
            'lang' => 'ACL_U_CHASTITY_LOCK_BADGE',
            'cat'  => 'chastity'
        ];

        $permissions['u_chastity_contract'] = [
            'lang' => 'ACL_U_CHASTITY_CONTRACT',
            'cat'  => 'chastity'
        ];

        $event['categories'] = $categories;
        $event['permissions'] = $permissions;
		
    }
   
    public function set_post_row_var($event)
    {
        if (empty($this->config['chastity_profile_display']))
        {
            return;
        }

        $post_row = $event['post_row'];
        $user_id = (int) $event['row']['user_id'];

        // Lire d'abord le statut de l'utilisateur pour savoir s'il est verrouillé
        // (même si la période vient juste de commencer et que days_current = 0)
        $sql = 'SELECT chastity_status FROM ' . $this->chastity_users_table . ' WHERE user_id = ' . $user_id;
        $result = $this->db->sql_query($sql);
        $user_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        $is_locked = ($user_row && $user_row['chastity_status'] === 'locked');

        // Contrat de chasteté actif (affiché juste au-dessus de la ligne de
        // statut Verrouillé/Libre, comme sur le profil complet).
        $has_active_contract_post = false;
        if (!empty($this->contracts_table))
        {
            try
            {
                $cp_res = $this->db->sql_query('SELECT 1 FROM ' . $this->contracts_table . "
                    WHERE encage_user_id = " . $user_id . " AND status = 'active' LIMIT 1");
                $has_active_contract_post = (bool) $this->db->sql_fetchrow($cp_res);
                $this->db->sql_freeresult($cp_res);
            }
            catch (\Throwable $e) {}
        }

        // Lire table cache
        $sql = 'SELECT days_current_period, days_since_last_end 
                FROM ' . $this->cache_table . ' 
                WHERE user_id = ' . $user_id;
        $result = $this->db->sql_query($sql);
        $cache = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        
        if ($cache)
        {
            $days_current = (int) $cache['days_current_period'];
            $days_since = (int) $cache['days_since_last_end'];

            if ($is_locked)
            {
                // Verrouillé (priorité au statut, même si 0 jour)
                $post_row['CHASTITY_STATUS'] = 'locked';
                $post_row['CHASTITY_DAYS'] = $days_current;
                $post_row['CHASTITY_SINCE_TEXT'] = $this->format_since_post($user_id, $days_current);
                $this->set_locktober_post_progress($post_row, $user_id);
            }
            else if ($days_since > 0)
            {
                // Libre depuis X jour(s)
                $post_row['CHASTITY_STATUS'] = 'free';
                $post_row['CHASTITY_DAYS'] = $days_since;
                $jw = ((int) $days_since === 1)
                    ? (isset($this->user->lang['CHASTITY_DAY']) ? $this->user->lang['CHASTITY_DAY'] : 'jour')
                    : (isset($this->user->lang['CHASTITY_DAYS']) ? $this->user->lang['CHASTITY_DAYS'] : 'jours');
                $post_row['CHASTITY_SINCE_TEXT'] = $days_since . ' ' . $jw;
            }
            else
            {
                // Pas de période
                $post_row['CHASTITY_STATUS'] = 'none';
            }

            // Anneaux de récompense : visibles quel que soit le statut (libre ou non)
            $this->set_rewards_post_rings($post_row, $user_id);

                        $post_row['CHASTITY_BADGE'] = true;
            $post_row['S_CHASTITY_POST_HAS_CONTRACT'] = $has_active_contract_post;

            $show_in_posts = true;
            if ($this->prefs_table)
            {
                $sql_p = 'SELECT show_in_posts FROM ' . $this->prefs_table . ' WHERE user_id = ' . $user_id;
                $res_p = $this->db->sql_query($sql_p);
                $row_p = $this->db->sql_fetchrow($res_p);
                $this->db->sql_freeresult($res_p);
                if ($row_p !== false) { $show_in_posts = (bool) $row_p['show_in_posts']; }
            }
            $post_row['S_SHOW_IN_POSTS'] = $show_in_posts;
        }
        else if ($is_locked)
        {
            // Pas d'entrée en cache mais l'utilisateur est verrouillé
            // (cas d'une période fraîchement créée avant le 1er run du cron de cache)
            $post_row['CHASTITY_STATUS'] = 'locked';
            $post_row['CHASTITY_DAYS']   = 0;
            $post_row['CHASTITY_SINCE_TEXT'] = $this->format_since_post($user_id, 0);
            $post_row['CHASTITY_BADGE']  = true;
            $post_row['S_CHASTITY_POST_HAS_CONTRACT'] = $has_active_contract_post;
            $post_row['S_SHOW_IN_POSTS'] = true;
            $this->set_locktober_post_progress($post_row, $user_id);
            $this->set_rewards_post_rings($post_row, $user_id);
        }

        $event['post_row'] = $post_row;
    }

    /**
     * Ajoute au post_row la progression Locktober (mini-barre + texte court)
     * si le membre a une période Locktober active pour l'année configurée.
     */
    private function set_locktober_post_progress(&$post_row, $user_id)
    {
        if (empty($this->config['chastity_locktober_enabled']))
        {
            return;
        }

        try {
            $lk_year = (int) ($this->config['chastity_locktober_year'] ?? date('Y'));
            $sql = 'SELECT start_date FROM ' . $this->periods_table . '
                    WHERE user_id = ' . (int) $user_id . '
                      AND is_locktober = 1
                      AND locktober_year = ' . $lk_year . "
                      AND status = 'active'
                    LIMIT 1";
            $res = $this->db->sql_query($sql);
            $row = $this->db->sql_fetchrow($res);
            $this->db->sql_freeresult($res);

            if ($row) {
                $day = (int) floor((time() - (int) $row['start_date']) / 86400) + 1;
                if ($day < 0) { $day = 0; }
                if ($day > 31) { $day = 31; }
                $post_row['S_CHASTITY_LOCKTOBER'] = true;
                $post_row['CHASTITY_LOCKTOBER_DAY'] = $day;
                $post_row['CHASTITY_LOCKTOBER_PCT'] = (int) round(($day / 31) * 100);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Ajoute au post_row les anneaux de récompense (période ANNÉE, version
     * compacte) si le système de récompenses est activé.
     */
    private function set_rewards_post_rings(&$post_row, $user_id)
    {
        if (empty($this->config['chastity_rewards_enabled']) || empty($this->active_days_table)) {
            return;
        }
        try {
            $calc = new \verturin\chastitytracker\service\rewards_calculator(
                $this->db, $this->config, $this->periods_table, $this->active_days_table, $this->lk_rewards_table, $this->lk_milestones_table, $this->special_days_table
            );
            $rings = $calc->get_rings($user_id);
            $post_row['S_CHASTITY_POST_RINGS'] = true;
            foreach (['day', 'month', 'year'] as $period) {
                $suffix = strtoupper($period);
                foreach (['cage', 'posts', 'logins'] as $type) {
                    $tp = strtoupper($type);
                    $post_row['CHASTITY_PR_' . $suffix . '_' . $tp]          = $rings[$period][$type]['pct'];
                    $post_row['CHASTITY_PR_' . $suffix . '_' . $tp . '_VAL'] = $rings[$period][$type]['value'];
                    $post_row['CHASTITY_PR_' . $suffix . '_' . $tp . '_GOAL']= $rings[$period][$type]['goal'];
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Texte de durée pour le badge des posts : "X jours" ou "XhYY" si < 24h.
     * Lit start_date de la période active de l'utilisateur.
     */
    private function format_since_post($user_id, $days_current)
    {
        $secs = 0;
        $sql = 'SELECT start_date FROM ' . $this->periods_table
             . " WHERE user_id = " . (int) $user_id . " AND status = 'active' ORDER BY start_date DESC LIMIT 1";
        $res = $this->db->sql_query($sql);
        $p   = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        if ($p) { $secs = max(0, time() - (int) $p['start_date']); }

        if ($this->period_calculator !== null) {
            // Si la période est active mais < 24h, secs porte l'info ; sinon on retombe sur les jours
            return ($secs > 0) ? $this->period_calculator->format_duration($secs) : $this->period_calculator->format_duration($days_current * 86400);
        }
        // Repli si service indisponible
        if ($secs > 0 && $secs < 86400) {
            return ((int) floor($secs / 3600)) . 'h' . sprintf('%02d', (int) floor(($secs % 3600) / 60));
        }
        $jw = ((int) $days_current === 1)
            ? (isset($this->user->lang['CHASTITY_DAY']) ? $this->user->lang['CHASTITY_DAY'] : 'jour')
            : (isset($this->user->lang['CHASTITY_DAYS']) ? $this->user->lang['CHASTITY_DAYS'] : 'jours');
        return $days_current . ' ' . $jw;
    }

    public function display_nav_link($event)
    {
        if (defined('IN_ADMIN'))
        {
            return;
        }

        if ($this->user->data['user_id'] == ANONYMOUS)
        {
            return;
        }

        if (!$this->auth->acl_get('u_chastity_view'))
        {
            return;
        }

        global $phpbb_root_path, $phpEx;

		$ucp_url = append_sid($phpbb_root_path . 'ucp.' . $phpEx,
            'i=\\verturin\\chastitytracker\\ucp\\main_module&mode=calendar');

        $cages_url = append_sid($phpbb_root_path . 'app.' . $phpEx . '/chastity/cages');

        $this->template->assign_vars([
            'S_CHASTITY_NAV_LINK' => true,
            'U_CHASTITY_NAV_LINK' => $ucp_url,
            'U_CHASTITY_CAGES_LINK' => $cages_url,
            'U_CHASTITY_LOCK_SVG' => rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/styles/all/theme/images/chastity_lock.svg',
            'U_CHASTITY_CAGES_SVG' => rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/styles/all/theme/images/chastity_cages.svg',
        ]);
    }

public function display_leaderboard($event)
{
    if (empty($this->config['chastity_enable']) || empty($this->config['chastity_profile_display']))
    {
        return;
    }
    if (defined('IN_ADMIN')) { return; }
    if (!$this->auth->acl_get('u_chastity_leaderboard')) { return; }

    // Afficher uniquement sur la page d'index du forum
    $page_name = $this->user->page['page_name'];
    if ($page_name !== 'index.php') { return; }

    $periods_table = $this->periods_table;
    $history_table = $this->history_table;
    $current_year  = (int) date('Y');
    $year_start    = mktime(0, 0, 0, 1, 1, $current_year);
    $year_end      = mktime(23, 59, 59, 12, 31, $current_year);

    $this->template->assign_vars([
        'S_CHASTITY_LEADERBOARD'    => true,
        'CHASTITY_LEADERBOARD_YEAR' => $current_year,
    ]);

    // ────────────────────────────────────────────────────────────────
    // COLONNE 1 : Top 5 meilleures périodes de l'année en cours
    // Utilise days_count pour les périodes entièrement dans l'année,
    // recalcule avec bornage pour les chevauchements début/fin d'année.
    // Inclut les périodes actives (bornées sur l'année).
    // ────────────────────────────────────────────────────────────────
    $sql = 'SELECT p.user_id, p.start_date, p.end_date, p.status, p.days_count, u.username, u.user_colour
            FROM ' . $periods_table . ' p
            JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
            WHERE p.start_date <= ' . $year_end . '
            AND (p.end_date >= ' . $year_start . " OR p.status = 'active')";
    $result = $this->db->sql_query($sql);
    $best_per_user_year = [];
    while ($row = $this->db->sql_fetchrow($result))
    {
        $uid = (int) $row['user_id'];
        $ps  = (int) $row['start_date'];
        $pe  = ($row['status'] === 'active') ? time() : (int) $row['end_date'];

        // Si la période est entièrement dans l'année → days_count direct
        if ($ps >= $year_start && $pe <= $year_end && $row['status'] === 'completed')
        {
            $days = (int) $row['days_count'];
        }
        else
        {
            // Chevauchement → borner et recalculer
            $ps = max($ps, $year_start);
            $pe = min($pe, $year_end);
            if ($pe <= $ps) { continue; }
            $days = (int) floor(($pe - $ps) / 86400);
        }
        if ($days <= 0) { continue; }
        if (!isset($best_per_user_year[$uid]) || $days > $best_per_user_year[$uid]['days'])
        {
            $best_per_user_year[$uid] = [
                'days' => $days, 'username' => $row['username'],
                'colour' => $row['user_colour'], 'user_id' => $uid,
            ];
        }
    }
    $this->db->sql_freeresult($result);
    usort($best_per_user_year, function($a, $b) { return $b['days'] - $a['days']; });
    $rank = 1;
    foreach (array_slice($best_per_user_year, 0, 5) as $entry)
    {
        $this->template->assign_block_vars('chastity_top_year', [
            'RANK'     => $rank++,
            'USERNAME' => get_username_string('full', $entry['user_id'], $entry['username'], $entry['colour']),
            'DAYS'     => $entry['days'],
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // COLONNE 2 : Top 5 meilleures années tous temps
    // Lecture directe de chastity_history — une seule entrée par utilisateur (la meilleure)
    // ────────────────────────────────────────────────────────────────
    $sql = 'SELECT h.user_id, h.year, h.total_days, u.username, u.user_colour
            FROM ' . $history_table . ' h
            JOIN ' . USERS_TABLE . ' u ON u.user_id = h.user_id
            WHERE h.total_days > 0
            AND h.total_days = (
                SELECT MAX(h2.total_days) FROM ' . $history_table . ' h2
                WHERE h2.user_id = h.user_id
            )
            ORDER BY h.total_days DESC
            LIMIT 5';
    $result = $this->db->sql_query($sql);
    $rank = 1;
    while ($row = $this->db->sql_fetchrow($result))
    {
        $this->template->assign_block_vars('chastity_top_best_year', [
            'RANK'     => $rank++,
            'USERNAME' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
            'YEAR'     => (int) $row['year'],
            'DAYS'     => (int) $row['total_days'],
        ]);
    }
    $this->db->sql_freeresult($result);

    // ────────────────────────────────────────────────────────────────
    // COLONNE 3 : Top 5 meilleure période tous temps
    // Utilise days_count pour les terminées, calcul live pour les actives.
    // ────────────────────────────────────────────────────────────────
    // a) Top 5 des terminées (SQL direct, rapide)
    $sql = 'SELECT p.user_id, p.days_count, u.username, u.user_colour
            FROM ' . $periods_table . ' p
            JOIN ' . USERS_TABLE . " u ON u.user_id = p.user_id
            WHERE p.status = 'completed' AND p.days_count > 0
            ORDER BY p.days_count DESC
            LIMIT 5";
    $result = $this->db->sql_query($sql);
    $best_alltime = [];
    while ($row = $this->db->sql_fetchrow($result))
    {
        $uid = (int) $row['user_id'];
        $days = (int) $row['days_count'];
        if (!isset($best_alltime[$uid]) || $days > $best_alltime[$uid]['days'])
        {
            $best_alltime[$uid] = [
                'days' => $days, 'username' => $row['username'],
                'colour' => $row['user_colour'], 'user_id' => $uid,
            ];
        }
    }
    $this->db->sql_freeresult($result);

    // b) Vérifier les périodes actives (peuvent battre le record)
    $sql = 'SELECT p.user_id, p.start_date, u.username, u.user_colour
            FROM ' . $periods_table . ' p
            JOIN ' . USERS_TABLE . " u ON u.user_id = p.user_id
            WHERE p.status = 'active'";
    $result = $this->db->sql_query($sql);
    while ($row = $this->db->sql_fetchrow($result))
    {
        $uid  = (int) $row['user_id'];
        $days = (int) floor((time() - (int) $row['start_date']) / 86400);
        if ($days <= 0) { continue; }
        if (!isset($best_alltime[$uid]) || $days > $best_alltime[$uid]['days'])
        {
            $best_alltime[$uid] = [
                'days' => $days, 'username' => $row['username'],
                'colour' => $row['user_colour'], 'user_id' => $uid,
            ];
        }
    }
    $this->db->sql_freeresult($result);
    usort($best_alltime, function($a, $b) { return $b['days'] - $a['days']; });
    $rank = 1;
    foreach (array_slice($best_alltime, 0, 5) as $entry)
    {
        $this->template->assign_block_vars('chastity_top_alltime', [
            'RANK'     => $rank++,
            'USERNAME' => get_username_string('full', $entry['user_id'], $entry['username'], $entry['colour']),
            'DAYS'     => $entry['days'],
        ]);
    }
}

    /**
     * V1 — Badge cadenas et/ou clé à côté du pseudo
     * Règles :
     *  - Sub verrouillé sans KH actif      → 🔒
     *  - Sub verrouillé avec KH actif      → 🔒🔑
     *  - KH avec au moins 1 sub verrouillé → 🔑 (en plus du cadenas si lui-même verrouillé)
     *  - Sinon                             → rien
     * Event core.modify_username_string
     */
    public function add_lock_badge($event)
    {
        // Vérifier config + permissions
        if (empty($this->config['chastity_badge_enabled'])) { return; }
        if (!$this->auth->acl_get('u_chastity_lock_badge')) { return; }

        $user_id = (int) $event['user_id'];
        if ($user_id <= 0) { return; }

        // Uniquement les modes qui supportent le HTML inline sans risque
        $mode = $event['mode'];
        if ($mode !== 'full' && $mode !== 'no_profile') { return; }

        // Sécurité : ne pas injecter si username_string ne contient pas déjà
        // de balises HTML (cas où une extension/template échappe la sortie)
        $username_string = $event['username_string'];
        if (strpos($username_string, '<') === false) { return; }

        // ─── Charger les caches une seule fois par pageview ───────────
        if ($this->locked_users_cache === null)
        {
            $this->locked_users_cache = [];
            $sql = 'SELECT user_id FROM ' . $this->chastity_users_table
                 . " WHERE chastity_status = 'locked'";
            $result = $this->db->sql_query($sql);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $this->locked_users_cache[(int) $row['user_id']] = true;
            }
            $this->db->sql_freeresult($result);
        }

        // Cache : qui est KH d'au moins un encagé sous contrôle (verrouillé ou non)
        if ($this->active_kh_cache === null)
        {
            $this->active_kh_cache = [];
            if (!empty($this->keyholders_table))
            {
                try {
                    $sql = 'SELECT DISTINCT k.kh_user_id
                            FROM ' . $this->keyholders_table . " k
                            WHERE k.status = 'active'";
                    $result = $this->db->sql_query($sql);
                    while ($row = $this->db->sql_fetchrow($result))
                    {
                        $this->active_kh_cache[(int) $row['kh_user_id']] = true;
                    }
                    $this->db->sql_freeresult($result);
                } catch (\Throwable $e) {
                    // Table pas encore créée (migration v3.7.0 pas jouée) → ignorer
                }
            }
        }

        // Cache : quels subs ont un KH actif (pour afficher 🔒🔑 au lieu de 🔒 seul)
        if ($this->subs_with_active_kh_cache === null)
        {
            $this->subs_with_active_kh_cache = [];
            if (!empty($this->keyholders_table))
            {
                try {
                    $sql = 'SELECT sub_user_id FROM ' . $this->keyholders_table . " WHERE status = 'active'";
                    $result = $this->db->sql_query($sql);
                    while ($row = $this->db->sql_fetchrow($result))
                    {
                        $this->subs_with_active_kh_cache[(int) $row['sub_user_id']] = true;
                    }
                    $this->db->sql_freeresult($result);
                } catch (\Throwable $e) {}
            }
        }

        // ─── Calcul des badges à afficher ─────────────────────────────
        // RÈGLE :
        //   - Le 🔒 indique que le membre est verrouillé (sub porte la cage)
        //   - Le 🔒 doré indique un sub verrouillé sous contrôle d'un KH actif
        //   - Le 🔑 indique que le membre est KH désigné d'au moins un sub verrouillé
        //   - Un sub qui a un KH ne porte JAMAIS la clé (seul le KH la porte)
        //   - Un KH peut aussi être verrouillé lui-même (par son propre KH) → 🔒🔑
        $is_locked = isset($this->locked_users_cache[$user_id]);
        $is_active_kh = isset($this->active_kh_cache[$user_id]);
        $has_active_kh = isset($this->subs_with_active_kh_cache[$user_id]);

        $badges = '';
        if ($is_locked) {
            if ($has_active_kh) {
                // Sub verrouillé sous contrôle d'un KH : cadenas doré
                $badges .= ' <span class="chastity-lock-badge chastity-lock-under-kh" title="' . (isset($this->user->lang['CHASTITY_KH_UNDER_KH_CONTROL']) ? $this->user->lang['CHASTITY_KH_UNDER_KH_CONTROL'] : 'Locked under Keyholder control') . '">🔒</span>';
            } else {
                $badges .= ' <span class="chastity-lock-badge" title="' . $this->user->lang['CHASTITY_STATUS_LOCKED'] . '">🔒</span>';
            }
        }
        if ($is_active_kh) {
            $badges .= ' <span class="chastity-kh-badge" title="' . (isset($this->user->lang['CHASTITY_KH_IS_KEYHOLDER']) ? $this->user->lang['CHASTITY_KH_IS_KEYHOLDER'] : 'Active Keyholder') . '">🔑</span>';
        }

        if ($badges !== '') {
            $event['username_string'] = $username_string . $badges;
        }
    }

}