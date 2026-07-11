<?php
/**
 * Chastity Tracker — Badge Image Controller
 * Génère une image PNG dynamique du statut pour signatures, forums externes, etc.
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\controller;

use Symfony\Component\HttpFoundation\Response;

if (!defined('IN_PHPBB'))
{
    exit;
}

class badge
{
    protected $db;
    protected $config;
    protected $request;
    protected $prefs_table;
    protected $users_table;
    protected $periods_table;
    protected $cache_table;
    protected $ext_root_path;
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
        $ext_root_path,
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
        $this->ext_root_path = $ext_root_path;
        $this->keyholders_table = $keyholders_table;
        $this->contracts_table = $contracts_table;
    }

    public function handle()
    {
        $token = $this->request->variable('token', '');
        $style = $this->request->variable('style', 'dark'); // dark, light, mini, medium
        $preview = $this->request->variable('preview', ''); // 'free' pour forcer aperçu version libre

        // ── Récupérer les données (même logique que api.php) ──
        $data = $this->get_user_data($token);

        // Override pour aperçu version libre (utilisé sur la page UCP Widget/Token)
        if ($preview === 'free' && !$data['error']) {
            $data['locked']          = false;
            $data['status']          = 'free';
            $data['days_current']    = 0;
            $data['days_since_last'] = max(1, (int) ($data['days_since_last'] ?? 1));
        }

        // Live-preview : si l'utilisateur passe explicitement alias, hide_status ou tagline en URL,
        // on les utilise pour l'aperçu (sans sauvegarder en BDD). Sécurisé par le token valide.
        if (!$data['error']) {
            if ($this->request->is_set('alias')) {
                $alias_param = trim((string) $this->request->variable('alias', '', true));
                $data['username'] = $alias_param !== '' ? $alias_param : ($data['real_username'] ?? $data['username']);
            }
            if ($this->request->is_set('hide_status')) {
                $data['hide_status'] = (bool) $this->request->variable('hide_status', 0);
            }
            if ($this->request->is_set('tagline')) {
                $tagline_param = trim((string) $this->request->variable('tagline', '', true));
                if (mb_strlen($tagline_param) > 150) { $tagline_param = mb_substr($tagline_param, 0, 150); }
                $data['tagline'] = $tagline_param;
            }
        }

        // ── Générer l'image ──
        if ($style === 'mini')
        {
            $image = $this->render_mini($data);
        }
        else if ($style === 'medium')
        {
            $image = $this->render_medium($data);
        }
        else
        {
            $image = $this->render_full($data, $style);
        }

        // ── Retourner l'image PNG ──
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $response = new Response($png, 200);
        $response->headers->set('Content-Type', 'image/png');

        // Si le client a passé _t=... ou preview=..., c'est un refresh forcé : pas de cache
        $force_no_cache = $this->request->variable('_t', '') !== '' || $preview !== '';
        if ($force_no_cache) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        } else {
            $response->headers->set('Cache-Control', 'public, max-age=300'); // Cache 5 min
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        }
        return $response;
    }

    /**
     * Récupère les données utilisateur à partir du token
     */
    private function get_user_data($token)
    {
        if (empty($token))
        {
            return ['error' => true, 'message' => 'Token manquant'];
        }

        $sql = 'SELECT p.user_id
                FROM ' . $this->prefs_table . ' p
                WHERE p.api_enabled = 1
                AND p.api_token = \'' . $this->db->sql_escape($token) . '\'';
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return ['error' => true, 'message' => 'Token invalide'];
        }

        $user_id = (int) $row['user_id'];

        // Vérifier inactivité 60 jours
        $sixty_days_ago = time() - (60 * 86400);
        $sql_post = 'SELECT user_lastpost_time, username FROM ' . USERS_TABLE
                  . ' WHERE user_id = ' . $user_id;
        $res_post = $this->db->sql_query($sql_post);
        $user_row = $this->db->sql_fetchrow($res_post);
        $this->db->sql_freeresult($res_post);

        $username = $user_row ? $user_row['username'] : '?';

        if ($user_row && (int) $user_row['user_lastpost_time'] < $sixty_days_ago)
        {
            return ['error' => false, 'status' => 'inactive', 'username' => $username, 'message' => 'Donnez des nouvelles !'];
        }

        // Données chastity
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
            return ['error' => true, 'message' => 'Utilisateur inconnu'];
        }

        $locked = ($cu['chastity_status'] === 'locked');

        $days_current = 0;
        $secs_current = 0;
        if ($locked)
        {
            $sql_p = 'SELECT start_date FROM ' . $this->periods_table
                   . " WHERE user_id = $user_id AND status = 'active' ORDER BY start_date DESC LIMIT 1";
            $res_p = $this->db->sql_query($sql_p);
            $p     = $this->db->sql_fetchrow($res_p);
            $this->db->sql_freeresult($res_p);
            if ($p) {
                $secs_current = max(0, time() - (int) $p['start_date']);
                $days_current = (int) floor($secs_current / 86400);
            }
        }

        // Lire le tagline + préférences badge (avec rétro-compatibilité si colonnes absentes)
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

        // L'alias remplace le pseudo si renseigné
        $display_name = $alias !== '' ? $alias : $username;

        // ── Statut Keyholder ──
        // is_keyholder : a au moins un encagé sous contrôle (relation active), verrouillé ou non
        // kh_subs_count : nombre total d'encagés sous contrôle
        $is_keyholder = false;   // user est KH d'au moins un encagé
        $has_active_kh = false;  // user a un KH actif (lui-même encagé sous contrôle)
        $kh_subs_count = 0;
        if (!empty($this->keyholders_table)) {
            try {
                // Combien d'encagés sous sa coupe ?
                $sql = 'SELECT COUNT(*) AS nb FROM ' . $this->keyholders_table
                     . " WHERE kh_user_id = $user_id AND status = 'active'";
                $r = $this->db->sql_query($sql);
                $kh_subs_count = (int) $this->db->sql_fetchfield('nb');
                $this->db->sql_freeresult($r);
                $is_keyholder = ($kh_subs_count > 0);

                // A-t-il un KH actif ?
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

        return [
            'error'            => false,
            'status'           => $cu['chastity_status'],
            'locked'           => $locked,
            'username'         => $display_name,
            'real_username'    => $username,
            'hide_status'      => $hide_status,
            'days_current'     => $locked ? $days_current : 0,
            'secs_current'     => $locked ? $secs_current : 0,
            'days_since_last'  => $locked ? 0 : (int) $cu['days_since_last_end'],
            'total_days'       => (function() use ($user_id) {
                $sql_tt = 'SELECT SUM(end_date - start_date) AS total_seconds FROM ' . $this->periods_table . "
                           WHERE user_id = " . $user_id . " AND status = 'completed' AND end_date > start_date";
                $res_tt = $this->db->sql_query($sql_tt);
                $t_seconds = (int) $this->db->sql_fetchfield('total_seconds');
                $this->db->sql_freeresult($res_tt);
                $t = (int) floor($t_seconds / 86400);
                $sql_tt = 'SELECT start_date FROM ' . $this->periods_table . "
                           WHERE user_id = " . $user_id . " AND status = 'active'
                           ORDER BY start_date DESC LIMIT 1";
                $res_tt = $this->db->sql_query($sql_tt);
                $act_tt = $this->db->sql_fetchrow($res_tt);
                $this->db->sql_freeresult($res_tt);
                if ($act_tt && (int) $act_tt['start_date'] > 0) {
                    $t += (int) floor((time() - (int) $act_tt['start_date']) / 86400);
                }
                return $t;
            })(),
            'days_current_year'=> (int) ($cu['days_current_year'] ?? 0),
            'tagline'          => $tagline,
            'is_keyholder'     => $is_keyholder,
            'has_active_kh'    => $has_active_kh,
            'kh_subs_count'    => $kh_subs_count,
            'has_active_contract' => $has_active_contract,
            'gender'           => $gender,
        ];
    }

    /**
     * Génère le badge complet (400x160)
     */
    private function render_full($data, $style)
    {
        $w = 400;
        $tagline = isset($data['tagline']) ? trim((string) $data['tagline']) : '';
        $has_tagline = ($tagline !== '');
        $h = $has_tagline ? 185 : 160;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);

        // Couleurs
        if ($style === 'light')
        {
            $bg       = imagecolorallocate($img, 245, 245, 250);
            $text     = imagecolorallocate($img, 30, 30, 50);
            $subtext  = imagecolorallocate($img, 120, 120, 140);
            $border   = imagecolorallocate($img, 200, 200, 210);
        }
        else // dark
        {
            $bg       = imagecolorallocate($img, 26, 26, 46);
            $text     = imagecolorallocate($img, 240, 240, 255);
            $subtext  = imagecolorallocate($img, 140, 140, 170);
            $border   = imagecolorallocate($img, 50, 50, 70);
        }
        $red      = imagecolorallocate($img, 231, 76, 60);
        $green    = imagecolorallocate($img, 46, 204, 113);
        $orange   = imagecolorallocate($img, 243, 156, 18);

        // Fond avec coins arrondis simulés
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bg);
        imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);

        // Police (utiliser la police par défaut GD si pas de TTF)
        $font_path = $this->ext_root_path . 'styles/all/theme/fonts/';
        $has_ttf   = false;

        // Chercher une police TTF dans l'extension ou utiliser les polices GD intégrées
        $ttf_file = $font_path . 'badge_font.ttf';
        if (file_exists($ttf_file))
        {
            $has_ttf = true;
        }

        if (isset($data['error']) && $data['error'])
        {
            // Erreur
            $this->draw_text($img, $w / 2, $h / 2, $data['message'], $red, 4, true, $has_ttf ? $ttf_file : null);
            return $img;
        }

        if (($data['status'] ?? '') === 'inactive')
        {
            $this->draw_text($img, $w / 2, 50, 'INACTIF', $orange, 5, true, $has_ttf ? $ttf_file : null);
            $this->draw_text($img, $w / 2, 90, $data['username'], $text, 4, true, $has_ttf ? $ttf_file : null);
            $this->draw_text($img, $w / 2, 120, $data['message'], $subtext, 2, true, $has_ttf ? $ttf_file : null);
            return $img;
        }

        $locked    = $data['locked'];
        $is_kh     = !empty($data['is_keyholder']);
        $kh_only   = ($is_kh && !$locked); // Keyholder non encagé → mode KH
        $gold      = imagecolorallocate($img, 218, 165, 32);
        $hide_status = !empty($data['hide_status']);

        if ($kh_only) {
            // ── Mode KEYHOLDER (non encagé) ──
            $nb = (int) ($data['kh_subs_count'] ?? 0);
            $sub_word = ($nb == 1) ? 'encagé' : 'encagés';
            $kh_label = (($data['gender'] ?? 'male') === 'female') ? 'KEYHOLDEUSE' : 'KEYHOLDER';

            // Barre dorée en haut
            imagefilledrectangle($img, 0, 0, $w - 1, 4, $gold);

            // Pseudo + statut KEYHOLDER
            $this->draw_text($img, 12, 22, $data['username'], $text, 4, false, $has_ttf ? $ttf_file : null);
            if (!$hide_status) {
                $this->draw_text($img, 12, 48, $kh_label, $gold, 5, false, $has_ttf ? $ttf_file : null);
            }

            // Ligne : "cle 🔑 N encagés" (sans emoji pour GD — on dessine une clé textuelle)
            $this->draw_text($img, 12, 82, $nb . ' ' . $sub_word, $gold, 5, false, $has_ttf ? $ttf_file : null);

            // Ligne séparatrice + stats (jours cumulés perso)
            imageline($img, 12, 110, $w - 12, 110, $border);
            $this->draw_text($img, 12,  125, 'Total: ' . $data['total_days'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);
            $this->draw_text($img, 160, 125, date('Y') . ': ' . $data['days_current_year'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);

            if ($has_tagline) {
                imageline($img, 12, 148, $w - 12, 148, $border);
                $this->draw_tagline($img, $w, 155, $tagline, $subtext, $has_ttf ? $ttf_file : null, 60);
            }

            // Pastille K dorée
            $this->draw_kh_indicator($img, $w, 'kh', 'normal');
            if (!empty($data['has_active_contract'])) {
                $this->draw_contract_indicator($img, $w, 'normal');
            }
            return $img;
        }

        $status_color = $locked ? $red : $green;
        $status_text  = $locked ? 'EN CAGE' : 'LIBRE';
        $days         = $locked ? $data['days_current'] : $data['days_since_last'];
        $since_text   = $this->format_since($locked, (int) ($data['secs_current'] ?? 0), $days);

        // Barre de statut en haut
        imagefilledrectangle($img, 0, 0, $w - 1, 4, $status_color);

        // Colonne gauche : pseudo + statut (rien si masqué)
        $this->draw_text($img, 12, 22, $data['username'], $text, 4, false, $has_ttf ? $ttf_file : null);
        if (!$hide_status) {
            $this->draw_text($img, 12, 48, $status_text, $status_color, 5, false, $has_ttf ? $ttf_file : null);
        }

        // "depuis X jours" ou "depuis XhYY" si < 24h
        $this->draw_text($img, 12, 82, 'depuis ' . $since_text, $text, 5, false, $has_ttf ? $ttf_file : null);

        // Ligne séparatrice
        imageline($img, 12, 110, $w - 12, 110, $border);

        // Stats en bas
        $this->draw_text($img, 12,  125, 'Total: ' . $data['total_days'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);
        $this->draw_text($img, 160, 125, date('Y') . ': ' . $data['days_current_year'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);

        // Tagline personnalisé (en bas, sur 2 lignes max)
        if ($has_tagline) {
            imageline($img, 12, 148, $w - 12, 148, $border);
            $this->draw_tagline($img, $w, 155, $tagline, $subtext, $has_ttf ? $ttf_file : null, 60);
        }

        // Indicateur Keyholder
        if (!empty($data['is_keyholder'])) {
            $this->draw_kh_indicator($img, $w, 'kh', 'normal');
        } elseif (!empty($data['has_active_kh']) && $locked) {
            $this->draw_kh_indicator($img, $w, 'sub_kh', 'normal');
        }

        // Indicateur contrat de chasteté actif (même si libre)
        if (!empty($data['has_active_contract'])) {
            $this->draw_contract_indicator($img, $w, 'normal');
        }

        return $img;
    }

    /**
     * Génère le badge medium (400x100) — nombre de jours à côté du statut
     */
    private function render_medium($data)
    {
        $w = 400;
        $tagline = isset($data['tagline']) ? trim((string) $data['tagline']) : '';
        $has_tagline = ($tagline !== '');
        $h = $has_tagline ? 125 : 100;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);

        $bg       = imagecolorallocate($img, 26, 26, 46);
        $text     = imagecolorallocate($img, 240, 240, 255);
        $subtext  = imagecolorallocate($img, 140, 140, 170);
        $border   = imagecolorallocate($img, 50, 50, 70);
        $red      = imagecolorallocate($img, 231, 76, 60);
        $green    = imagecolorallocate($img, 46, 204, 113);
        $orange   = imagecolorallocate($img, 243, 156, 18);

        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bg);
        imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);

        $font_path = $this->ext_root_path . 'styles/all/theme/fonts/';
        $ttf_file = $font_path . 'badge_font.ttf';
        $has_ttf = file_exists($ttf_file);

        if (isset($data['error']) && $data['error'])
        {
            $this->draw_text($img, $w / 2, $h / 2, $data['message'], $red, 4, true, $has_ttf ? $ttf_file : null);
            return $img;
        }

        if (($data['status'] ?? '') === 'inactive')
        {
            $this->draw_text($img, $w / 2, 30, 'INACTIF — ' . $data['username'], $orange, 4, true, $has_ttf ? $ttf_file : null);
            $this->draw_text($img, $w / 2, 60, $data['message'], $subtext, 2, true, $has_ttf ? $ttf_file : null);
            return $img;
        }

        $locked       = $data['locked'];
        $is_kh        = !empty($data['is_keyholder']);
        $kh_only      = ($is_kh && !$locked);
        $gold         = imagecolorallocate($img, 218, 165, 32);
        $hide_status  = !empty($data['hide_status']);

        if ($kh_only) {
            $nb = (int) ($data['kh_subs_count'] ?? 0);
            $sub_word = ($nb == 1) ? 'encagé' : 'encagés';
            $kh_label = (($data['gender'] ?? 'male') === 'female') ? 'KEYHOLDEUSE' : 'KEYHOLDER';

            imagefilledrectangle($img, 0, 0, $w - 1, 4, $gold);
            $this->draw_text($img, 12, 14, $data['username'], $text, 4, false, $has_ttf ? $ttf_file : null);
            if (!$hide_status) {
                $this->draw_text($img, 12, 42, $kh_label, $gold, 5, false, $has_ttf ? $ttf_file : null);
            }
            // N encagés à droite
            $kh_str = $nb . ' ' . $sub_word;
            $kh_w = strlen($kh_str) * 10;
            $this->draw_text($img, $w - 12 - $kh_w, 42, $kh_str, $gold, 5, false, $has_ttf ? $ttf_file : null);

            imageline($img, 12, 72, $w - 12, 72, $border);
            $this->draw_text($img, 12,  82, 'Total: ' . $data['total_days'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);
            $this->draw_text($img, 160, 82, date('Y') . ': ' . $data['days_current_year'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);

            if ($has_tagline) {
                imageline($img, 12, 100, $w - 12, 100, $border);
                $this->draw_tagline($img, $w, 107, $tagline, $subtext, $has_ttf ? $ttf_file : null, 60);
            }
            $this->draw_kh_indicator($img, $w, 'kh', 'normal');
            return $img;
        }

        $status_color = $locked ? $red : $green;
        $status_text  = $locked ? 'EN CAGE' : 'LIBRE';
        $days         = $locked ? $data['days_current'] : $data['days_since_last'];

        // Barre de statut en haut
        imagefilledrectangle($img, 0, 0, $w - 1, 4, $status_color);

        // Ligne 1 : pseudo
        $this->draw_text($img, 12, 14, $data['username'], $text, 4, false, $has_ttf ? $ttf_file : null);

        // Ligne 2 : Statut à gauche (ou rien si masqué) + "depuis X jours" à droite
        if (!$hide_status) {
            $this->draw_text($img, 12, 42, $status_text, $status_color, 5, false, $has_ttf ? $ttf_file : null);
        }

        // "depuis X jours" ou "depuis XhYY" si < 24h — aligné à droite
        $since_text = 'depuis ' . $this->format_since($locked, (int) ($data['secs_current'] ?? 0), $days);
        $total_w = strlen($since_text) * 10;
        $start_x = $w - 12 - $total_w;
        $this->draw_text($img, $start_x, 42, $since_text, $text, 5, false, $has_ttf ? $ttf_file : null);

        // Ligne séparatrice
        imageline($img, 12, 72, $w - 12, 72, $border);

        // Stats en bas
        $this->draw_text($img, 12,  82, 'Total: ' . $data['total_days'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);
        $this->draw_text($img, 160, 82, date('Y') . ': ' . $data['days_current_year'] . 'j', $subtext, 2, false, $has_ttf ? $ttf_file : null);

        // Tagline personnalisé
        if ($has_tagline) {
            imageline($img, 12, 100, $w - 12, 100, $border);
            $this->draw_tagline($img, $w, 107, $tagline, $subtext, $has_ttf ? $ttf_file : null, 60);
        }

        // Indicateur Keyholder
        if (!empty($data['is_keyholder'])) {
            $this->draw_kh_indicator($img, $w, 'kh', 'normal');
        } elseif (!empty($data['has_active_kh']) && $locked) {
            $this->draw_kh_indicator($img, $w, 'sub_kh', 'normal');
        }

        // Indicateur contrat de chasteté actif (même si libre)
        if (!empty($data['has_active_contract'])) {
            $this->draw_contract_indicator($img, $w, 'normal');
        }

        return $img;
    }

    /**
     * Génère le mini badge (200x40) pour les signatures compactes
     */
    private function render_mini($data)
    {
        $w = 240;
        $h = 36;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);

        $bg      = imagecolorallocate($img, 26, 26, 46);
        $text    = imagecolorallocate($img, 240, 240, 255);
        $subtext = imagecolorallocate($img, 140, 140, 170);
        $red     = imagecolorallocate($img, 231, 76, 60);
        $green   = imagecolorallocate($img, 46, 204, 113);
        $border  = imagecolorallocate($img, 50, 50, 70);

        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bg);
        imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);

        if (isset($data['error']) && $data['error'])
        {
            imagestring($img, 2, 8, 10, 'Erreur: ' . $data['message'], $red);
            return $img;
        }

        if (($data['status'] ?? '') === 'inactive')
        {
            imagestring($img, 2, 8, 10, 'Inactif — ' . $data['username'], $subtext);
            return $img;
        }

        $locked = $data['locked'];
        $is_kh  = !empty($data['is_keyholder']);
        $kh_only = ($is_kh && !$locked);
        $gold   = imagecolorallocate($img, 218, 165, 32);
        $hide_status = !empty($data['hide_status']);

        if ($kh_only) {
            $nb = (int) ($data['kh_subs_count'] ?? 0);
            $sub_word = ($nb == 1) ? 'encage' : 'encages';
            $kh_label = (($data['gender'] ?? 'male') === 'female') ? 'Keyholdeuse' : 'Keyholder';
            imagefilledrectangle($img, 0, 0, 3, $h - 1, $gold);
            imagestring($img, 3, 10, 2,  $data['username'], $text);
            imagestring($img, 2, 10, 18, $kh_label . ' | ' . $nb . ' ' . $sub_word, $subtext);
            $this->draw_kh_indicator($img, $w, 'kh', 'mini');
            return $img;
        }

        $sc     = $locked ? $red : $green;
        $days   = $locked ? $data['days_current'] : $data['days_since_last'];
        $since_text = $this->format_since($locked, (int) ($data['secs_current'] ?? 0), $days);

        // Pastille de couleur
        imagefilledrectangle($img, 0, 0, 3, $h - 1, $sc);

        // Texte (sans accent car imagestring ne gère pas UTF-8)
        imagestring($img, 3, 10, 2,  $data['username'], $text);
        imagestring($img, 2, 10, 18, 'depuis ' . $since_text . ' | Total: ' . $data['total_days'] . 'j', $subtext);

        // Indicateur Keyholder (taille mini)
        if (!empty($data['is_keyholder'])) {
            $this->draw_kh_indicator($img, $w, 'kh', 'mini');
        } elseif (!empty($data['has_active_kh']) && $locked) {
            $this->draw_kh_indicator($img, $w, 'sub_kh', 'mini');
        }

        // Indicateur contrat de chasteté actif (même si libre)
        if (!empty($data['has_active_contract'])) {
            $this->draw_contract_indicator($img, $w, 'mini');
        }

        return $img;
    }

    /**
     * Dessine du texte (avec ou sans TTF)
     */
    /**
     * Formate la durée écoulée d'une période active.
     * < 24h → "1h05", "5h30" ; >= 24h → "X jour(s)".
     * @param bool $locked  période active ?
     * @param int  $secs    secondes écoulées (si verrouillé)
     * @param int  $days    jours (fallback / libre)
     * @return string  texte prêt à afficher (après "depuis ")
     */
    private function format_since($locked, $secs, $days)
    {
        if ($locked && $secs > 0 && $secs < 86400) {
            $h = (int) floor($secs / 3600);
            $m = (int) floor(($secs % 3600) / 60);
            return $h . 'h' . sprintf('%02d', $m);
        }
        $jour_word = ($days == 1) ? 'jour' : 'jours';
        return $days . ' ' . $jour_word;
    }

    private function draw_text($img, $x, $y, $text, $color, $font_size = 3, $centered = false, $ttf = null, $right_align = false)
    {
        if ($ttf && function_exists('imagettftext'))
        {
            $pt = $font_size * 4 + 4; // Convertir taille GD en points approximatifs
            $bbox = imagettfbbox($pt, 0, $ttf, $text);
            $tw   = $bbox[2] - $bbox[0];
            if ($centered) { $x = $x - $tw / 2; }
            elseif ($right_align) { $x = $x - $tw; }
            imagettftext($img, $pt, 0, (int) $x, $y, $color, $ttf, $text);
        }
        else
        {
            // Polices GD intégrées (1-5) : imagestring ne gère pas l'UTF-8,
            // on convertit vers ISO-8859-1 pour les accents (é, è, à, ...).
            if (function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            } elseif (function_exists('iconv')) {
                $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
                if ($conv !== false) { $text = $conv; }
            }
            $gd_font = min(5, max(1, $font_size));
            $fw = imagefontwidth($gd_font);
            $tw = $fw * strlen($text);
            if ($centered) { $x = $x - $tw / 2; }
            elseif ($right_align) { $x = $x - $tw; }
            imagestring($img, $gd_font, (int) $x, $y, $text, $color);
        }
    }

    /**
     * Dessine un tagline sur 1 ou 2 lignes max, centré, avec wrap simple.
     */
    private function draw_tagline($img, $w, $y_top, $text, $color, $ttf = null, $chars_per_line = 60)
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($text === '') { return; }

        // Wrap mot par mot
        $lines = [];
        $words = explode(' ', $text);
        $current = '';
        foreach ($words as $word) {
            if (mb_strlen($current . ($current ? ' ' : '') . $word) <= $chars_per_line) {
                $current .= ($current ? ' ' : '') . $word;
            } else {
                if ($current !== '') { $lines[] = $current; }
                $current = $word;
                if (count($lines) >= 2) { break; }
            }
        }
        if ($current !== '' && count($lines) < 2) { $lines[] = $current; }
        $lines = array_slice($lines, 0, 2);

        // Ajouter "…" si tronqué
        if (count($lines) === 2) {
            $rebuilt = implode(' ', $lines);
            if (mb_strlen($rebuilt) < mb_strlen($text)) {
                $lines[1] = mb_substr($lines[1], 0, max(0, mb_strlen($lines[1]) - 1)) . '…';
            }
        }

        $line_h = 14;
        foreach ($lines as $i => $line) {
            $this->draw_text($img, $w / 2, $y_top + ($i * $line_h), $line, $color, 2, true, $ttf);
        }
    }

    /**
     * Dessine un indicateur Keyholder en haut à droite du badge
     * (cercle doré avec une lettre K)
     * - 'kh' : utilisateur est KH actif (vrai indicateur de pouvoir)
     * - 'sub_kh' : sub avec KH actif (indicateur de soumission, plus discret)
     */
    /**
     * Indicateur "Contrat de chasteté actif", positionné en haut à DROITE,
     * immédiatement à GAUCHE du badge KH (K), pour former un groupe visuel
     * cohérent des deux pastilles de statut. S'affiche même sans KH, dans ce
     * cas seul (à la même position que si le K était présent) — reste donc
     * toujours au même endroit, jamais flottant.
     */
    private function draw_contract_indicator($img, $w, $size = 'normal')
    {
        if ($size === 'mini') {
            $radius = 8;
            $offset_x = 14;
            $offset_y = 14;
            $font_size = 2;
            $gap = 4;
        } else {
            $radius = 12;
            $offset_x = 22;
            $offset_y = 18;
            $font_size = 3;
            $gap = 6;
        }

        // Positionné juste à gauche du badge K (qui est centré sur w - offset_x) :
        // un diamètre complet + un petit espace entre les deux pastilles.
        $cx = $w - $offset_x - ($radius * 2) - $gap;
        $cy = $offset_y;

        $bg = imagecolorallocate($img, 188, 42, 77);    // rose CTR (#BC2A4D)
        $fg = imagecolorallocate($img, 255, 255, 255);
        $border = imagecolorallocate($img, 140, 30, 58);

        imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $bg);
        imageellipse($img, $cx, $cy, $radius * 2, $radius * 2, $border);

        $text = 'C';
        $char_w = imagefontwidth($font_size);
        $char_h = imagefontheight($font_size);
        imagestring($img, $font_size, $cx - ($char_w / 2), $cy - ($char_h / 2), $text, $fg);
    }

    private function draw_kh_indicator($img, $w, $type = 'kh', $size = 'normal')
    {
        if ($size === 'mini') {
            $radius = 8;
            $offset_x = 14;
            $offset_y = 14;
            $font_size = 2;
        } else {
            $radius = 12;
            $offset_x = 22;
            $offset_y = 18;
            $font_size = 3;
        }

        $cx = $w - $offset_x;
        $cy = $offset_y;

        // Couleur : doré pour KH, argenté pour sub_kh (plus discret)
        if ($type === 'kh') {
            $bg = imagecolorallocate($img, 218, 165, 32);   // gold
            $fg = imagecolorallocate($img, 255, 255, 255);  // white
            $border = imagecolorallocate($img, 184, 134, 11);
        } else {
            $bg = imagecolorallocate($img, 192, 192, 192);  // silver
            $fg = imagecolorallocate($img, 70, 70, 70);     // dark grey
            $border = imagecolorallocate($img, 130, 130, 130);
        }

        // Cercle plein avec bordure
        imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $bg);
        imageellipse($img, $cx, $cy, $radius * 2, $radius * 2, $border);

        // Lettre K centrée
        $text = 'K';
        $char_w = imagefontwidth($font_size);
        $char_h = imagefontheight($font_size);
        imagestring($img, $font_size, $cx - ($char_w / 2), $cy - ($char_h / 2), $text, $fg);
    }

}
