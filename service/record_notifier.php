<?php
/**
 * Chastity Tracker — Service de félicitations record
 * Détecte quand un membre bat son record de jours consécutifs et envoie un
 * MP de félicitations au membre (+ sa keyholder en copie si elle existe).
 * Anti-doublon via la colonne record_notified de chastity_user_prefs.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\service;

class record_notifier
{
    protected $db;
    protected $config;
    protected $user;
    protected $rewards_calc;
    protected $prefs_table;
    protected $keyholders_table;
    protected $root_path;
    protected $php_ext;

    public function __construct($db, $config, $user, $rewards_calc, $prefs_table, $keyholders_table, $root_path, $php_ext)
    {
        $this->db = $db;
        $this->config = $config;
        $this->user = $user;
        $this->rewards_calc = $rewards_calc;
        $this->prefs_table = $prefs_table;
        $this->keyholders_table = $keyholders_table;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    /**
     * Vérifie le record d'un membre et envoie le MP si battu.
     * @param int  $user_id
     * @param bool $on_close  true si appelé à la clôture d'une période
     */
    public function check_and_notify($user_id, $on_close = false)
    {
        if (empty($this->config['chastity_record_pm_enabled'])) {
            return;
        }
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        // Préférences : refus du membre + dernier record notifié
        $optout = 0;
        $notified = 0;
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT record_pm_optout, record_notified FROM ' . $this->prefs_table . ' WHERE user_id = ' . $user_id);
        $this->db->sql_return_on_error(false);
        if ($res === false) { return; }
        if ($row = $this->db->sql_fetchrow($res)) {
            $optout   = (int) $row['record_pm_optout'];
            $notified = (int) $row['record_notified'];
        }
        $this->db->sql_freeresult($res);

        if ($optout) {
            return;
        }

        // Record actuel (plus longue série)
        $record = (int) $this->rewards_calc->get_longest_streak($user_id);

        // On ne félicite que si le record dépasse le dernier déjà notifié
        // et qu'il est significatif (au moins 1 jour).
        if ($record < 1 || $record <= $notified) {
            return;
        }

        // Mémoriser le nouveau record notifié (anti-doublon) AVANT l'envoi
        $this->db->sql_query('UPDATE ' . $this->prefs_table . ' SET record_notified = ' . $record . ' WHERE user_id = ' . $user_id);

        // Récupérer le nom du membre
        $username = $this->get_username($user_id);
        if ($username === '') {
            return;
        }

        // Keyholder active (copie)
        $kh_id = $this->get_active_keyholder($user_id);

        $this->send_pm($user_id, $username, $kh_id, $record, $on_close);
    }

    protected function get_username($user_id)
    {
        $res = $this->db->sql_query('SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id);
        $row = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        return $row ? (string) $row['username'] : '';
    }

    protected function get_active_keyholder($user_id)
    {
        if ($this->keyholders_table === '') {
            return 0;
        }
        $this->db->sql_return_on_error(true);
        $res = $this->db->sql_query('SELECT kh_user_id FROM ' . $this->keyholders_table . '
                                     WHERE sub_user_id = ' . (int) $user_id . " AND status = 'active'");
        $this->db->sql_return_on_error(false);
        if ($res === false) { return 0; }
        $row = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        return $row ? (int) $row['kh_user_id'] : 0;
    }

    protected function send_pm($user_id, $username, $kh_id, $record, $on_close)
    {
        if (!function_exists('submit_pm')) {
            include_once($this->root_path . 'includes/functions_privmsgs.' . $this->php_ext);
        }
        if (!function_exists('generate_text_for_storage')) {
            include_once($this->root_path . 'includes/functions_content.' . $this->php_ext);
        }

        $subject = sprintf($this->user->lang['CHASTITY_RECORD_PM_SUBJECT'], $record);
        $msg_key = $on_close ? 'CHASTITY_RECORD_PM_MESSAGE_CLOSE' : 'CHASTITY_RECORD_PM_MESSAGE_LIVE';
        $message_text = sprintf($this->user->lang[$msg_key], $username, $record);

        $uid = $bitfield = $options = '';
        generate_text_for_storage($message_text, $uid, $bitfield, $options, true, true, true);

        // Expéditeur système : on prend le compte admin fondateur (user_id le plus
        // bas avec type founder), sinon l'utilisateur courant.
        $from_id = $this->get_system_sender();

        $address = ['u' => [(int) $user_id => 'to']];
        if ($kh_id > 0 && $kh_id !== (int) $user_id) {
            $address['u'][(int) $kh_id] = 'to';
        }

        $pm_data = [
            'from_user_id'    => $from_id,
            'from_user_ip'    => '127.0.0.1',
            'from_username'   => '',
            'enable_sig'      => false,
            'enable_bbcode'   => true,
            'enable_smilies'  => true,
            'enable_urls'     => true,
            'icon_id'         => 0,
            'bbcode_bitfield' => $bitfield,
            'bbcode_uid'      => $uid,
            'message'         => $message_text,
            'address_list'    => $address,
        ];

        submit_pm('post', $subject, $pm_data, false);
    }

    protected function get_system_sender()
    {
        // Fondateur (type 3 = USER_FOUNDER) au plus petit id
        $res = $this->db->sql_query('SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_type = 3 ORDER BY user_id ASC');
        $row = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);
        if ($row) {
            return (int) $row['user_id'];
        }
        return (int) $this->user->data['user_id'];
    }
}
