<?php
/**
 * Chastity Tracker - ACP Module (corrigé)
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;
    private $chastity_users_table;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $config;

        // Initialisation
        $this->u_action = $request->variable('u_action', '');
        $user->add_lang_ext('verturin/chastitytracker', 'common');
        $this->tpl_name   = 'acp_chastity_' . $mode;
        $this->page_title = $user->lang['ACP_CHASTITY_' . strtoupper($mode)];

        $this->chastity_users_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');
        $periods_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_periods');
		$users_table   = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');		

        add_form_key('acp_chastity');

        switch ($mode)
        {
            case 'settings':
                $this->settings_mode($user, $template, $request, $config);
                break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table);
                break;

            case 'rebuild':
                $this->rebuild_mode($user, $template, $request, $db, $config, $periods_table, $phpbb_container);
                break;

			case 'backup':
				$this->backup_mode($user, $template, $request, $db, $periods_table, $users_table);
				break;
        }
    }

    private function settings_mode($user, $template, $request, $config)
    {
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_enable',                       $request->variable('chastity_enable', 1));
            $config->set('chastity_profile_display',              $request->variable('chastity_profile_display', 0));
            $config->set('chastity_min_period_days',              $request->variable('chastity_min_period_days', 0));
            $config->set('chastity_rule_masturbation_enabled',    $request->variable('chastity_rule_masturbation_enabled', 0));
            $config->set('chastity_rule_ejaculation_enabled',     $request->variable('chastity_rule_ejaculation_enabled', 0));
            $config->set('chastity_rule_sleep_removal_enabled',   $request->variable('chastity_rule_sleep_removal_enabled', 0));
            $config->set('chastity_rule_public_removal_enabled',  $request->variable('chastity_rule_public_removal_enabled', 0));
            $config->set('chastity_rule_medical_removal_enabled', $request->variable('chastity_rule_medical_removal_enabled', 0));
            $config->set('chastity_locktober_enabled',            $request->variable('chastity_locktober_enabled', 0));
            $config->set('chastity_locktober_year',               $request->variable('chastity_locktober_year', (int) date('Y')));
            $config->set('chastity_locktober_badge_enabled',      $request->variable('chastity_locktober_badge_enabled', 0));
            $config->set('chastity_locktober_leaderboard_enabled',$request->variable('chastity_locktober_leaderboard_enabled', 0));

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Assign template variables
        $template->assign_vars([
            'CHASTITY_ENABLE'                         => (int) ($config['chastity_enable'] ?? 1),
            'CHASTITY_PROFILE_DISPLAY'                => (int) ($config['chastity_profile_display'] ?? 0),
            'CHASTITY_MIN_PERIOD_DAYS'                => (int) ($config['chastity_min_period_days'] ?? 0),
            'CHASTITY_RULE_MASTURBATION_ENABLED'      => (int) ($config['chastity_rule_masturbation_enabled'] ?? 1),
            'CHASTITY_RULE_EJACULATION_ENABLED'       => (int) ($config['chastity_rule_ejaculation_enabled'] ?? 1),
            'CHASTITY_RULE_SLEEP_REMOVAL_ENABLED'     => (int) ($config['chastity_rule_sleep_removal_enabled'] ?? 1),
            'CHASTITY_RULE_PUBLIC_REMOVAL_ENABLED'    => (int) ($config['chastity_rule_public_removal_enabled'] ?? 1),
            'CHASTITY_RULE_MEDICAL_REMOVAL_ENABLED'   => (int) ($config['chastity_rule_medical_removal_enabled'] ?? 1),
            'CHASTITY_LOCKTOBER_ENABLED'              => (int) ($config['chastity_locktober_enabled'] ?? 1),
            'CHASTITY_LOCKTOBER_YEAR'                 => (int) ($config['chastity_locktober_year'] ?? date('Y')),
            'CHASTITY_LOCKTOBER_BADGE_ENABLED'        => (int) ($config['chastity_locktober_badge_enabled'] ?? 1),
            'CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED'  => (int) ($config['chastity_locktober_leaderboard_enabled'] ?? 1),
            'U_ACTION'                                => $this->u_action,
        ]);
    }

    private function rebuild_mode($user, $template, $request, $db, $config, $periods_table, $phpbb_container)
    {
        $rebuilt = 0;

        if ($request->is_set_post('rebuild'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            // Récupérer tous les utilisateurs ayant des périodes
            $sql = 'SELECT DISTINCT user_id FROM ' . $periods_table;
            $result = $db->sql_query($sql);
            $user_ids = [];
            while ($row = $db->sql_fetchrow($result))
            {
                $user_ids[] = (int) $row['user_id'];
            }
            $db->sql_freeresult($result);

            foreach ($user_ids as $uid)
            {
                // Total des jours complétés
                $sql = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                        WHERE user_id = $uid AND status = 'completed'";
                $result = $db->sql_query($sql);
                $total_days = (int) $db->sql_fetchfield('total');
                $db->sql_freeresult($result);

                // Période active
                $sql = 'SELECT period_id, start_date FROM ' . $periods_table . "
                        WHERE user_id = $uid AND status = 'active'
                        ORDER BY start_date DESC LIMIT 1";
                $result = $db->sql_query($sql);
                $active = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                if ($active)
                {
                    $start_time = is_numeric($active['start_date']) ? (int) $active['start_date'] : strtotime($active['start_date']);
                    $active_days = (int) floor((time() - $start_time) / 86400);
                    $total_days += $active_days;

                    $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                        SET chastity_status = 'locked',
                            chastity_current_period = " . (int) $active['period_id'] . ",
                            chastity_total_days = $total_days
                        WHERE user_id = $uid");
                }
                else
                {
                    $db->sql_query('UPDATE ' . $this->chastity_users_table . "
                        SET chastity_status = 'free',
                            chastity_current_period = 0,
                            chastity_total_days = $total_days
                        WHERE user_id = $uid");
                }

                $rebuilt++;
            }

            trigger_error(sprintf($user->lang['ACP_CHASTITY_REBUILD_DONE'], $rebuilt) . adm_back_link($this->u_action));
        }

        // Mise à jour cache et historique
        if ($request->is_set_post('run_cache_update'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $cache_updater = $phpbb_container->get('verturin.chastitytracker.cache_updater');
            $count = $cache_updater->update_cache();
            $config->set('chastity_cache_last_gc',   time(), true);
            trigger_error(
                sprintf($user->lang['ACP_CHASTITY_CACHE_UPDATED'], $count) . adm_back_link($this->u_action)
            );
        }

        if ($request->is_set_post('run_history_update'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $history_updater = $phpbb_container->get('verturin.chastitytracker.history_updater');
            $count = $history_updater->update_history();
            $config->set('chastity_history_last_gc', time(), true);
            trigger_error(
                sprintf($user->lang['ACP_CHASTITY_HISTORY_UPDATED'], $count) . adm_back_link($this->u_action)
            );
        }

        if ($request->is_set_post('save_cache_interval'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $config->set('chastity_cache_gc', max(1, (int) $request->variable('chastity_cache_gc', 60)));
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('save_history_interval'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $config->set('chastity_history_gc', max(1, (int) $request->variable('chastity_history_gc', 1440)));
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('toggle_cache_cron'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $current = (int) ($config['chastity_cache_cron_enabled'] ?? 1);
            $config->set('chastity_cache_cron_enabled', $current ? 0 : 1);
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('toggle_history_cron'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $current = (int) ($config['chastity_history_cron_enabled'] ?? 1);
            $config->set('chastity_history_cron_enabled', $current ? 0 : 1);
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Remettre les timers cron à l'heure actuelle pour éviter
        // un recalcul automatique dans les minutes qui suivent
        if ($rebuilt > 0)
        {
            $config->set('chastity_cache_last_gc',   time(), true);
            $config->set('chastity_history_last_gc', time(), true);
        }

        // Statistiques
        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(*) as active FROM ' . $periods_table . " WHERE status = 'active'";
        $result = $db->sql_query($sql);
        $active_count = (int) $db->sql_fetchfield('active');
        $db->sql_freeresult($result);

        $template->assign_vars([
            'REBUILD_TOTAL_USERS'      => $total_users,
            'REBUILD_ACTIVE_PERIODS'   => $active_count,
            'U_ACTION'                 => $this->u_action,
            'CHASTITY_CACHE_GC'        => (int) ($config['chastity_cache_gc']         ?? 60),
            'CHASTITY_HISTORY_GC'      => (int) ($config['chastity_history_gc']       ?? 1440),
            'S_CACHE_CRON_ENABLED'   => (bool) ($config['chastity_cache_cron_enabled'] ?? 1),
            'S_HISTORY_CRON_ENABLED' => (bool) ($config['chastity_history_cron_enabled'] ?? 1),
			'CHASTITY_CACHE_LAST_GC'   => (!empty($config['chastity_cache_last_gc']) && $config['chastity_cache_last_gc'] > 0)
                                           ? $user->format_date((int) $config['chastity_cache_last_gc'], 'd/m/Y H:i') : '-',
            'CHASTITY_HISTORY_LAST_GC' => (!empty($config['chastity_history_last_gc']) && $config['chastity_history_last_gc'] > 0)
                                           ? $user->format_date((int) $config['chastity_history_last_gc'], 'd/m/Y H:i') : '-',
        ]);
    }

    private function statistics_mode($user, $template, $db, $periods_table)
    {
        $sql = 'SELECT COUNT(*) as total_periods, SUM(days_count) as total_days FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $global = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(*) as active_periods FROM ' . $periods_table . " WHERE status = 'active'";
        $result = $db->sql_query($sql);
        $active_periods = (int) $db->sql_fetchfield('active_periods');
        $db->sql_freeresult($result);

        $sql = 'SELECT u.user_id, u.username, u.user_colour, SUM(p.days_count) as total_days, COUNT(p.period_id) as total_periods
                FROM ' . $periods_table . ' p
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
                GROUP BY p.user_id, u.username, u.user_colour, u.user_id
                ORDER BY total_days DESC
                LIMIT 10';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('top_users', [
                'USERNAME'      => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                'TOTAL_DAYS'    => (int) $row['total_days'],
                'TOTAL_PERIODS' => (int) $row['total_periods'],
            ]);
        }
        $db->sql_freeresult($result);

        $total = (int) $global['total_periods'];
        $days  = (int) $global['total_days'];

        $template->assign_vars([
            'TOTAL_PERIODS'  => $total,
            'TOTAL_DAYS'     => $days,
            'TOTAL_USERS'    => $total_users,
            'ACTIVE_PERIODS' => $active_periods,
            'AVERAGE_DAYS'   => $total > 0 ? round($days / $total, 1) : 0,
        ]);
    }

private function backup_mode($user, $template, $request, $db, $periods_table, $users_table)
{
    // ── EXPORT ──
    if ($request->is_set_post('export_backup'))
    {
        if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

        $dump  = "-- Chastity Tracker Backup\n";
        $dump .= "-- Date : " . date('Y-m-d H:i:s') . "\n\n";

        $dump .= "TRUNCATE TABLE `" . $users_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $users_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $users_table . "` VALUES ("
                . (int) $row['user_id'] . ", "
                . "'" . $db->sql_escape($row['username']) . "', "
                . "'" . $db->sql_escape($row['user_colour']) . "', "
                . "'" . $db->sql_escape($row['chastity_status']) . "', "
                . (int) $row['chastity_current_period'] . ", "
                . (int) $row['chastity_total_days'] . ", "
                . (int) $row['created_time'] . ", "
                . (int) $row['updated_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        $dump .= "\nTRUNCATE TABLE `" . $periods_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $periods_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $periods_table . "` VALUES ("
                . (int) $row['period_id'] . ", "
                . (int) $row['user_id'] . ", "
                . (int) $row['start_date'] . ", "
                . (int) $row['end_date'] . ", "
                . "'" . $db->sql_escape($row['status']) . "', "
                . (int) $row['is_permanent'] . ", "
                . (int) $row['is_locktober'] . ", "
                . (int) $row['locktober_year'] . ", "
                . (int) $row['locktober_completed'] . ", "
                . (int) $row['days_count'] . ", "
                . "'" . $db->sql_escape($row['notes']) . "', "
                . (int) $row['rule_masturbation'] . ", "
                . (int) $row['rule_ejaculation'] . ", "
                . (int) $row['rule_sleep_removal'] . ", "
                . (int) $row['rule_public_removal'] . ", "
                . (int) $row['rule_medical_removal'] . ", "
                . (int) $row['created_time'] . ", "
                . (int) $row['updated_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        $filename = 'chastity_backup_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($dump));
        echo $dump;
        exit;
    }

    // ── RESTAURATION ──
    if ($request->is_set_post('restore_backup'))
    {
        if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

        $file_data = $request->file('backup_file');
        if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name']))
        {
            trigger_error($user->lang['ACP_CHASTITY_BACKUP_NO_FILE'] . adm_back_link($this->u_action));
        }

        $sql_content = file_get_contents($file_data['tmp_name']);
        if (strpos($sql_content, 'Chastity Tracker Backup') === false)
        {
            trigger_error($user->lang['ACP_CHASTITY_BACKUP_INVALID'] . adm_back_link($this->u_action));
        }

        $lines = explode("\n", $sql_content);
        $count = 0;
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0) { continue; }
            $db->sql_query($line);
            if (strpos($line, 'INSERT') === 0) { $count++; }
        }
        trigger_error(sprintf($user->lang['ACP_CHASTITY_BACKUP_RESTORED'], $count) . adm_back_link($this->u_action));
    }

    $sql = 'SELECT COUNT(*) as total FROM ' . $users_table;
    $result = $db->sql_query($sql);
    $total_users = (int) $db->sql_fetchfield('total');
    $db->sql_freeresult($result);

    $sql = 'SELECT COUNT(*) as total FROM ' . $periods_table;
    $result = $db->sql_query($sql);
    $total_periods = (int) $db->sql_fetchfield('total');
    $db->sql_freeresult($result);

    $template->assign_vars([
        'BACKUP_USERS'   => $total_users,
        'BACKUP_PERIODS' => $total_periods,
        'U_ACTION'       => $this->u_action,
    ]);
}

}
