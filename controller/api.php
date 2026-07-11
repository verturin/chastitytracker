<?php
/**
 * Chastity Tracker — API Controller (JSON public)
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined('IN_PHPBB'))
{
    exit;
}

class api
{
    protected $db;
    protected $config;
    protected $request;
    protected $prefs_table;
    protected $users_table;
    protected $periods_table;
    protected $cache_table;
    protected $keyholders_table;
    protected $contracts_table;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\config\config $config,
        \phpbb\request\request $request,
        $prefs_table,
        $users_table,
        $periods_table,
        $cache_table,
        $keyholders_table = '',
        $contracts_table = ''
    )
    {
        $this->db            = $db;
        $this->config        = $config;
        $this->request       = $request;
        $this->prefs_table   = $prefs_table;
        $this->users_table   = $users_table;
        $this->periods_table = $periods_table;
        $this->cache_table   = $cache_table;
        $this->keyholders_table = $keyholders_table;
        $this->contracts_table = $contracts_table;
    }

    public function handle()
    {
        $token = $this->request->variable('token', '');

        if (empty($token))
        {
            return new JsonResponse(['error' => 'Token manquant'], 400);
        }

        // Vérifier le token dans chastity_user_prefs
        $sql = 'SELECT p.user_id
                FROM ' . $this->prefs_table . ' p
                WHERE p.api_enabled = 1
                AND p.api_token = \'' . $this->db->sql_escape($token) . '\'';
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return new JsonResponse(['error' => 'Token invalide'], 403);
        }

        $user_id = (int) $row['user_id'];

        // Vérifier si le membre a posté dans les 60 derniers jours
        $sixty_days_ago = time() - (60 * 86400);
        $sql_post = 'SELECT user_lastpost_time FROM ' . USERS_TABLE
                  . ' WHERE user_id = ' . $user_id;
        $res_post = $this->db->sql_query($sql_post);
        $user_row = $this->db->sql_fetchrow($res_post);
        $this->db->sql_freeresult($res_post);

        if ($user_row && (int) $user_row['user_lastpost_time'] < $sixty_days_ago)
        {
            return new JsonResponse([
                'status'  => 'inactive',
                'message' => 'Donnez des nouvelles sur le forum !',
            ]);
        }

        // Récupérer les données du membre
        $sql_cu = 'SELECT cu.chastity_status, cu.chastity_total_days,
                          cc.days_current_period, cc.days_current_year, cc.days_since_last_end
                   FROM ' . $this->users_table . ' cu
                   LEFT JOIN ' . $this->cache_table . ' cc ON cc.user_id = cu.user_id
                   WHERE cu.user_id = ' . $user_id;
        $res_cu = $this->db->sql_query($sql_cu);
        $cu     = $this->db->sql_fetchrow($res_cu);
        $this->db->sql_freeresult($res_cu);

        if (!$cu)
        {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }

        $locked = ($cu['chastity_status'] === 'locked');

        // Période active — calcul temps réel
        $days_current = 0;
        if ($locked)
        {
            $sql_p = 'SELECT start_date FROM ' . $this->periods_table
                   . " WHERE user_id = $user_id AND status = 'active' ORDER BY start_date DESC LIMIT 1";
            $res_p = $this->db->sql_query($sql_p);
            $p     = $this->db->sql_fetchrow($res_p);
            $this->db->sql_freeresult($res_p);
            if ($p) { $days_current = (int) floor((time() - (int) $p['start_date']) / 86400); }
        }

        // Lire les préfs badge (rétro-compat si colonnes absentes)
        $tagline = '';
        $alias = '';
        $hide_status = false;
        $gender = 'male';
        try {
            $sql_t = 'SELECT * FROM ' . $this->prefs_table . ' WHERE user_id = ' . $user_id;
            $res_t = $this->db->sql_query($sql_t);
            $row_t = $this->db->sql_fetchrow($res_t);
            $this->db->sql_freeresult($res_t);
            if ($row_t) {
                if (isset($row_t['badge_tagline']))     { $tagline = (string) $row_t['badge_tagline']; }
                if (isset($row_t['badge_alias']))       { $alias = trim((string) $row_t['badge_alias']); }
                if (isset($row_t['badge_hide_status'])) { $hide_status = (bool) $row_t['badge_hide_status']; }
                if (isset($row_t['gender']) && $row_t['gender'] !== '') { $gender = (string) $row_t['gender']; }
            }
        } catch (\Exception $e) {}

        // Statut Keyholder
        // is_keyholder : a au moins une relation KH active (encagé sous contrôle, verrouillé ou non)
        // kh_subs_count : nombre total d'encagés sous contrôle (relation active)
        $is_keyholder = false;
        $has_active_kh = false;
        $kh_subs_count = 0;
        if (!empty($this->keyholders_table)) {
            try {
                $sql = 'SELECT COUNT(*) AS nb FROM ' . $this->keyholders_table
                     . " WHERE kh_user_id = $user_id AND status = 'active'";
                $r = $this->db->sql_query($sql);
                $kh_subs_count = (int) $this->db->sql_fetchfield('nb');
                $this->db->sql_freeresult($r);
                $is_keyholder = ($kh_subs_count > 0);

                $sql = 'SELECT 1 FROM ' . $this->keyholders_table . " WHERE sub_user_id = $user_id AND status = 'active' LIMIT 1";
                $r = $this->db->sql_query($sql);
                $has_active_kh = (bool) $this->db->sql_fetchrow($r);
                $this->db->sql_freeresult($r);
            } catch (\Throwable $e) {}
        }

        // Contrat de chasteté actif (valide même si le membre est libre)
        $has_active_contract = false;
        if (!empty($this->contracts_table)) {
            try {
                $sql = 'SELECT 1 FROM ' . $this->contracts_table . "
                        WHERE encage_user_id = $user_id AND status = 'active' LIMIT 1";
                $r = $this->db->sql_query($sql);
                $has_active_contract = (bool) $this->db->sql_fetchrow($r);
                $this->db->sql_freeresult($r);
            } catch (\Throwable $e) {}
        }

        // Total de jours recalculé en TEMPS RÉEL, en sommant les SECONDES
        // RÉELLES des périodes complétées (pas les days_count déjà arrondis
        // individuellement) + jours de la période active.
        $sql_tt = 'SELECT SUM(end_date - start_date) AS total_seconds FROM ' . $this->periods_table . "
                   WHERE user_id = " . $user_id . " AND status = 'completed' AND end_date > start_date";
        $res_tt = $this->db->sql_query($sql_tt);
        $live_total_seconds = (int) $this->db->sql_fetchfield('total_seconds');
        $this->db->sql_freeresult($res_tt);
        $live_total_days = (int) floor($live_total_seconds / 86400);
        $sql_tt = 'SELECT start_date FROM ' . $this->periods_table . "
                   WHERE user_id = " . $user_id . " AND status = 'active'
                   ORDER BY start_date DESC LIMIT 1";
        $res_tt = $this->db->sql_query($sql_tt);
        $act_tt = $this->db->sql_fetchrow($res_tt);
        $this->db->sql_freeresult($res_tt);
        if ($act_tt && (int) $act_tt['start_date'] > 0) {
            $live_total_days += (int) floor((time() - (int) $act_tt['start_date']) / 86400);
        }

        return new JsonResponse([
            'status'             => $cu['chastity_status'],
            'locked'             => $locked,
            'days_current'       => $locked ? $days_current : 0,
            'days_since_last'    => $locked ? 0 : (int) $cu['days_since_last_end'],
            'total_days'         => $live_total_days,
            'days_current_year'  => (int) ($cu['days_current_year'] ?? 0),
            'tagline'            => $tagline,
            'alias'              => $alias,
            'hide_status'        => $hide_status,
            'is_keyholder'       => $is_keyholder,
            'has_active_kh'      => $has_active_kh,
            'has_active_contract'=> $has_active_contract,
            'kh_subs_count'      => $kh_subs_count,
            'gender'             => $gender,
            'keyholder_label'    => ($gender === 'female') ? 'Keyholdeuse' : 'Keyholder',
        ]);
    }
}
