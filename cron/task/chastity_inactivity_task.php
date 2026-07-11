<?php
/**
 * Chastity Tracker — Cron task : gestion de l'inactivité
 * Rappel MP + suppression automatique des périodes inactives
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\cron\task;

use phpbb\config\config;
use phpbb\cron\task\base;
use phpbb\db\driver\driver_interface;

class chastity_inactivity_task extends base
{
    /** @var config */
    protected $config;

    /** @var driver_interface */
    protected $db;

    /** @var string */
    protected $users_table;

    /** @var string */
    protected $periods_table;

    /** @var string */
    protected $phpbb_root_path;

    /** @var string */
    protected $php_ext;

    public function __construct(
        config $config,
        driver_interface $db,
        $users_table,
        $periods_table,
        $phpbb_root_path,
        $php_ext
    )
    {
        $this->config          = $config;
        $this->db              = $db;
        $this->users_table     = $users_table;
        $this->periods_table   = $periods_table;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext         = $php_ext;
    }

    public function run()
    {
        $warn_days   = max(1, (int) $this->config['chastity_inactivity_warn_days']);
        $cancel_days = max($warn_days + 1, (int) $this->config['chastity_inactivity_cancel_days']);
        $now         = time();

        // ── ÉTAPE 1 : Annulation (cancel_days) — traiter d'abord pour éviter double MP ──
        $cancel_threshold = $now - ($cancel_days * 86400);

        $sql = 'SELECT cu.user_id, cu.username, u.user_lastvisit
                FROM ' . $this->users_table . ' cu
                INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = cu.user_id
                WHERE cu.chastity_status = \'locked\'
                  AND cu.inactivity_warned = 1
                  AND u.user_lastvisit < ' . $cancel_threshold;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $user_id  = (int) $row['user_id'];
            $days_ago = (int) floor(($now - (int) $row['user_lastvisit']) / 86400);

            // Supprimer la période active
            $this->db->sql_query(
                'DELETE FROM ' . $this->periods_table
                . " WHERE user_id = $user_id AND status = 'active'"
            );

            // Recalculer les totaux
            $this->recalc_totals($user_id);

            // Envoyer le MP d'annulation
            $message = $this->config['chastity_inactivity_cancel_message'];
            $message = str_replace(
                ['{USERNAME}', '{DAYS}'],
                [$row['username'], $days_ago],
                $message
            );
            $this->send_pm($user_id, $row['username'], 'Période annulée — inactivité', $message);

            // Copie à l'admin (comme pour activités/sorties)
            $this->notify_admin('cancel', $row['username'], $days_ago, $message);

            // Remettre le flag à 0
            $this->db->sql_query(
                'UPDATE ' . $this->users_table
                . ' SET inactivity_warned = 0, updated_time = ' . $now
                . ' WHERE user_id = ' . $user_id
            );
        }
        $this->db->sql_freeresult($result);

        // ── ÉTAPE 2 : Avertissement (warn_days) ──
        $warn_threshold = $now - ($warn_days * 86400);

        $sql = 'SELECT cu.user_id, cu.username, u.user_lastvisit
                FROM ' . $this->users_table . ' cu
                INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = cu.user_id
                WHERE cu.chastity_status = \'locked\'
                  AND cu.inactivity_warned = 0
                  AND u.user_lastvisit < ' . $warn_threshold;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $user_id   = (int) $row['user_id'];
            $days_ago  = (int) floor(($now - (int) $row['user_lastvisit']) / 86400);
            $remaining = $cancel_days - $days_ago;

            $message = $this->config['chastity_inactivity_warn_message'];
            $message = str_replace(
                ['{USERNAME}', '{DAYS}', '{REMAINING}'],
                [$row['username'], $days_ago, max(0, $remaining)],
                $message
            );
            $this->send_pm($user_id, $row['username'], 'Rappel — période en cage', $message);

            // Copie à l'admin
            $this->notify_admin('warn', $row['username'], $days_ago, $message);

            $this->db->sql_query(
                'UPDATE ' . $this->users_table
                . ' SET inactivity_warned = 1, updated_time = ' . $now
                . ' WHERE user_id = ' . $user_id
            );
        }
        $this->db->sql_freeresult($result);

        $this->config->set('chastity_inactivity_last_gc', $now);
    }

    /**
     * Envoie une copie du message à l'admin (comme pour activités/sorties)
     * @param string $type 'warn' ou 'cancel'
     */
    private function notify_admin($type, $username, $days_ago, $original_message)
    {
        $admin_id = (int) ($this->config['chastity_notify_admin_id'] ?? 0);
        if ($admin_id <= 0) { return; }

        // Vérifier que l'admin existe
        $sql = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_id = ' . $admin_id;
        $result = $this->db->sql_query($sql);
        $admin = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        if (!$admin) { return; }

        if ($type === 'cancel')
        {
            $subject = '[Inactivité] Période annulée — ' . $username;
            $body    = '[b]' . $username . '[/b] ne s\'est pas connecté(e) depuis ' . $days_ago . ' jours.' . "\n\n"
                     . 'Sa période active a été automatiquement annulée et les totaux recalculés.' . "\n\n"
                     . '[i]Message envoyé à l\'utilisateur :[/i]' . "\n"
                     . '[quote]' . $original_message . '[/quote]';
        }
        else // warn
        {
            $subject = '[Inactivité] Rappel envoyé — ' . $username;
            $body    = 'Un rappel d\'inactivité a été envoyé à [b]' . $username . '[/b] (absent depuis ' . $days_ago . ' jours).' . "\n\n"
                     . '[i]Message envoyé à l\'utilisateur :[/i]' . "\n"
                     . '[quote]' . $original_message . '[/quote]';
        }

        $this->send_pm($admin_id, '', $subject, $body);
    }

    /**
     * Recalcule les totaux après suppression de la période active
     */
    private function recalc_totals(int $user_id): void
    {
        // Somme des secondes réelles, pas les days_count déjà arrondis
        // individuellement (perdrait les périodes de moins de 24h).
        $sql = 'SELECT SUM(end_date - start_date) as total_seconds FROM ' . $this->periods_table
             . " WHERE user_id = $user_id AND status = 'completed' AND end_date > start_date";
        $result     = $this->db->sql_query($sql);
        $total_seconds = (int) $this->db->sql_fetchfield('total_seconds');
        $this->db->sql_freeresult($result);
        $total_days = (int) floor($total_seconds / 86400);

        $this->db->sql_query(
            'UPDATE ' . $this->users_table
            . " SET chastity_status = 'free', chastity_current_period = 0,"
            . " chastity_total_days = $total_days, updated_time = " . time()
            . " WHERE user_id = $user_id"
        );
    }

    /**
     * Envoie un message privé à un utilisateur
     */
    private function send_pm(int $user_id, string $username, string $subject, string $message_text): void
    {
        if (!function_exists('submit_pm'))
        {
            include_once($this->phpbb_root_path . 'includes/functions_privmsgs.' . $this->php_ext);
        }

        $uid = $bitfield = $options = '';
        generate_text_for_storage($message_text, $uid, $bitfield, $options, true, true, true);

        $pm_data = [
            'from_user_id'    => ANONYMOUS,
            'from_user_ip'    => '127.0.0.1',
            'from_username'   => 'Chastity Tracker',
            'enable_sig'      => false,
            'enable_bbcode'   => true,
            'enable_smilies'  => true,
            'enable_urls'     => true,
            'icon_id'         => 0,
            'bbcode_bitfield' => $bitfield,
            'bbcode_uid'      => $uid,
            'message'         => $message_text,
            'address_list'    => ['u' => [$user_id => 'to']],
        ];

        submit_pm('post', $subject, $pm_data, false);
    }

    public function is_runnable()
    {
        return (bool) ($this->config['chastity_inactivity_enabled'] ?? 0);
    }

    public function should_run()
    {
        // Toutes les 24h
        return (int) ($this->config['chastity_inactivity_last_gc'] ?? 0) < time() - 86400;
    }
}
