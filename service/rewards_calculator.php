<?php
/**
 * Chastity Tracker — Service rewards_calculator
 * Calcule la progression des anneaux de récompense pour un utilisateur :
 *  - Cage  : heures passées en cage sur la fenêtre (intersection des périodes)
 *  - Posts : nombre de messages postés sur la fenêtre
 *  - Logins: jours actifs distincts sur la fenêtre (table chastity_active_days)
 * pour 3 fenêtres : jour / mois / année en cours.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\service;

class rewards_calculator
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\config\config */
    protected $config;

    /** @var string */
    protected $periods_table;

    /** @var string */
    protected $active_days_table;

    /** @var string */
    protected $lk_rewards_table;

    /** @var string */
    protected $lk_milestones_table;

    /** @var string */
    protected $special_days_table;

    /** @var string */
    protected $earned_table = '';

    /** @var string */
    protected $streak_milestones_table = '';

    /** @var string */
    protected $total_milestones_table = '';

    public function set_milestone_tables($streak, $total)
    {
        $this->streak_milestones_table = (string) $streak;
        $this->total_milestones_table = (string) $total;
    }

    /**
     * Définit la table des badges acquis (appelée après instanciation pour
     * ne pas modifier la signature du constructeur partout).
     */
    public function set_earned_table($table)
    {
        $this->earned_table = (string) $table;
    }

    public function __construct($db, $config, $periods_table, $active_days_table, $lk_rewards_table = '', $lk_milestones_table = '', $special_days_table = '', $earned_table = '', $streak_milestones_table = '', $total_milestones_table = '')
    {
        $this->db = $db;
        $this->config = $config;
        $this->periods_table = $periods_table;
        $this->active_days_table = $active_days_table;
        $this->lk_rewards_table = $lk_rewards_table;
        $this->lk_milestones_table = $lk_milestones_table;
        $this->special_days_table = $special_days_table;
        $this->earned_table = $earned_table;
        $this->streak_milestones_table = $streak_milestones_table;
        $this->total_milestones_table = $total_milestones_table;
    }

    /**
     * Renvoie les bornes [start, end] (timestamps) des 3 fenêtres.
     */
    public function get_windows($now = null)
    {
        $now = $now ?: time();
        return [
            'day' => [
                strtotime(date('Y-m-d 00:00:00', $now)),
                strtotime(date('Y-m-d 23:59:59', $now)),
            ],
            'month' => [
                strtotime(date('Y-m-01 00:00:00', $now)),
                strtotime(date('Y-m-t 23:59:59', $now)),
            ],
            'year' => [
                strtotime(date('Y-01-01 00:00:00', $now)),
                strtotime(date('Y-12-31 23:59:59', $now)),
            ],
        ];
    }

    /**
     * Heures en cage d'un utilisateur sur [start, end].
     * Somme des intersections de toutes ses périodes avec la fenêtre.
     */
    public function cage_hours($user_id, $start, $end)
    {
        $now = time();
        $clip_end = min($end, $now);
        if ($clip_end <= $start) {
            return 0;
        }

        // Périodes chevauchant la fenêtre : end_date = 0 => active (en cours)
        $sql = 'SELECT start_date, end_date, status FROM ' . $this->periods_table . '
                WHERE user_id = ' . (int) $user_id . '
                  AND start_date <= ' . (int) $clip_end . '
                  AND (end_date = 0 OR end_date >= ' . (int) $start . ')';
        $res = $this->db->sql_query($sql);

        $seconds = 0;
        while ($row = $this->db->sql_fetchrow($res)) {
            $p_start = (int) $row['start_date'];
            $p_end   = ((int) $row['end_date'] > 0) ? (int) $row['end_date'] : $now;
            $ov_start = max($p_start, $start);
            $ov_end   = min($p_end, $clip_end);
            if ($ov_end > $ov_start) {
                $seconds += ($ov_end - $ov_start);
            }
        }
        $this->db->sql_freeresult($res);

        return (int) floor($seconds / 3600);
    }

    /**
     * Nombre de messages postés par l'utilisateur sur [start, end].
     */
    public function post_count($user_id, $start, $end)
    {
        $clip_end = min($end, time());
        $sql = 'SELECT COUNT(post_id) AS cnt FROM ' . POSTS_TABLE . '
                WHERE poster_id = ' . (int) $user_id . '
                  AND post_time >= ' . (int) $start . '
                  AND post_time <= ' . (int) $clip_end . "
                  AND post_visibility = 1";
        $res = $this->db->sql_query($sql);
        $cnt = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($res);
        return $cnt;
    }

    /**
     * Jours actifs distincts de l'utilisateur sur [start, end].
     * day_date est stocké au format AAAAMMJJ.
     */
    public function login_days($user_id, $start, $end)
    {
        $d_start = (int) date('Ymd', $start);
        $d_end   = (int) date('Ymd', min($end, time()));
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->active_days_table . '
                WHERE user_id = ' . (int) $user_id . '
                  AND day_date >= ' . $d_start . '
                  AND day_date <= ' . $d_end;
        $res = $this->db->sql_query($sql);
        $cnt = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($res);
        return $cnt;
    }

    /**
     * Construit la structure complète des anneaux pour un utilisateur :
     * [ 'day' => ['cage'=>[val,goal,pct], 'posts'=>[...], 'logins'=>[...]], 'month'=>..., 'year'=>... ]
     */
    public function get_rings($user_id, $now = null)
    {
        $windows = $this->get_windows($now);
        $rings = [];

        foreach ($windows as $period => $w) {
            list($start, $end) = $w;

            $cage_val   = $this->cage_hours($user_id, $start, $end);
            $posts_val  = $this->post_count($user_id, $start, $end);
            $logins_val = $this->login_days($user_id, $start, $end);

            $cage_goal   = max(1, (int) $this->config['chastity_goal_cage_' . $period]);
            $posts_goal  = max(1, (int) $this->config['chastity_goal_posts_' . $period]);
            $logins_goal = max(1, (int) $this->config['chastity_goal_logins_' . $period]);

            $rings[$period] = [
                'cage'   => $this->ring($cage_val, $cage_goal),
                'posts'  => $this->ring($posts_val, $posts_goal),
                'logins' => $this->ring($logins_val, $logins_goal),
            ];
        }

        return $rings;
    }

    /**
     * Un anneau : valeur, objectif, pourcentage (borné 0..100), complétion.
     */
    private function ring($value, $goal)
    {
        $pct = (int) min(100, round(($value / $goal) * 100));
        return [
            'value'     => (int) $value,
            'goal'      => (int) $goal,
            'pct'       => $pct,
            'completed' => ($value >= $goal),
        ];
    }

    /**
     * Badges Locktober d'un utilisateur, par année.
     * Renvoie [ ['year'=>2026, 'level'=>'success'|'participated'], ... ]
     * trié par année décroissante. 'success' prime sur 'participated'.
     */
    /**
     * Calcule, pour un membre, les années Locktober avec leur niveau.
     * Réussi  = une période quelconque couvre tout octobre (1→31) de l'année.
     * Participé = inscrit (is_locktober) cette année mais sans couvrir tout le mois.
     * Renvoie [year => ['enrolled'=>bool, 'success'=>bool], ...]
     */
    public function compute_locktober_years($user_id)
    {
        $user_id = (int) $user_id;
        $years = [];
        $now = time();

        // 1) Années d'inscription Locktober
        $res = $this->db->sql_query('SELECT DISTINCT locktober_year FROM ' . $this->periods_table . '
                                     WHERE user_id = ' . $user_id . ' AND is_locktober = 1 AND locktober_year > 0');
        while ($row = $this->db->sql_fetchrow($res)) {
            $years[(int) $row['locktober_year']] = ['enrolled' => true, 'success' => false];
        }
        $this->db->sql_freeresult($res);

        // 2) Toutes les périodes pour tester la couverture d'octobre
        $periods = [];
        $min_year = (int) date('Y', $now);
        $res = $this->db->sql_query('SELECT start_date, end_date FROM ' . $this->periods_table . '
                                     WHERE user_id = ' . $user_id . ' AND start_date > 0');
        while ($row = $this->db->sql_fetchrow($res)) {
            $st = (int) $row['start_date'];
            $en = ((int) $row['end_date'] > 0) ? (int) $row['end_date'] : $now;
            $periods[] = ['start' => $st, 'end' => $en];
            $y = (int) date('Y', $st);
            if ($y < $min_year) { $min_year = $y; }
        }
        $this->db->sql_freeresult($res);

        $max_year = (int) date('Y', $now);
        for ($y = $min_year; $y <= $max_year; $y++) {
            $oct_start = mktime(0, 0, 0, 10, 1, $y);
            $oct_end   = mktime(23, 59, 59, 10, 31, $y);
            if ($now <= $oct_end) { continue; } // octobre doit être terminé
            // Couverture "encagé tout octobre" : commencé au plus tard dans la
            // journée du 1er oct, fini au plus tôt dans la journée du 31 oct
            // (peu importe l'heure exacte de début/fin).
            $cover_start = mktime(23, 59, 59, 10, 1, $y);  // fin de journée du 1er oct
            $cover_end   = mktime(0, 0, 0, 10, 31, $y);    // début de journée du 31 oct
            $covered = false;
            foreach ($periods as $p) {
                if ($p['start'] <= $cover_start && $p['end'] >= $cover_end) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                if (!isset($years[$y])) { $years[$y] = ['enrolled' => false, 'success' => false]; }
                $years[$y]['success'] = true;
            }
        }

        return $years;
    }

    public function get_locktober_badges($user_id)
    {
        $user_id = (int) $user_id;
        $years = $this->compute_locktober_years($user_id);

        krsort($years);
        $badges = [];
        foreach ($years as $y => $info) {
            $badges[] = [
                'year'  => $y,
                'level' => $info['success'] ? 'success' : 'participated',
            ];
        }

        // Joindre les récompenses d'année (libellé + image) si la table est connue
        if (!empty($badges) && $this->lk_rewards_table !== '') {
            $rewards = [];
            $this->db->sql_return_on_error(true);
            $rr = $this->db->sql_query('SELECT locktober_year, reward_label, reward_image, reward_image_part FROM ' . $this->lk_rewards_table);
            $this->db->sql_return_on_error(false);
            if ($rr !== false) {
                while ($row = $this->db->sql_fetchrow($rr)) {
                    $rewards[(int) $row['locktober_year']] = $row;
                }
                $this->db->sql_freeresult($rr);
            }

            $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/locktober/';
            foreach ($badges as &$b) {
                $r = $rewards[$b['year']] ?? null;
                // Image selon le niveau : réussi → reward_image ; participé → reward_image_part
                $img = '';
                if ($r) {
                    $img = ($b['level'] === 'success') ? $r['reward_image'] : $r['reward_image_part'];
                }
                $b['reward_label'] = ($r && $img !== '') ? $r['reward_label'] : ($r ? $r['reward_label'] : '');
                $b['reward_image'] = ($img !== '') ? ($img_url . $img) : '';
            }
            unset($b);
        } else {
            foreach ($badges as &$b) { $b['reward_label'] = ''; $b['reward_image'] = ''; }
            unset($b);
        }

        return $badges;
    }

    /**
     * Plus haut palier de fidélité atteint par l'utilisateur, selon son nombre
     * total de Locktober RÉUSSIS (locktober_completed = 1).
     * Renvoie null si aucun palier atteint ou table absente.
     */
    public function get_milestone_badge($user_id)
    {
        if ($this->lk_milestones_table === '') {
            return null;
        }

        // Nombre de Locktober réussis (couverture réelle d'octobre)
        $years = $this->compute_locktober_years($user_id);
        $nb = 0;
        foreach ($years as $info) {
            if (!empty($info['success'])) { $nb++; }
        }

        if ($nb < 1) {
            return null;
        }

        // Palier le plus élevé dont le seuil est atteint
        $sql = 'SELECT threshold, milestone_label, milestone_image
                FROM ' . $this->lk_milestones_table . '
                WHERE threshold <= ' . $nb . '
                ORDER BY threshold DESC';
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query_limit($sql, 1);
        $this->db->sql_return_on_error(false);
        if ($res === false) {
            return null;
        }
        $row = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);

        if (!$row) {
            return null;
        }

        $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/locktober/';
        return [
            'count'     => $nb,
            'threshold' => (int) $row['threshold'],
            'label'     => $row['milestone_label'],
            'image'     => $row['milestone_image'] ? ($img_url . $row['milestone_image']) : '',
        ];
    }

    /**
     * Badges "journée spéciale" : pour chaque journée définie (jour/mois) et
     * chaque année où une période du membre couvrait cette date, un badge.
     * Renvoie [ ['date'=>'14/02', 'year'=>2025, 'label'=>..., 'image'=>...], ... ]
     */
    /**
     * Badges anniversaire : encagé le jour de son propre anniversaire et/ou
     * de celui de sa keyholder active. Dates issues du profil phpBB
     * (user_birthday, format "JJ-MM-AAAA" ou "JJ-MM-"). Un badge par année
     * où une période couvre la date.
     */
    public function get_birthday_badges($user_id, $keyholders_table = '')
    {
        if (empty($this->config['chastity_birthday_enabled'])) {
            return [];
        }

        $user_id = (int) $user_id;
        $now = time();

        // Périodes du membre
        $periods = [];
        $res = $this->db->sql_query('SELECT start_date, end_date FROM ' . $this->periods_table . '
                                     WHERE user_id = ' . $user_id . ' AND start_date > 0');
        while ($row = $this->db->sql_fetchrow($res)) {
            $periods[] = [
                'start' => (int) $row['start_date'],
                'end'   => ((int) $row['end_date'] > 0) ? (int) $row['end_date'] : $now,
            ];
        }
        $this->db->sql_freeresult($res);

        if (empty($periods)) {
            return [];
        }

        // Date de naissance du membre + de sa keyholder active
        $targets = [];

        $self_bd = $this->get_user_birthday($user_id);
        if ($self_bd !== null) {
            $targets[] = [
                'dd'    => $self_bd['dd'],
                'mm'    => $self_bd['mm'],
                'label' => (string) $this->config['chastity_birthday_self_label'],
                'image' => (string) $this->config['chastity_birthday_self_image'],
                'type'  => 'self',
            ];
        }

        if ($keyholders_table !== '') {
            $this->db->sql_return_on_error(true);
            $kres = $this->db->sql_query('SELECT kh_user_id FROM ' . $keyholders_table . '
                                          WHERE sub_user_id = ' . $user_id . " AND status = 'active'");
            $this->db->sql_return_on_error(false);
            if ($kres !== false) {
                $krow = $this->db->sql_fetchrow($kres);
                $this->db->sql_freeresult($kres);
                if ($krow && (int) $krow['kh_user_id'] > 0) {
                    $kh_bd = $this->get_user_birthday((int) $krow['kh_user_id']);
                    if ($kh_bd !== null) {
                        $targets[] = [
                            'dd'    => $kh_bd['dd'],
                            'mm'    => $kh_bd['mm'],
                            'label' => (string) $this->config['chastity_birthday_kh_label'],
                            'image' => (string) $this->config['chastity_birthday_kh_image'],
                            'type'  => 'kh',
                        ];
                    }
                }
            }
        }

        if (empty($targets)) {
            return [];
        }

        $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/birthday/';
        $min_year = (int) date('Y', min(array_column($periods, 'start')));
        $max_year = (int) date('Y');

        $badges = [];
        foreach ($targets as $t) {
            for ($y = $min_year; $y <= $max_year; $y++) {
                $ts = mktime(12, 0, 0, $t['mm'], $t['dd'], $y);
                if ($ts === false) { continue; }
                $covered = false;
                foreach ($periods as $p) {
                    if ($p['start'] <= $ts && $ts <= $p['end']) {
                        $covered = true;
                        break;
                    }
                }
                if ($covered) {
                    $badges[] = [
                        'type'  => $t['type'],
                        'year'  => $y,
                        'date'  => sprintf('%02d/%02d', $t['dd'], $t['mm']),
                        'label' => $t['label'],
                        'image' => ($t['image'] !== '') ? ($img_url . $t['image']) : '',
                    ];
                }
            }
        }

        return $badges;
    }

    /**
     * Récupère le jour/mois d'anniversaire d'un utilisateur depuis son profil
     * phpBB (champ user_birthday, format "JJ-MM-AAAA" ou "JJ-MM-").
     * Renvoie ['dd'=>int,'mm'=>int] ou null si non renseigné.
     */
    private function get_user_birthday($user_id)
    {
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT user_birthday FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id);
        $this->db->sql_return_on_error(false);
        if ($res === false) { return null; }
        $row = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        if (!$row || empty($row['user_birthday'])) { return null; }

        // Format phpBB : "JJ-MM-AAAA" (ou "JJ-MM-" si année absente)
        $parts = explode('-', trim($row['user_birthday']));
        if (count($parts) < 2) { return null; }
        $dd = (int) $parts[0];
        $mm = (int) $parts[1];
        if ($dd < 1 || $dd > 31 || $mm < 1 || $mm > 12) { return null; }
        return ['dd' => $dd, 'mm' => $mm];
    }

    public function get_special_day_badges($user_id)
    {
        if ($this->special_days_table === '') {
            return [];
        }

        // Journées définies
        $days = [];
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT sday_day, sday_month, sday_label, sday_image FROM ' . $this->special_days_table);
        $this->db->sql_return_on_error(false);
        if ($res === false) {
            return [];
        }
        while ($row = $this->db->sql_fetchrow($res)) {
            $days[] = $row;
        }
        $this->db->sql_freeresult($res);

        if (empty($days)) {
            return [];
        }

        // Périodes du membre (bornes)
        $periods = [];
        $now = time();
        $res = $this->db->sql_query('SELECT start_date, end_date FROM ' . $this->periods_table . '
                                     WHERE user_id = ' . (int) $user_id);
        while ($row = $this->db->sql_fetchrow($res)) {
            $periods[] = [
                'start' => (int) $row['start_date'],
                'end'   => ((int) $row['end_date'] > 0) ? (int) $row['end_date'] : $now,
            ];
        }
        $this->db->sql_freeresult($res);

        if (empty($periods)) {
            return [];
        }

        $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/special/';
        $min_year = (int) date('Y', min(array_column($periods, 'start')));
        $max_year = (int) date('Y');

        $badges = [];
        foreach ($days as $d) {
            $dd = (int) $d['sday_day'];
            $mm = (int) $d['sday_month'];
            for ($y = $min_year; $y <= $max_year; $y++) {
                $ts = mktime(12, 0, 0, $mm, $dd, $y); // midi pour éviter les bords DST
                if ($ts === false) {
                    continue;
                }
                // Une période couvre-t-elle cette date ?
                $covered = false;
                foreach ($periods as $p) {
                    if ($p['start'] <= $ts && $ts <= $p['end']) {
                        $covered = true;
                        break;
                    }
                }
                if ($covered) {
                    $badges[] = [
                        'date'  => sprintf('%02d/%02d', $dd, $mm),
                        'year'  => $y,
                        'label' => $d['sday_label'],
                        'image' => $d['sday_image'] ? ($img_url . $d['sday_image']) : '',
                    ];
                }
            }
        }

        // Tri par année décroissante puis date
        usort($badges, function ($a, $b) {
            if ($a['year'] !== $b['year']) {
                return $b['year'] - $a['year'];
            }
            return strcmp($a['date'], $b['date']);
        });

        return $badges;
    }

    /**
     * Recalcule (incrémente) les compteurs de périodes parfaites pour tous
     * les membres ayant une activité. Une seule incrémentation par période
     * grâce à last_period. Utilisable par le cron ET le bouton ACP.
     */
    public function recalc_perfect_counts($perfect_table, $now = null)
    {
        $now = $now ?: time();
        // Sécurité : ne rien faire si la table n'existe pas encore
        $this->db->sql_return_on_error(true);
        $test = $this->db->sql_query('SELECT 1 FROM ' . $perfect_table . ' LIMIT 1');
        $this->db->sql_return_on_error(false);
        if ($test === false) {
            return;
        }
        $this->db->sql_freeresult($test);
        $period_keys = [
            'day'   => (int) date('Ymd', $now),
            'month' => (int) date('Ym', $now),
            'year'  => (int) date('Y', $now),
        ];

        $user_ids = [];
        foreach ([$this->periods_table, $this->active_days_table] as $tbl) {
            if ($tbl === '') { continue; }
            $res = $this->db->sql_query('SELECT DISTINCT user_id FROM ' . $tbl);
            while ($row = $this->db->sql_fetchrow($res)) {
                $user_ids[(int) $row['user_id']] = true;
            }
            $this->db->sql_freeresult($res);
        }

        foreach (array_keys($user_ids) as $user_id) {
            if ($user_id <= 0) { continue; }
            $rings = $this->get_rings($user_id, $now);

            foreach (['day', 'month', 'year'] as $scale) {
                $perfect = $rings[$scale]['cage']['completed']
                        && $rings[$scale]['posts']['completed']
                        && $rings[$scale]['logins']['completed'];
                if (!$perfect) { continue; }

                $pk = $period_keys[$scale];
                $res = $this->db->sql_query('SELECT pcount, last_period FROM ' . $perfect_table . '
                    WHERE user_id = ' . (int) $user_id . " AND pscale = '" . $this->db->sql_escape($scale) . "'");
                $row = $this->db->sql_fetchrow($res);
                $this->db->sql_freeresult($res);

                if ($row === false) {
                    $this->db->sql_query('INSERT INTO ' . $perfect_table . ' ' . $this->db->sql_build_array('INSERT', [
                        'user_id' => (int) $user_id, 'pscale' => $scale, 'pcount' => 1, 'last_period' => (int) $pk,
                    ]));
                } else if ((int) $row['last_period'] !== (int) $pk) {
                    $this->db->sql_query('UPDATE ' . $perfect_table . '
                        SET pcount = ' . ((int) $row['pcount'] + 1) . ', last_period = ' . (int) $pk . '
                        WHERE user_id = ' . (int) $user_id . " AND pscale = '" . $this->db->sql_escape($scale) . "'");
                }
            }
        }
    }

    /**
     * Reconstitution RÉTROACTIVE complète des périodes parfaites pour tous les
     * membres. Recalcule depuis le début (1re période de chaque membre) jusqu'à
     * aujourd'hui. Règle rétroactive : un jour/mois/année est parfait si l'anneau
     * CAGE et l'anneau POSTS sont complets — l'anneau connexions est déduit (un
     * post implique une connexion ce jour-là), car l'historique des connexions
     * n'existe pas avant l'installation du suivi.
     * Remplace intégralement le contenu de la table des compteurs.
     */
    public function recalc_perfect_full($perfect_table, $now = null)
    {
        $now = $now ?: time();

        // Sécurité : table absente
        $this->db->sql_return_on_error(true);
        $test = $this->db->sql_query('SELECT 1 FROM ' . $perfect_table . ' LIMIT 1');
        $this->db->sql_return_on_error(false);
        if ($test === false) { return; }
        $this->db->sql_freeresult($test);

        // Membres ayant au moins une période
        $members = [];
        $res = $this->db->sql_query('SELECT user_id, MIN(start_date) AS first_start
                                     FROM ' . $this->periods_table . '
                                     WHERE start_date > 0
                                     GROUP BY user_id');
        while ($row = $this->db->sql_fetchrow($res)) {
            $members[(int) $row['user_id']] = (int) $row['first_start'];
        }
        $this->db->sql_freeresult($res);

        // On vide la table puis on reconstruit
        $this->db->sql_query('DELETE FROM ' . $perfect_table);

        foreach ($members as $user_id => $first_start) {
            if ($user_id <= 0 || $first_start <= 0) { continue; }

            $counts = ['day' => 0, 'month' => 0, 'year' => 0];
            $seen   = ['month' => [], 'year' => []];

            // Objectifs
            $cage_goal_d  = max(1, (int) $this->config['chastity_goal_cage_day']);
            $posts_goal_d = max(1, (int) $this->config['chastity_goal_posts_day']);
            $cage_goal_m  = max(1, (int) $this->config['chastity_goal_cage_month']);
            $posts_goal_m = max(1, (int) $this->config['chastity_goal_posts_month']);
            $cage_goal_y  = max(1, (int) $this->config['chastity_goal_cage_year']);
            $posts_goal_y = max(1, (int) $this->config['chastity_goal_posts_year']);

            // Parcours jour par jour, de la 1re période à aujourd'hui (midi pour DST)
            $cursor = mktime(12, 0, 0, (int) date('n', $first_start), (int) date('j', $first_start), (int) date('Y', $first_start));
            $today  = mktime(12, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

            while ($cursor <= $today) {
                $w = $this->get_windows($cursor);

                // Jour parfait : cage + posts
                $cage_d  = $this->cage_hours($user_id, $w['day'][0], $w['day'][1]);
                $posts_d = $this->post_count($user_id, $w['day'][0], $w['day'][1]);
                if ($cage_d >= $cage_goal_d && $posts_d >= $posts_goal_d) {
                    $counts['day']++;
                }

                // Avancer d'un jour (midi → évite les sauts DST)
                $cursor = strtotime('+1 day', $cursor);
            }

            // Mois parfaits : pour chaque mois entre la 1re période et aujourd'hui
            $mcur = mktime(12, 0, 0, (int) date('n', $first_start), 15, (int) date('Y', $first_start));
            while ($mcur <= $today) {
                $w = $this->get_windows($mcur);
                $cage_m  = $this->cage_hours($user_id, $w['month'][0], $w['month'][1]);
                $posts_m = $this->post_count($user_id, $w['month'][0], $w['month'][1]);
                if ($cage_m >= $cage_goal_m && $posts_m >= $posts_goal_m) {
                    $counts['month']++;
                }
                $mcur = strtotime('+1 month', $mcur);
            }

            // Années parfaites
            $ycur_year = (int) date('Y', $first_start);
            $end_year  = (int) date('Y', $now);
            for ($y = $ycur_year; $y <= $end_year; $y++) {
                $ts = mktime(12, 0, 0, 6, 15, $y);
                $w = $this->get_windows($ts);
                $cage_y  = $this->cage_hours($user_id, $w['year'][0], $w['year'][1]);
                $posts_y = $this->post_count($user_id, $w['year'][0], $w['year'][1]);
                if ($cage_y >= $cage_goal_y && $posts_y >= $posts_goal_y) {
                    $counts['year']++;
                }
            }

            // Enregistrement (last_period = période courante pour éviter un double
            // comptage par le cron le même jour/mois/année)
            $pk = [
                'day'   => (int) date('Ymd', $now),
                'month' => (int) date('Ym', $now),
                'year'  => (int) date('Y', $now),
            ];
            foreach (['day', 'month', 'year'] as $scale) {
                if ($counts[$scale] > 0) {
                    $this->db->sql_query('INSERT INTO ' . $perfect_table . ' ' . $this->db->sql_build_array('INSERT', [
                        'user_id'     => (int) $user_id,
                        'pscale'      => $scale,
                        'pcount'      => (int) $counts[$scale],
                        'last_period' => (int) $pk[$scale],
                    ]));
                }
            }
        }
    }

    /**
     * Recalcule les récompenses STOCKÉES d'un seul membre :
     *  - complétion Locktober (octobres déjà terminés)
     *  - compteurs de périodes parfaites du moment
     * Appelé après l'ajout/modification d'une période par ce membre.
     */
    public function recalc_user($user_id, $perfect_table, $now = null)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) { return; }
        $now = $now ?: time();

        // 1) Complétion Locktober pour les octobres terminés
        $res = $this->db->sql_query('SELECT DISTINCT locktober_year FROM ' . $this->periods_table . '
                                     WHERE user_id = ' . $user_id . ' AND is_locktober = 1 AND locktober_year > 0');
        $years = [];
        while ($row = $this->db->sql_fetchrow($res)) { $years[] = (int) $row['locktober_year']; }
        $this->db->sql_freeresult($res);

        foreach ($years as $year) {
            $oct_start = mktime(0, 0, 0, 10, 1, $year);
            $oct_end   = mktime(23, 59, 59, 10, 31, $year);
            if ($now <= $oct_end) { continue; }
            $cover_start = mktime(23, 59, 59, 10, 1, $year);
            $cover_end   = mktime(0, 0, 0, 10, 31, $year);
            $this->db->sql_query('UPDATE ' . $this->periods_table . '
                SET locktober_completed = 1
                WHERE user_id = ' . $user_id . ' AND is_locktober = 1 AND locktober_year = ' . (int) $year . '
                  AND start_date <= ' . (int) $cover_start . '
                  AND (end_date = 0 OR end_date >= ' . (int) $cover_end . ')');
            $this->db->sql_query('UPDATE ' . $this->periods_table . '
                SET locktober_completed = 0
                WHERE user_id = ' . $user_id . ' AND is_locktober = 1 AND locktober_year = ' . (int) $year . '
                  AND NOT (start_date <= ' . (int) $cover_start . '
                           AND (end_date = 0 OR end_date >= ' . (int) $cover_end . '))');
        }

        // 2) Périodes parfaites du membre (jour/mois/année courants)
        if ($perfect_table !== '') {
            $this->db->sql_return_on_error(true);
            $test = $this->db->sql_query('SELECT 1 FROM ' . $perfect_table . ' LIMIT 1');
            $this->db->sql_return_on_error(false);
            if ($test === false) {
                return;
            }
            $this->db->sql_freeresult($test);
            $period_keys = [
                'day'   => (int) date('Ymd', $now),
                'month' => (int) date('Ym', $now),
                'year'  => (int) date('Y', $now),
            ];
            $rings = $this->get_rings($user_id, $now);
            foreach (['day', 'month', 'year'] as $scale) {
                $perfect = $rings[$scale]['cage']['completed']
                        && $rings[$scale]['posts']['completed']
                        && $rings[$scale]['logins']['completed'];
                if (!$perfect) { continue; }
                $pk = $period_keys[$scale];
                $res = $this->db->sql_query('SELECT pcount, last_period FROM ' . $perfect_table . '
                    WHERE user_id = ' . $user_id . " AND pscale = '" . $this->db->sql_escape($scale) . "'");
                $row = $this->db->sql_fetchrow($res);
                $this->db->sql_freeresult($res);
                if ($row === false) {
                    $this->db->sql_query('INSERT INTO ' . $perfect_table . ' ' . $this->db->sql_build_array('INSERT', [
                        'user_id' => $user_id, 'pscale' => $scale, 'pcount' => 1, 'last_period' => (int) $pk,
                    ]));
                } else if ((int) $row['last_period'] !== (int) $pk) {
                    $this->db->sql_query('UPDATE ' . $perfect_table . '
                        SET pcount = ' . ((int) $row['pcount'] + 1) . ', last_period = ' . (int) $pk . '
                        WHERE user_id = ' . $user_id . " AND pscale = '" . $this->db->sql_escape($scale) . "'");
                }
            }
        }
    }

    /**
     * Synchronise les badges FIGÉS d'un membre.
     * - Insère en base tout badge actuellement justifié et pas encore stocké.
     * - Retire un badge stocké uniquement si plus AUCUNE période ne le justifie.
     * Les badges de l'année EN COURS ne sont pas figés (encore en acquisition).
     * Les libellés/images/KH sont figés au moment de l'insertion.
     */
    public function sync_earned_badges($user_id, $keyholders_table = '')
    {
        if ($this->earned_table === '') {
            return;
        }
        $user_id = (int) $user_id;
        $cur_year = (int) date('Y');

        // 1) Badges actuellement justifiés (toutes années sauf année en cours)
        $justified = $this->compute_justified_badges($user_id, $keyholders_table, $cur_year);

        // 2) Badges déjà stockés
        $stored = [];
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT eb_id, badge_type, badge_year, badge_key FROM ' . $this->earned_table . ' WHERE user_id = ' . $user_id);
        $this->db->sql_return_on_error(false);
        if ($res === false) { return; }
        while ($row = $this->db->sql_fetchrow($res)) {
            $sig = $row['badge_type'] . '|' . (int) $row['badge_year'] . '|' . $row['badge_key'];
            $stored[$sig] = (int) $row['eb_id'];
        }
        $this->db->sql_freeresult($res);

        // 3) Insérer les nouveaux
        foreach ($justified as $sig => $b) {
            if (isset($stored[$sig])) { continue; }
            $this->db->sql_query('INSERT INTO ' . $this->earned_table . ' ' . $this->db->sql_build_array('INSERT', [
                'user_id'     => $user_id,
                'badge_type'  => $b['type'],
                'badge_year'  => (int) $b['year'],
                'badge_key'   => $b['key'],
                'badge_label' => $b['label'],
                'badge_image' => $b['image'],
                'badge_level' => $b['level'] ?? '',
                'extra'       => $b['extra'] ?? '',
                'earned_at'   => time(),
            ]));
        }

        // 4) Retirer ceux qui ne sont plus justifiés (période source disparue)
        foreach ($stored as $sig => $eb_id) {
            if (!isset($justified[$sig])) {
                $this->db->sql_query('DELETE FROM ' . $this->earned_table . ' WHERE eb_id = ' . (int) $eb_id);
            }
        }
    }

    /**
     * Calcule tous les badges justifiés par les périodes, hors année en cours.
     * Renvoie un tableau indexé par signature unique => données du badge.
     */
    private function compute_justified_badges($user_id, $keyholders_table, $exclude_year)
    {
        $out = [];

        // Locktober (déjà figé par année passée)
        foreach ($this->get_locktober_badges($user_id) as $b) {
            if ((int) $b['year'] >= $exclude_year) { continue; }
            $sig = 'locktober|' . (int) $b['year'] . '|' . $b['level'];
            $out[$sig] = [
                'type'  => 'locktober',
                'year'  => (int) $b['year'],
                'key'   => $b['level'],
                'level' => $b['level'],
                'label' => $b['reward_label'] ?? '',
                'image' => $b['reward_image'] ?? '',
            ];
        }

        // Journées spéciales
        foreach ($this->get_special_day_badges($user_id) as $b) {
            if ((int) $b['year'] >= $exclude_year) { continue; }
            $sig = 'sday|' . (int) $b['year'] . '|' . $b['date'];
            $out[$sig] = [
                'type'  => 'sday',
                'year'  => (int) $b['year'],
                'key'   => $b['date'],
                'label' => $b['label'],
                'image' => $b['image'],
            ];
        }

        // Anniversaires (perso + KH) — la KH est figée via le libellé/extra
        foreach ($this->get_birthday_badges($user_id, $keyholders_table) as $b) {
            if ((int) $b['year'] >= $exclude_year) { continue; }
            $type = ($b['type'] === 'kh') ? 'birthday_kh' : 'birthday_self';
            $sig = $type . '|' . (int) $b['year'] . '|' . $b['date'];
            $out[$sig] = [
                'type'  => $type,
                'year'  => (int) $b['year'],
                'key'   => $b['date'],
                'label' => $b['label'],
                'image' => $b['image'],
            ];
        }

        return $out;
    }

    /**
     * Affichage : badges FIGÉS (table) + calcul à la volée pour l'année en cours.
     * Renvoie ['locktober'=>[...], 'sday'=>[...], 'birthday'=>[...]].
     */
    public function get_all_badges($user_id, $keyholders_table = '', $allow_compact = false)
    {
        $user_id = (int) $user_id;
        $cur_year = (int) date('Y');
        $result = ['locktober' => [], 'sday' => [], 'birthday' => [], 'streak' => [], 'total' => []];

        // 1) Badges figés (années passées)
        if ($this->earned_table !== '') {
            $this->db->sql_return_on_error(true);
            $res = $this->db->sql_query('SELECT badge_type, badge_year, badge_key, badge_label, badge_image, badge_level
                                         FROM ' . $this->earned_table . ' WHERE user_id = ' . $user_id . ' ORDER BY badge_year DESC');
            $this->db->sql_return_on_error(false);
            if ($res !== false) {
                while ($row = $this->db->sql_fetchrow($res)) {
                    $t = $row['badge_type'];
                    if ($t === 'locktober') {
                        $result['locktober'][] = [
                            'year' => (int) $row['badge_year'],
                            'level' => $row['badge_level'],
                            'reward_label' => $row['badge_label'],
                            'reward_image' => $row['badge_image'],
                        ];
                    } elseif ($t === 'sday') {
                        $result['sday'][] = [
                            'year' => (int) $row['badge_year'],
                            'date' => $row['badge_key'],
                            'label' => $row['badge_label'],
                            'image' => $row['badge_image'],
                        ];
                    } elseif ($t === 'birthday_self' || $t === 'birthday_kh') {
                        $result['birthday'][] = [
                            'type' => ($t === 'birthday_kh') ? 'kh' : 'self',
                            'year' => (int) $row['badge_year'],
                            'date' => $row['badge_key'],
                            'label' => $row['badge_label'],
                            'image' => $row['badge_image'],
                        ];
                    }
                }
                $this->db->sql_freeresult($res);
            }
        }

        // 2) Compléter avec l'année EN COURS (calcul à la volée)
        foreach ($this->get_locktober_badges($user_id) as $b) {
            if ((int) $b['year'] === $cur_year) {
                $result['locktober'][] = $b;
            }
        }
        foreach ($this->get_special_day_badges($user_id) as $b) {
            if ((int) $b['year'] === $cur_year) {
                $result['sday'][] = $b;
            }
        }
        foreach ($this->get_birthday_badges($user_id, $keyholders_table) as $b) {
            if ((int) $b['year'] === $cur_year) {
                $result['birthday'][] = $b;
            }
        }

        // Badges jours consécutifs (record) et jours totaux (cumul) : tous les
        // paliers atteints. Records/cumuls = toujours "acquis", calcul direct.
        $result['streak'] = $this->get_streak_badges($user_id, $allow_compact);
        $result['total']  = $this->get_total_badges($user_id, $allow_compact);

        return $result;
    }

    /**
     * Met à jour les libellés/images des badges DÉJÀ FIGÉS d'un membre depuis
     * les sources actuelles (récompenses Locktober, journées spéciales, configs
     * anniversaire). Utile après modification d'une image dans l'ACP : les
     * badges figés gardaient l'ancienne image, ceci les rafraîchit sans changer
     * les badges acquis eux-mêmes.
     */
    public function refresh_earned_images($user_id, $keyholders_table = '')
    {
        if ($this->earned_table === '') {
            return;
        }
        $user_id = (int) $user_id;

        // Recalculer les badges justifiés (avec libellés/images actuels)
        $fresh = $this->compute_justified_badges($user_id, $keyholders_table, (int) date('Y') + 1);

        $res = $this->db->sql_query('SELECT eb_id, badge_type, badge_year, badge_key FROM ' . $this->earned_table . ' WHERE user_id = ' . $user_id);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($res)) {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($res);

        foreach ($rows as $row) {
            $sig = $row['badge_type'] . '|' . (int) $row['badge_year'] . '|' . $row['badge_key'];
            if (!isset($fresh[$sig])) { continue; }
            $b = $fresh[$sig];
            $this->db->sql_query('UPDATE ' . $this->earned_table . " SET
                badge_label = '" . $this->db->sql_escape($b['label']) . "',
                badge_image = '" . $this->db->sql_escape($b['image']) . "'
                WHERE eb_id = " . (int) $row['eb_id']);
        }
    }

    /**
     * Reconstruit rétroactivement les jours de connexion d'un membre à partir
     * de sources fiables de phpBB : chaque jour distinct où il a posté (un post
     * implique une connexion ce jour-là) + son dernier passage (user_lastvisit).
     * N'écrase rien : insère seulement les jours absents (PK user_id+day_date).
     * Ne peut pas récupérer les jours de connexion SANS post (non tracés avant
     * l'installation), mais remplit l'essentiel.
     */
    public function rebuild_active_days($user_id)
    {
        if ($this->active_days_table === '') {
            return;
        }
        $user_id = (int) $user_id;

        // Jours distincts où le membre a posté
        $days = [];
        $res = $this->db->sql_query('SELECT DISTINCT post_time FROM ' . POSTS_TABLE . '
                                     WHERE poster_id = ' . $user_id . ' AND post_time > 0');
        while ($row = $this->db->sql_fetchrow($res)) {
            $days[(int) date('Ymd', (int) $row['post_time'])] = true;
        }
        $this->db->sql_freeresult($res);

        // Dernier passage connu
        $res = $this->db->sql_query('SELECT user_lastvisit FROM ' . USERS_TABLE . ' WHERE user_id = ' . $user_id);
        if ($row = $this->db->sql_fetchrow($res)) {
            if ((int) $row['user_lastvisit'] > 0) {
                $days[(int) date('Ymd', (int) $row['user_lastvisit'])] = true;
            }
        }
        $this->db->sql_freeresult($res);

        if (empty($days)) {
            return;
        }

        // Jours déjà enregistrés (pour n'insérer que les manquants)
        $existing = [];
        $res = $this->db->sql_query('SELECT day_date FROM ' . $this->active_days_table . ' WHERE user_id = ' . $user_id);
        while ($row = $this->db->sql_fetchrow($res)) {
            $existing[(int) $row['day_date']] = true;
        }
        $this->db->sql_freeresult($res);

        foreach (array_keys($days) as $d) {
            if (isset($existing[$d])) { continue; }
            $this->db->sql_return_on_error(true);
            $this->db->sql_query('INSERT INTO ' . $this->active_days_table . ' ' . $this->db->sql_build_array('INSERT', [
                'user_id'  => $user_id,
                'day_date' => (int) $d,
            ]));
            $this->db->sql_return_on_error(false);
        }
    }

    /**
     * Plus longue série de jours consécutifs en cage = durée de la plus longue
     * période UNIQUE jamais atteinte (deux périodes accolées ne se cumulent pas).
     */
    public function get_longest_streak($user_id)
    {
        $user_id = (int) $user_id;
        $now = time();
        $max = 0;

        // Plus longue période complétée (valeur officielle days_count, la même
        // que celle utilisée par recalc_user_totals pour l'affichage standard).
        $sql = 'SELECT MAX(days_count) AS m FROM ' . $this->periods_table . "
                WHERE user_id = " . $user_id . " AND status = 'completed'";
        $res = $this->db->sql_query($sql);
        $max = (int) $this->db->sql_fetchfield('m');
        $this->db->sql_freeresult($res);

        // Période active éventuelle (jours écoulés en temps réel)
        $sql = 'SELECT start_date FROM ' . $this->periods_table . "
                WHERE user_id = " . $user_id . " AND status = 'active'
                ORDER BY start_date DESC LIMIT 1";
        $res = $this->db->sql_query($sql);
        $active = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        if ($active && (int) $active['start_date'] > 0) {
            $active_days = (int) floor(($now - (int) $active['start_date']) / 86400);
            if ($active_days > $max) { $max = $active_days; }
        }

        return $max;
    }

    /**
     * Nombre total de jours cumulés en cage : somme des SECONDES RÉELLES
     * (end_date - start_date) des périodes complétées + jours écoulés de la
     * période active en temps réel. On ne somme PAS les days_count déjà
     * arrondis individuellement (SUM(days_count)), car plusieurs périodes de
     * moins de 24h contribueraient chacune 0 au total même si leur cumul
     * réel dépasse un jour plein — perte de précision par arrondi prématuré.
     * Formule alignée avec recalc_user_totals() de l'UCP, pour éviter toute
     * divergence entre un total affiché et un badge attribué.
     */
    public function get_total_caged_days($user_id)
    {
        $user_id = (int) $user_id;
        $now = time();

        $sql = 'SELECT SUM(end_date - start_date) AS total_seconds FROM ' . $this->periods_table . "
                WHERE user_id = " . $user_id . " AND status = 'completed' AND end_date > start_date";
        $res = $this->db->sql_query($sql);
        $total_seconds = (int) $this->db->sql_fetchfield('total_seconds');
        $this->db->sql_freeresult($res);
        $total = (int) floor($total_seconds / 86400);

        $sql = 'SELECT start_date FROM ' . $this->periods_table . "
                WHERE user_id = " . $user_id . " AND status = 'active'
                ORDER BY start_date DESC LIMIT 1";
        $res = $this->db->sql_query($sql);
        $active = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        if ($active && (int) $active['start_date'] > 0) {
            $total += (int) floor(($now - (int) $active['start_date']) / 86400);
        }

        return (int) $total;
    }

    public function get_streak_badges($user_id, $allow_compact = false)
    {
        return $this->get_threshold_badges($this->streak_milestones_table, $this->get_longest_streak($user_id), 'streak', $allow_compact);
    }

    public function get_total_badges($user_id, $allow_compact = false)
    {
        return $this->get_threshold_badges($this->total_milestones_table, $this->get_total_caged_days($user_id), 'total', $allow_compact);
    }

    /**
     * Tous les paliers (d'une table de milestones) dont le seuil est atteint
     * par $value. Image résolue en URL.
     */
    private function get_threshold_badges($table, $value, $kind, $allow_compact = false)
    {
        if ($table === '') {
            return [];
        }
        $value = (int) $value;
        $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/milestones/';

        // Tous les paliers, triés
        $all = [];
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT threshold, milestone_label, milestone_image FROM ' . $table . ' ORDER BY threshold ASC');
        $this->db->sql_return_on_error(false);
        if ($res === false) { return []; }
        while ($row = $this->db->sql_fetchrow($res)) {
            $all[] = [
                'kind'      => $kind,
                'threshold' => (int) $row['threshold'],
                'label'     => $row['milestone_label'],
                'image'     => ($row['milestone_image'] !== '') ? ($img_url . $row['milestone_image']) : '',
                'earned'    => ((int) $row['threshold'] <= $value),
            ];
        }
        $this->db->sql_freeresult($res);

        if (empty($all)) {
            return [];
        }

        $show_next = !empty($this->config['chastity_ms_show_next']);
        $compact   = $allow_compact && !empty($this->config['chastity_ms_compact']);

        // Séparer obtenus / à venir
        $earned = [];
        $next   = null;
        foreach ($all as $b) {
            if ($b['earned']) {
                $earned[] = $b;
            } elseif ($next === null) {
                $next = $b; // premier non atteint = prochain objectif
            }
        }

        $result = [];
        if ($compact) {
            // Dernier obtenu + prochain grisé (2 max)
            if (!empty($earned)) {
                $result[] = end($earned);
            }
            if ($show_next && $next !== null) {
                $next['earned'] = false;
                $result[] = $next;
            }
        } else {
            // Tous les obtenus (+ prochain grisé si option)
            $result = $earned;
            if ($show_next && $next !== null) {
                $next['earned'] = false;
                $result[] = $next;
            }
        }

        return $result;
    }
}
