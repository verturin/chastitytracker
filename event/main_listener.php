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
    protected $chastity_users_table;
    protected $cache_table;
	protected $history_table;	
	protected $prefs_table;	
    
	public function __construct($config, $template, $user, $db, $auth, $periods_table, $chastity_users_table, $cache_table, $history_table, $prefs_table = '')	
	
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
            ],
        ];
    }
    
    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        
        $lang_set_ext[] = [
            'ext_name' => 'verturin/chastitytracker',
            'lang_set' => 'common',
        ];
        
        $event['lang_set_ext'] = $lang_set_ext;
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

        if ($locked && $row['start_date'])
        {
            $current_days = (int) floor((time() - (int) $row['start_date']) / 86400);
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

        // Meilleure année (depuis chastity_history)
        $sql_best_year = 'SELECT year, total_days FROM ' . $this->history_table . "
                         WHERE user_id = $user_id ORDER BY total_days DESC LIMIT 1";
        $result_best_year = $this->db->sql_query($sql_best_year);
        $best_year_row = $this->db->sql_fetchrow($result_best_year);
        $this->db->sql_freeresult($result_best_year);
        $best_year_days = $best_year_row ? (int) $best_year_row['total_days'] : 0;
        $best_year      = $best_year_row ? (int) $best_year_row['year'] : 0;

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
                $this->template->assign_block_vars('profile_cal', [
                    'MONTH'  => $m,
                    'DAY'    => $d,
                    'LOCKED' => isset($locked_cal[$date_str]),
                    'TODAY'  => ($date_str === $today_str),
                    'EMPTY'  => false,
                ]);
            }

            // Bloc mois pour le template
            $this->template->assign_block_vars('profile_months', [
                'MONTH_NAME' => $this->user->lang['datetime'][date('F', $m_first)],
                'MONTH_NUM'  => $m,
                'MONTH_YEAR' => $y,
            ]);
        }


		$this->template->assign_vars([
            'CHASTITY_STATUS'          => $this->user->lang[$locked ? 'CHASTITY_STATUS_LOCKED' : 'CHASTITY_STATUS_FREE'],
            'CHASTITY_CURRENT_DAYS'    => $current_days,
            'CHASTITY_TOTAL_DAYS'      => (int) $row['chastity_total_days'],
            'CHASTITY_DAYS_SINCE_END'  => $days_since_end,
            'S_CHASTITY_LOCKED'        => $locked,
            'S_DISPLAY_CHASTITY'       => true,
            'CHASTITY_LOCKED_SINCE'    => ($locked && $row['start_date'])
                ? $this->user->format_date((int) $row['start_date'], 'd/m/Y') : '',
            'CHASTITY_YEAR_DAYS'       => $year_days,
            'CHASTITY_YEAR_HOURS'      => (int) floor(($year_seconds % 86400) / 3600),
            'CHASTITY_YEAR_MINUTES'    => (int) floor(($year_seconds % 3600) / 60),
            'CHASTITY_CURRENT_YEAR'    => $current_year,
            'CHASTITY_BEST_YEAR_DAYS'  => $best_year_days,
            'CHASTITY_BEST_YEAR'       => $best_year,
            'PROFILE_CAL_YEAR'  	   => (int) date('Y'),
            'S_SHOW_STATUS'            => $show_status,
            'S_SHOW_DAYS'              => $show_days,
            'S_SHOW_TOTAL_DAYS'        => $show_total_days,
            'S_SHOW_YEAR_STATS'        => $show_year_stats,
            'S_SHOW_BEST_YEAR'         => $show_best_year,
            'S_SHOW_IN_POSTS'          => $show_in_posts,
            'S_SHOW_IN_CONTACT'        => $show_in_contact,
        ]);

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
            
            if ($days_current > 0)
            {
                // Verrouillé
                $post_row['CHASTITY_STATUS'] = 'locked';
                $post_row['CHASTITY_DAYS'] = $days_current;
            }
            else if ($days_since > 0)
            {
                // Libre depuis X jours
                $post_row['CHASTITY_STATUS'] = 'free';
                $post_row['CHASTITY_DAYS'] = $days_since;
            }
            else
            {
                // Pas de période
                $post_row['CHASTITY_STATUS'] = 'none';
            }
            
                        $post_row['CHASTITY_BADGE'] = true;

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

        $event['post_row'] = $post_row;
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

        $this->template->assign_vars([
            'S_CHASTITY_NAV_LINK' => true,
            'U_CHASTITY_NAV_LINK' => $ucp_url,
            'U_CHASTITY_LOCK_SVG' => $phpbb_root_path . 'ext/verturin/chastitytracker/styles/all/theme/images/chastity_lock.svg',
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



}