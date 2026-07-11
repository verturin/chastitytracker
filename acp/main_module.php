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
        // NE PAS écraser $this->u_action ici : le gestionnaire de modules
        // phpBB l'a déjà correctement renseigné (URL complète avec sid, i,
        // mode) AVANT d'appeler main(). L'ancienne ligne
        // "$this->u_action = $request->variable('u_action', '');" écrasait
        // systématiquement cette valeur par une chaîne vide (aucun paramètre
        // GET 'u_action' n'est jamais transmis), cassant tout lien <a href>
        // construit avec {{ U_ACTION }} (ex: adm/&acp_preview_contract=2 au
        // lieu de adm/index.php?...&acp_preview_contract=2). Les boutons en
        // formulaire POST (action="") semblaient fonctionner car un
        // action="" se contente de re-soumettre sur l'URL courante.
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
                $this->settings_mode($user, $template, $request, $config, $db);
                break;

            case 'locktober':
                $this->locktober_mode($user, $template, $request, $config, $db, $phpbb_container, $periods_table);
                break;

            case 'rewards':
                $this->rewards_mode($user, $template, $request, $config, $db, $phpbb_container);
                break;

            case 'statistics':
                $this->statistics_mode($user, $template, $db, $periods_table, $phpbb_container);
                break;

            case 'rebuild':
                $this->rebuild_mode($user, $template, $request, $db, $config, $periods_table, $phpbb_container);
                break;

            case 'delperiod':
                $this->delperiod_mode($user, $template, $request, $db, $periods_table, $phpbb_container);
                break;

			case 'backup':
				$this->backup_mode($user, $template, $request, $db, $periods_table, $users_table);
				break;

            case 'cageexits':
                $r_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexit_reasons');
                $this->acp_cageexits_mode($template, $request, $db, $r_table, $config, $user);
            break;

            case 'activities':
                $ar_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activity_reasons');
                $this->acp_activities_mode($template, $request, $db, $ar_table, $user);
            break;

            case 'cage_catalog':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables) {
                    trigger_error('Les tables v3.5.0 ne sont pas installées. Désactivez puis réactivez l\'extension dans ACP → Personnalisation → Extensions, puis purgez le cache.' . adm_back_link($this->u_action));
                }
                $this->acp_cage_catalog_mode($template, $request, $db, $cage_tables, $user, $config);
            break;

            case 'cage_comments':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables) {
                    trigger_error('Les tables v3.5.0 ne sont pas installées. Désactivez puis réactivez l\'extension.' . adm_back_link($this->u_action));
                }
                $this->acp_cage_comments_mode($template, $request, $db, $cage_tables, $user);
                $this->tpl_name = 'acp_chastity_cage_comments';
                $this->page_title = $user->lang['ACP_CHASTITY_CAGE_COMMENTS'];
            break;

            case 'cage_manufacturers':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables) {
                    trigger_error('Les tables v3.5.0 ne sont pas installées. Désactivez puis réactivez l\'extension dans ACP → Personnalisation → Extensions, puis purgez le cache.' . adm_back_link($this->u_action));
                }
                $this->acp_cage_manufacturers_mode($template, $request, $db, $cage_tables, $user);
            break;

            case 'cage_materials':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables || empty($cage_tables['materials'])) {
                    trigger_error('La table des matériaux n\'existe pas. Désactivez/réactivez l\'extension pour jouer la migration v3.5.1.' . adm_back_link($this->u_action));
                }
                $this->acp_cage_materials_mode($template, $request, $db, $cage_tables, $user);
            break;

            case 'keyholders':
                $kh_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
                $cu_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');
                $this->acp_keyholders_mode($template, $request, $db, $user, $kh_table, $cu_table);
                $this->tpl_name = 'acp_chastity_keyholders';
                $this->page_title = $user->lang['ACP_CHASTITY_KEYHOLDERS'];
            break;

            case 'contract':
                $contracts_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contracts');
                $links_table     = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_links');
                $articles_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_articles');
                $categories_table= $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_categories');
                $this->acp_contract_mode($template, $request, $db, $user, $contracts_table, $links_table, $articles_table, $categories_table, $phpbb_container);
                $this->tpl_name = 'acp_chastity_contract';
                $this->page_title = $user->lang['ACP_CHASTITY_CONTRACT'];
            break;
        }
    }

    private function get_cage_tables($container, $db)
    {
        try {
            $tables = [
                'manufacturers' => $container->getParameter('verturin.chastitytracker.tables.chastity_cage_manufacturers'),
                'catalog'       => $container->getParameter('verturin.chastitytracker.tables.chastity_cage_catalog'),
                'photos'        => $container->getParameter('verturin.chastitytracker.tables.chastity_cage_photos'),
                'cages'         => $container->getParameter('verturin.chastitytracker.tables.chastity_cages'),
                'usage'         => $container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage'),
            ];
            try {
                $tables['materials'] = $container->getParameter('verturin.chastitytracker.tables.chastity_cage_materials');
                $tables['ratings']   = $container->getParameter('verturin.chastitytracker.tables.chastity_cage_ratings');
            } catch (\Exception $e) {
                $tables['materials'] = '';
                $tables['ratings']   = '';
            }
        } catch (\Exception $e) {
            return false;
        }
        // Vérifier que la table catalogue existe
        $sql = "SHOW TABLES LIKE '" . $db->sql_escape($tables['catalog']) . "'";
        $res = $db->sql_query($sql);
        $exists = $db->sql_fetchrow($res);
        $db->sql_freeresult($res);
        return $exists ? $tables : false;
    }

    private function rewards_mode($user, $template, $request, $config, $db = null, $phpbb_container = null)
    {
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_rewards_enabled', $request->variable('chastity_rewards_enabled', 0));

            foreach (['cage', 'posts', 'logins'] as $type)
            {
                foreach (['day', 'month', 'year'] as $period)
                {
                    $key = 'chastity_goal_' . $type . '_' . $period;
                    $config->set($key, max(1, $request->variable($key, 1)));
                }
            }

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        $sd_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_special_days');
        $root_path = $phpbb_container->getParameter('core.root_path');
        $img_dir   = $root_path . 'ext/verturin/chastitytracker/images/special/';
        $img_url   = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/special/';

        // Options d'affichage des paliers (prochain grisé / mode compact)
        if ($request->is_set_post('submit_ms_options'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $config->set('chastity_ms_show_next', $request->variable('ms_show_next', 0));
            $config->set('chastity_ms_compact', $request->variable('ms_compact', 0));
            trigger_error($user->lang['ACP_CHASTITY_MS_OPTIONS_SAVED'] . adm_back_link($this->u_action));
        }

        // Paliers "jours consécutifs" et "jours totaux"
        $ms_dir = $root_path . 'ext/verturin/chastitytracker/images/milestones/';
        $ms_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/milestones/';
        $ms_tables = [
            'streak' => $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_streak_milestones'),
            'total'  => $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_total_milestones'),
        ];

        foreach ($ms_tables as $kind => $ms_table)
        {
            // Suppression
            if ($request->is_set_post('delete_ms_' . $kind))
            {
                if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
                $mid = (int) $request->variable('ms_id_del_' . $kind, 0);
                if ($mid > 0)
                {
                    $res = $db->sql_query('SELECT milestone_image FROM ' . $ms_table . ' WHERE milestone_id = ' . $mid);
                    if ($row = $db->sql_fetchrow($res))
                    {
                        if (!empty($row['milestone_image']) && file_exists($ms_dir . $row['milestone_image']))
                        {
                            @unlink($ms_dir . $row['milestone_image']);
                        }
                    }
                    $db->sql_freeresult($res);
                    $db->sql_query('DELETE FROM ' . $ms_table . ' WHERE milestone_id = ' . $mid);
                }
                trigger_error($user->lang['ACP_CHASTITY_MS_DELETED'] . adm_back_link($this->u_action));
            }

            // Ajout / modification
            if ($request->is_set_post('submit_ms_' . $kind))
            {
                if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
                $m_id        = (int) $request->variable('ms_id_edit_' . $kind, 0);
                $m_threshold = (int) $request->variable('ms_threshold_' . $kind, 0);
                $m_label     = $request->variable('ms_label_' . $kind, '', true);

                if ($m_threshold < 1)
                {
                    trigger_error($user->lang['ACP_CHASTITY_MS_BADTHRESHOLD'] . adm_back_link($this->u_action), E_USER_WARNING);
                }

                $m_image = '';
                if ($m_id > 0)
                {
                    $res = $db->sql_query('SELECT milestone_image FROM ' . $ms_table . ' WHERE milestone_id = ' . $m_id);
                    if ($row = $db->sql_fetchrow($res)) { $m_image = (string) $row['milestone_image']; }
                    $db->sql_freeresult($res);
                }

                $upload = $request->file('ms_image_file_' . $kind);
                if (!empty($upload['name']))
                {
                    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                    $type = isset($upload['type']) ? strtolower($upload['type']) : '';
                    if (!isset($allowed[$type]))
                    {
                        trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADIMG'] . adm_back_link($this->u_action), E_USER_WARNING);
                    }
                    if ((int) $upload['size'] <= 0 || (int) $upload['size'] > 2097152)
                    {
                        trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADSIZE'] . adm_back_link($this->u_action), E_USER_WARNING);
                    }
                    if (!is_dir($ms_dir)) { @mkdir($ms_dir, 0755, true); }
                    $fname = 'ms_' . $kind . '_' . $m_threshold . '_' . substr(md5(uniqid()), 0, 8) . '.' . $allowed[$type];
                    if (@move_uploaded_file($upload['tmp_name'], $ms_dir . $fname))
                    {
                        if ($m_image !== '' && file_exists($ms_dir . $m_image)) { @unlink($ms_dir . $m_image); }
                        $m_image = $fname;
                    }
                    else
                    {
                        trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_UPLOADFAIL'] . adm_back_link($this->u_action), E_USER_WARNING);
                    }
                }

                if ($m_id > 0)
                {
                    $db->sql_query('UPDATE ' . $ms_table . " SET
                        threshold = " . (int) $m_threshold . ",
                        milestone_label = '" . $db->sql_escape($m_label) . "',
                        milestone_image = '" . $db->sql_escape($m_image) . "',
                        updated_time = " . time() . '
                        WHERE milestone_id = ' . $m_id);
                }
                else
                {
                    $db->sql_query('INSERT INTO ' . $ms_table . ' ' . $db->sql_build_array('INSERT', [
                        'threshold'       => $m_threshold,
                        'milestone_label' => $m_label,
                        'milestone_image' => $m_image,
                        'updated_time'    => time(),
                    ]));
                }
                trigger_error($user->lang['ACP_CHASTITY_MS_SAVED'] . adm_back_link($this->u_action));
            }
        }

        // Recalcul / vérification global des récompenses
        if ($request->is_set_post('recalc_rewards'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $periods_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_periods');

            // 1) Complétion Locktober : réévalue locktober_completed pour tous
            //    les octobres déjà terminés (couverture 1er→31 octobre).
            $now = time();
            $res = $db->sql_query('SELECT DISTINCT locktober_year FROM ' . $periods_table . '
                                   WHERE is_locktober = 1 AND locktober_year > 0');
            $years = [];
            while ($row = $db->sql_fetchrow($res)) { $years[] = (int) $row['locktober_year']; }
            $db->sql_freeresult($res);

            foreach ($years as $year)
            {
                $oct_start = mktime(0, 0, 0, 10, 1, $year);
                $oct_end   = mktime(23, 59, 59, 10, 31, $year);
                if ($now <= $oct_end) { continue; }
                $cover_start = mktime(23, 59, 59, 10, 1, $year);
                $cover_end   = mktime(0, 0, 0, 10, 31, $year);

                // Marquer réussis
                $db->sql_query('UPDATE ' . $periods_table . '
                    SET locktober_completed = 1
                    WHERE is_locktober = 1 AND locktober_year = ' . (int) $year . '
                      AND start_date <= ' . (int) $cover_start . '
                      AND (end_date = 0 OR end_date >= ' . (int) $cover_end . ')');
                // Dé-marquer ceux qui ne couvrent plus octobre (période modifiée/supprimée)
                $db->sql_query('UPDATE ' . $periods_table . '
                    SET locktober_completed = 0
                    WHERE is_locktober = 1 AND locktober_year = ' . (int) $year . '
                      AND NOT (start_date <= ' . (int) $cover_start . '
                               AND (end_date = 0 OR end_date >= ' . (int) $cover_end . '))');
            }

            // 2) Périodes parfaites : reconstitution rétroactive complète
            try {
                $rewards_calc = $phpbb_container->get('verturin.chastitytracker.rewards_calculator');
                $pc_table     = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_perfect_counts');
                $rewards_calc->recalc_perfect_full($pc_table);
            } catch (\Throwable $e) {}

            // 3) Badges acquis : figer/synchroniser pour tous les membres
            try {
                $rewards_calc = $phpbb_container->get('verturin.chastitytracker.rewards_calculator');
                $kh_table_b   = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
                $ures = $db->sql_query('SELECT DISTINCT user_id FROM ' . $periods_table . ' WHERE start_date > 0');
                while ($urow = $db->sql_fetchrow($ures)) {
                    $uid_r = (int) $urow['user_id'];
                    $rewards_calc->rebuild_active_days($uid_r);
                    $rewards_calc->sync_earned_badges($uid_r, $kh_table_b);
                    $rewards_calc->refresh_earned_images($uid_r, $kh_table_b);
                }
                $db->sql_freeresult($ures);
            } catch (\Throwable $e) {}

            // Purger le cache (drapeaux/affichages)
            global $cache;
            if (isset($cache) && is_object($cache) && method_exists($cache, 'purge')) { $cache->purge(); }

            trigger_error($user->lang['ACP_CHASTITY_REWARDS_RECALC_DONE'] . adm_back_link($this->u_action));
        }

        // Suppression d'une journée spéciale
        if ($request->is_set_post('delete_sday'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $sid = (int) $request->variable('sday_id', 0);
            if ($sid > 0)
            {
                $res = $db->sql_query('SELECT sday_image FROM ' . $sd_table . ' WHERE sday_id = ' . $sid);
                if ($row = $db->sql_fetchrow($res))
                {
                    if (!empty($row['sday_image']) && file_exists($img_dir . $row['sday_image']))
                    {
                        @unlink($img_dir . $row['sday_image']);
                    }
                }
                $db->sql_freeresult($res);
                $db->sql_query('DELETE FROM ' . $sd_table . ' WHERE sday_id = ' . $sid);
            }
            trigger_error($user->lang['ACP_CHASTITY_SDAY_DELETED'] . adm_back_link($this->u_action));
        }

        // Ajout d'une journée spéciale
        // ─── Badges anniversaire (config) ───
        $bday_dir = $root_path . 'ext/verturin/chastitytracker/images/birthday/';
        if ($request->is_set_post('submit_birthday'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_birthday_enabled', $request->variable('birthday_enabled', 0));
            $config->set('chastity_birthday_self_label', $request->variable('birthday_self_label', '', true));
            $config->set('chastity_birthday_kh_label', $request->variable('birthday_kh_label', '', true));

            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $uploads = [
                'chastity_birthday_self_image' => 'birthday_self_image_file',
                'chastity_birthday_kh_image'   => 'birthday_kh_image_file',
            ];
            foreach ($uploads as $cfg => $field)
            {
                $upload = $request->file($field);
                if (empty($upload['name'])) { continue; }
                $type = isset($upload['type']) ? strtolower($upload['type']) : '';
                if (!isset($allowed[$type]))
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADIMG'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if ((int) $upload['size'] <= 0 || (int) $upload['size'] > 2097152)
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADSIZE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if (!is_dir($bday_dir)) { @mkdir($bday_dir, 0755, true); }
                $suffix = ($cfg === 'chastity_birthday_kh_image') ? 'kh' : 'self';
                $fname = 'bday_' . $suffix . '_' . substr(md5(uniqid()), 0, 8) . '.' . $allowed[$type];
                if (@move_uploaded_file($upload['tmp_name'], $bday_dir . $fname))
                {
                    $old = (string) $config[$cfg];
                    if ($old !== '' && file_exists($bday_dir . $old)) { @unlink($bday_dir . $old); }
                    $config->set($cfg, $fname);
                }
                else
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_UPLOADFAIL'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
            }

            trigger_error($user->lang['ACP_CHASTITY_BIRTHDAY_SAVED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit_sday'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $s_id    = (int) $request->variable('sday_id_edit', 0);
            $s_day   = (int) $request->variable('sday_day', 0);
            $s_month = (int) $request->variable('sday_month', 0);
            $s_label = $request->variable('sday_label', '', true);

            if ($s_day < 1 || $s_day > 31 || $s_month < 1 || $s_month > 12)
            {
                trigger_error($user->lang['ACP_CHASTITY_SDAY_BADDATE'] . adm_back_link($this->u_action), E_USER_WARNING);
            }

            // Image actuelle si modification (pour la conserver sans nouvel upload)
            $s_image = '';
            if ($s_id > 0)
            {
                $res = $db->sql_query('SELECT sday_image FROM ' . $sd_table . ' WHERE sday_id = ' . $s_id);
                if ($row = $db->sql_fetchrow($res)) { $s_image = (string) $row['sday_image']; }
                $db->sql_freeresult($res);
            }

            $upload = $request->file('sday_image_file');
            if (!empty($upload['name']))
            {
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $type = isset($upload['type']) ? strtolower($upload['type']) : '';
                if (!isset($allowed[$type]))
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADIMG'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if ((int) $upload['size'] <= 0 || (int) $upload['size'] > 2097152)
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADSIZE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if (!is_dir($img_dir)) { @mkdir($img_dir, 0755, true); }
                $fname = 'sday_' . $s_day . '_' . $s_month . '_' . substr(md5(uniqid()), 0, 8) . '.' . $allowed[$type];
                if (@move_uploaded_file($upload['tmp_name'], $img_dir . $fname))
                {
                    if ($s_image !== '' && file_exists($img_dir . $s_image)) { @unlink($img_dir . $s_image); }
                    $s_image = $fname;
                }
                else
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_UPLOADFAIL'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
            }

            if ($s_id > 0)
            {
                $db->sql_query('UPDATE ' . $sd_table . " SET
                    sday_day = " . (int) $s_day . ",
                    sday_month = " . (int) $s_month . ",
                    sday_label = '" . $db->sql_escape($s_label) . "',
                    sday_image = '" . $db->sql_escape($s_image) . "',
                    updated_time = " . time() . '
                    WHERE sday_id = ' . $s_id);
            }
            else
            {
                $db->sql_query('INSERT INTO ' . $sd_table . ' ' . $db->sql_build_array('INSERT', [
                    'sday_day'     => $s_day,
                    'sday_month'   => $s_month,
                    'sday_label'   => $s_label,
                    'sday_image'   => $s_image,
                    'updated_time' => time(),
                ]));
            }

            trigger_error($user->lang['ACP_CHASTITY_SDAY_SAVED'] . adm_back_link($this->u_action));
        }

        // Liste des journées spéciales (tolérant si la table n'existe pas encore)
        $db->sql_return_on_error(true);
        $res = $db->sql_query('SELECT sday_id, sday_day, sday_month, sday_label, sday_image FROM ' . $sd_table . ' ORDER BY sday_month ASC, sday_day ASC');
        $db->sql_return_on_error(false);
        if ($res !== false)
        {
        while ($row = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('special_day', [
                'ID'    => (int) $row['sday_id'],
                'DAY'   => (int) $row['sday_day'],
                'MONTH' => (int) $row['sday_month'],
                'DATE'  => sprintf('%02d/%02d', (int) $row['sday_day'], (int) $row['sday_month']),
                'LABEL' => $row['sday_label'],
                'IMAGE' => $row['sday_image'] ? ($img_url . $row['sday_image']) : '',
            ]);
        }
        $db->sql_freeresult($res);
        }

        $vars = [
            'CHASTITY_REWARDS_ENABLED' => (int) ($config['chastity_rewards_enabled'] ?? 0),
            'U_ACTION'                 => $this->u_action,
            'CHASTITY_BIRTHDAY_ENABLED'    => (int) ($config['chastity_birthday_enabled'] ?? 0),
            'CHASTITY_BIRTHDAY_SELF_LABEL' => (string) ($config['chastity_birthday_self_label'] ?? ''),
            'CHASTITY_BIRTHDAY_KH_LABEL'   => (string) ($config['chastity_birthday_kh_label'] ?? ''),
            'CHASTITY_BIRTHDAY_SELF_IMAGE' => ($config['chastity_birthday_self_image'] ?? '') ? (rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/birthday/' . $config['chastity_birthday_self_image']) : '',
            'CHASTITY_BIRTHDAY_KH_IMAGE'   => ($config['chastity_birthday_kh_image'] ?? '') ? (rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/birthday/' . $config['chastity_birthday_kh_image']) : '',
            'CHASTITY_MS_SHOW_NEXT'        => (int) ($config['chastity_ms_show_next'] ?? 0),
            'CHASTITY_MS_COMPACT'          => (int) ($config['chastity_ms_compact'] ?? 0),
        ];
        foreach (['cage', 'posts', 'logins'] as $type)
        {
            foreach (['day', 'month', 'year'] as $period)
            {
                $key = 'chastity_goal_' . $type . '_' . $period;
                $vars['GOAL_' . strtoupper($type) . '_' . strtoupper($period)] = (int) ($config[$key] ?? 1);
            }
        }
        // Listes des paliers streak/total pour affichage
        foreach ($ms_tables as $kind => $ms_table)
        {
            $res = $db->sql_query('SELECT milestone_id, threshold, milestone_label, milestone_image FROM ' . $ms_table . ' ORDER BY threshold ASC');
            while ($row = $db->sql_fetchrow($res))
            {
                $template->assign_block_vars('ms_' . $kind, [
                    'ID'        => (int) $row['milestone_id'],
                    'THRESHOLD' => (int) $row['threshold'],
                    'LABEL'     => $row['milestone_label'],
                    'IMAGE'     => $row['milestone_image'] ? ($ms_url . $row['milestone_image']) : '',
                ]);
            }
            $db->sql_freeresult($res);
        }

        $template->assign_vars($vars);
    }

    private function locktober_mode($user, $template, $request, $config, $db = null, $phpbb_container = null, $periods_table = null)
    {
        $lr_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_locktober_rewards');
        $root_path = $phpbb_container->getParameter('core.root_path');
        $img_dir = $root_path . 'ext/verturin/chastitytracker/images/locktober/';

        // Sauvegarde des réglages généraux
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_locktober_enabled',             $request->variable('chastity_locktober_enabled', 0));
            $config->set('chastity_locktober_test_mode',           $request->variable('chastity_locktober_test_mode', 0));
            $config->set('chastity_locktober_year',                $request->variable('chastity_locktober_year', (int) date('Y')));
            $config->set('chastity_locktober_badge_enabled',       $request->variable('chastity_locktober_badge_enabled', 0));
            $config->set('chastity_locktober_leaderboard_enabled', $request->variable('chastity_locktober_leaderboard_enabled', 0));

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Sauvegarde d'une récompense d'année (libellé + image)
        // Suppression d'une récompense d'année
        if ($request->is_set_post('delete_reward'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $del_year = (int) $request->variable('reward_year_del', 0);
            if ($del_year > 0)
            {
                $res = $db->sql_query('SELECT reward_image, reward_image_part FROM ' . $lr_table . ' WHERE locktober_year = ' . $del_year);
                if ($row = $db->sql_fetchrow($res))
                {
                    foreach (['reward_image', 'reward_image_part'] as $col)
                    {
                        if (!empty($row[$col]) && file_exists($img_dir . $row[$col]))
                        {
                            @unlink($img_dir . $row[$col]);
                        }
                    }
                }
                $db->sql_freeresult($res);
                $db->sql_query('DELETE FROM ' . $lr_table . ' WHERE locktober_year = ' . $del_year);
            }
            trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_DELETED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit_reward'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $r_year  = (int) $request->variable('reward_year', 0);
            $r_label = $request->variable('reward_label', '', true);

            if ($r_year < 2000)
            {
                trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADYEAR'] . adm_back_link($this->u_action), E_USER_WARNING);
            }

            // Images existantes (conservées si pas de nouvel upload)
            $cur = ['reward_image' => '', 'reward_image_part' => ''];
            $res = $db->sql_query('SELECT reward_image, reward_image_part FROM ' . $lr_table . ' WHERE locktober_year = ' . $r_year);
            if ($row = $db->sql_fetchrow($res))
            {
                $cur['reward_image']      = (string) $row['reward_image'];
                $cur['reward_image_part'] = (string) $row['reward_image_part'];
            }
            $db->sql_freeresult($res);

            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];

            // Deux uploads : "réussi" (reward_image_file) et "participé" (reward_image_part_file)
            $uploads = [
                'reward_image'      => 'reward_image_file',
                'reward_image_part' => 'reward_image_part_file',
            ];
            foreach ($uploads as $col => $field)
            {
                $upload = $request->file($field);
                if (empty($upload['name'])) { continue; }

                $type = isset($upload['type']) ? strtolower($upload['type']) : '';
                if (!isset($allowed[$type]))
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADIMG'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if ((int) $upload['size'] <= 0 || (int) $upload['size'] > 2097152)
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADSIZE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if (!is_dir($img_dir)) { @mkdir($img_dir, 0755, true); }

                $suffix = ($col === 'reward_image_part') ? 'p' : 'r';
                $fname = 'locktober_' . $r_year . '_' . $suffix . '_' . substr(md5(uniqid()), 0, 8) . '.' . $allowed[$type];
                if (@move_uploaded_file($upload['tmp_name'], $img_dir . $fname))
                {
                    if ($cur[$col] !== '' && file_exists($img_dir . $cur[$col])) { @unlink($img_dir . $cur[$col]); }
                    $cur[$col] = $fname;
                }
                else
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_UPLOADFAIL'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
            }

            // UPSERT manuel (PK = locktober_year)
            $res = $db->sql_query('SELECT locktober_year FROM ' . $lr_table . ' WHERE locktober_year = ' . $r_year);
            $exists = (bool) $db->sql_fetchrow($res);
            $db->sql_freeresult($res);

            if ($exists)
            {
                $db->sql_query('UPDATE ' . $lr_table . " SET
                    reward_label = '" . $db->sql_escape($r_label) . "',
                    reward_image = '" . $db->sql_escape($cur['reward_image']) . "',
                    reward_image_part = '" . $db->sql_escape($cur['reward_image_part']) . "',
                    updated_time = " . time() . '
                    WHERE locktober_year = ' . $r_year);
            }
            else
            {
                $db->sql_query('INSERT INTO ' . $lr_table . ' ' . $db->sql_build_array('INSERT', [
                    'locktober_year'    => $r_year,
                    'reward_label'      => $r_label,
                    'reward_image'      => $cur['reward_image'],
                    'reward_image_part' => $cur['reward_image_part'],
                    'updated_time'      => time(),
                ]));
            }

            trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_SAVED'] . adm_back_link($this->u_action));
        }

        $lm_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_locktober_milestones');

        // Recalcul des récompenses Locktober (drapeau locktober_completed)
        if ($request->is_set_post('recalc_locktober'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $now = time();
            $res = $db->sql_query('SELECT MIN(start_date) AS first FROM ' . $periods_table . ' WHERE start_date > 0');
            $row = $db->sql_fetchrow($res);
            $first_year = $row && $row['first'] ? (int) date('Y', (int) $row['first']) : (int) date('Y');
            $db->sql_freeresult($res);

            for ($y = $first_year; $y <= (int) date('Y', $now); $y++)
            {
                $oct_start = mktime(0, 0, 0, 10, 1, $y);
                $oct_end   = mktime(23, 59, 59, 10, 31, $y);
                if ($now <= $oct_end) { continue; }
                $cover_start = mktime(23, 59, 59, 10, 1, $y);
                $cover_end   = mktime(0, 0, 0, 10, 31, $y);

                $db->sql_query('UPDATE ' . $periods_table . '
                    SET locktober_completed = 1
                    WHERE is_locktober = 1 AND locktober_year = ' . (int) $y . '
                      AND start_date <= ' . (int) $cover_start . '
                      AND (end_date = 0 OR end_date >= ' . (int) $cover_end . ')');
                $db->sql_query('UPDATE ' . $periods_table . '
                    SET locktober_completed = 0
                    WHERE is_locktober = 1 AND locktober_year = ' . (int) $y . '
                      AND NOT (start_date <= ' . (int) $cover_start . '
                               AND (end_date = 0 OR end_date >= ' . (int) $cover_end . '))');
            }

            global $cache;
            if (isset($cache) && is_object($cache) && method_exists($cache, 'purge')) { $cache->purge(); }

            trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_RECALC_DONE'] . adm_back_link($this->u_action));
        }

        // Suppression d'un palier
        if ($request->is_set_post('delete_milestone'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }
            $mid = (int) $request->variable('milestone_id', 0);
            if ($mid > 0)
            {
                $res = $db->sql_query('SELECT milestone_image FROM ' . $lm_table . ' WHERE milestone_id = ' . $mid);
                if ($row = $db->sql_fetchrow($res))
                {
                    if (!empty($row['milestone_image']) && file_exists($img_dir . $row['milestone_image']))
                    {
                        @unlink($img_dir . $row['milestone_image']);
                    }
                }
                $db->sql_freeresult($res);
                $db->sql_query('DELETE FROM ' . $lm_table . ' WHERE milestone_id = ' . $mid);
            }
            trigger_error($user->lang['ACP_CHASTITY_MILESTONE_DELETED'] . adm_back_link($this->u_action));
        }

        // Ajout d'un palier
        if ($request->is_set_post('submit_milestone'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $m_threshold = (int) $request->variable('milestone_threshold', 0);
            $m_label     = $request->variable('milestone_label', '', true);

            if ($m_threshold < 1)
            {
                trigger_error($user->lang['ACP_CHASTITY_MILESTONE_BADTHRESHOLD'] . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $m_image = '';
            $upload = $request->file('milestone_image_file');
            if (!empty($upload['name']))
            {
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $type = isset($upload['type']) ? strtolower($upload['type']) : '';
                if (!isset($allowed[$type]))
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADIMG'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if ((int) $upload['size'] <= 0 || (int) $upload['size'] > 2097152)
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_BADSIZE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
                if (!is_dir($img_dir)) { @mkdir($img_dir, 0755, true); }
                $fname = 'milestone_' . $m_threshold . '_' . substr(md5(uniqid()), 0, 8) . '.' . $allowed[$type];
                if (@move_uploaded_file($upload['tmp_name'], $img_dir . $fname))
                {
                    $m_image = $fname;
                }
                else
                {
                    trigger_error($user->lang['ACP_CHASTITY_LOCKTOBER_REWARD_UPLOADFAIL'] . adm_back_link($this->u_action), E_USER_WARNING);
                }
            }

            $db->sql_query('INSERT INTO ' . $lm_table . ' ' . $db->sql_build_array('INSERT', [
                'threshold'       => $m_threshold,
                'milestone_label' => $m_label,
                'milestone_image' => $m_image,
                'updated_time'    => time(),
            ]));

            trigger_error($user->lang['ACP_CHASTITY_MILESTONE_SAVED'] . adm_back_link($this->u_action));
        }

        // Récompenses existantes indexées par année
        $rewards = [];
        $res = $db->sql_query('SELECT locktober_year, reward_label, reward_image, reward_image_part FROM ' . $lr_table);
        while ($row = $db->sql_fetchrow($res))
        {
            $rewards[(int) $row['locktober_year']] = $row;
        }
        $db->sql_freeresult($res);

        // Tableau des éditions passées : participants (inscrits) + réussis (couverture octobre)
        $img_url = rtrim(generate_board_url(), '/') . '/ext/verturin/chastitytracker/images/locktober/';
        $editions = [];
        $now = time();

        // Participants = inscrits Locktober, par année
        $sql = 'SELECT locktober_year, COUNT(DISTINCT user_id) AS participants
                FROM ' . $periods_table . '
                WHERE is_locktober = 1 AND locktober_year > 0
                GROUP BY locktober_year';
        $res = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($res))
        {
            $editions[(int) $row['locktober_year']] = [
                'participants' => (int) $row['participants'],
                'success'      => 0,
            ];
        }
        $db->sql_freeresult($res);

        // Réussites = membres dont une période couvre tout octobre, pour chaque
        // année écoulée (octobre terminé). On part de la plus ancienne période.
        $res = $db->sql_query('SELECT MIN(start_date) AS first FROM ' . $periods_table . ' WHERE start_date > 0');
        $row = $db->sql_fetchrow($res);
        $first_year = $row && $row['first'] ? (int) date('Y', (int) $row['first']) : (int) date('Y');
        $db->sql_freeresult($res);

        for ($y = $first_year; $y <= (int) date('Y', $now); $y++)
        {
            $oct_start = mktime(0, 0, 0, 10, 1, $y);
            $oct_end   = mktime(23, 59, 59, 10, 31, $y);
            if ($now <= $oct_end) { continue; }
            $cover_start = mktime(23, 59, 59, 10, 1, $y);
            $cover_end   = mktime(0, 0, 0, 10, 31, $y);

            $sql = 'SELECT COUNT(DISTINCT user_id) AS nb FROM ' . $periods_table . '
                    WHERE start_date <= ' . (int) $cover_start . '
                      AND (end_date = 0 OR end_date >= ' . (int) $cover_end . ')';
            $res = $db->sql_query($sql);
            $nb = (int) $db->sql_fetchfield('nb');
            $db->sql_freeresult($res);

            if ($nb > 0)
            {
                if (!isset($editions[$y])) { $editions[$y] = ['participants' => 0, 'success' => 0]; }
                $editions[$y]['success'] = $nb;
            }
        }

        // Inclure aussi les années qui ont une récompense définie mais 0 participant
        foreach (array_keys($rewards) as $ry)
        {
            if (!isset($editions[$ry]))
            {
                $editions[$ry] = ['participants' => 0, 'success' => 0];
            }
        }
        krsort($editions);

        foreach ($editions as $y => $e)
        {
            $rw = $rewards[$y] ?? ['reward_label' => '', 'reward_image' => '', 'reward_image_part' => ''];
            $template->assign_block_vars('lk_edition', [
                'YEAR'         => $y,
                'PARTICIPANTS' => (int) $e['participants'],
                'SUCCESS'      => (int) $e['success'],
                'REWARD_LABEL' => $rw['reward_label'],
                'REWARD_IMAGE' => $rw['reward_image'] ? ($img_url . $rw['reward_image']) : '',
                'REWARD_IMAGE_PART' => $rw['reward_image_part'] ? ($img_url . $rw['reward_image_part']) : '',
                'HAS_REWARD'   => ($rw['reward_label'] !== '' || $rw['reward_image'] !== '' || $rw['reward_image_part'] !== ''),
            ]);
        }

        // Liste des paliers de fidélité
        $res = $db->sql_query('SELECT milestone_id, threshold, milestone_label, milestone_image FROM ' . $lm_table . ' ORDER BY threshold ASC');
        while ($row = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('lk_milestone', [
                'ID'        => (int) $row['milestone_id'],
                'THRESHOLD' => (int) $row['threshold'],
                'LABEL'     => $row['milestone_label'],
                'IMAGE'     => $row['milestone_image'] ? ($img_url . $row['milestone_image']) : '',
            ]);
        }
        $db->sql_freeresult($res);

        $template->assign_vars([
            'CHASTITY_LOCKTOBER_ENABLED'             => (int) ($config['chastity_locktober_enabled'] ?? 0),
            'CHASTITY_LOCKTOBER_TEST_MODE'           => (int) ($config['chastity_locktober_test_mode'] ?? 0),
            'CHASTITY_LOCKTOBER_YEAR'                => (int) ($config['chastity_locktober_year'] ?? date('Y')),
            'CHASTITY_LOCKTOBER_BADGE_ENABLED'       => (int) ($config['chastity_locktober_badge_enabled'] ?? 0),
            'CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED' => (int) ($config['chastity_locktober_leaderboard_enabled'] ?? 0),
            'U_ACTION'                               => $this->u_action,
        ]);
    }

    private function settings_mode($user, $template, $request, $config, $db = null)
    {
        // Réinitialiser les couleurs aux valeurs par défaut
        if ($request->is_set_post('reset_colors'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $config->set('chastity_color_cageexit', 'FFF3CD');
            $config->set('chastity_color_activity', 'EDE0F7');
            $config->set('chastity_color_mixed',    'F5E6D3');
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Enregistrer les couleurs
        if ($request->is_set_post('save_colors'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $col_ce  = strtoupper(ltrim(preg_replace('/[^0-9A-Fa-f]/', '', $request->variable('color_cageexit', 'FFF3CD')), '#'));
            $col_act = strtoupper(ltrim(preg_replace('/[^0-9A-Fa-f]/', '', $request->variable('color_activity', 'EDE0F7')), '#'));
            $col_mix = strtoupper(ltrim(preg_replace('/[^0-9A-Fa-f]/', '', $request->variable('color_mixed',    'F5E6D3')), '#'));
            if (strlen($col_ce)  === 6) { $config->set('chastity_color_cageexit', $col_ce); }
            if (strlen($col_act) === 6) { $config->set('chastity_color_activity', $col_act); }
            if (strlen($col_mix) === 6) { $config->set('chastity_color_mixed', $col_mix); }
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('acp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $config->set('chastity_enable',                       $request->variable('chastity_enable', 1));
            $config->set('chastity_record_pm_enabled',            $request->variable('chastity_record_pm_enabled', 1));
            $config->set('chastity_pdf_method',                   $request->variable('chastity_pdf_method', 'html'));
            $config->set('chastity_profile_display',              $request->variable('chastity_profile_display', 0));
            $config->set('chastity_min_period_days',              $request->variable('chastity_min_period_days', 0));
            $config->set('chastity_rule_masturbation_enabled',    $request->variable('chastity_rule_masturbation_enabled', 0));
            $config->set('chastity_rule_ejaculation_enabled',     $request->variable('chastity_rule_ejaculation_enabled', 0));
            $config->set('chastity_rule_sleep_removal_enabled',   $request->variable('chastity_rule_sleep_removal_enabled', 0));
            $config->set('chastity_rule_public_removal_enabled',  $request->variable('chastity_rule_public_removal_enabled', 0));
            $config->set('chastity_rule_medical_removal_enabled', $request->variable('chastity_rule_medical_removal_enabled', 0));
            $config->set('chastity_notify_admin_id', $request->variable('chastity_notify_admin_id', 0));
            $config->set('chastity_badge_enabled',   $request->variable('chastity_badge_enabled', 0));
            $config->set('chastity_inactivity_enabled',       $request->variable('chastity_inactivity_enabled', 0));
            $config->set('chastity_inactivity_warn_days',     $request->variable('chastity_inactivity_warn_days', 10));
            $config->set('chastity_inactivity_cancel_days',   $request->variable('chastity_inactivity_cancel_days', 20));
            $config->set('chastity_inactivity_warn_message',  $request->variable('chastity_inactivity_warn_message', '', true));
            $config->set('chastity_inactivity_cancel_message', $request->variable('chastity_inactivity_cancel_message', '', true));

            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Charger la liste des admins pour le combo notification
        if ($db)
        {
            $sql_admins = 'SELECT u.user_id, u.username
                           FROM ' . USERS_TABLE . ' u
                           JOIN ' . USER_GROUP_TABLE . ' ug ON ug.user_id = u.user_id
                           JOIN ' . GROUPS_TABLE . ' g ON g.group_id = ug.group_id
                           WHERE g.group_name = \'ADMINISTRATORS\'
                           AND ug.user_pending = 0
                           ORDER BY u.username ASC';
            $result_admins = $db->sql_query($sql_admins);
            while ($row_admin = $db->sql_fetchrow($result_admins))
            {
                $template->assign_block_vars('admin_list', [
                    'USER_ID'  => (int) $row_admin['user_id'],
                    'USERNAME' => $row_admin['username'],
                ]);
            }
            $db->sql_freeresult($result_admins);
        }

        // Assign template variables
        $template->assign_vars([
            'CHASTITY_ENABLE'                         => (int) ($config['chastity_enable'] ?? 1),
            'CHASTITY_RECORD_PM_ENABLED'              => (int) ($config['chastity_record_pm_enabled'] ?? 1),
            'CHASTITY_PDF_METHOD'                      => $config['chastity_pdf_method'] ?? 'html',
            'CHASTITY_PROFILE_DISPLAY'                => (int) ($config['chastity_profile_display'] ?? 0),
            'CHASTITY_MIN_PERIOD_DAYS'                => (int) ($config['chastity_min_period_days'] ?? 0),
            'CHASTITY_RULE_MASTURBATION_ENABLED'      => (int) ($config['chastity_rule_masturbation_enabled'] ?? 1),
            'CHASTITY_RULE_EJACULATION_ENABLED'       => (int) ($config['chastity_rule_ejaculation_enabled'] ?? 1),
            'CHASTITY_RULE_SLEEP_REMOVAL_ENABLED'     => (int) ($config['chastity_rule_sleep_removal_enabled'] ?? 1),
            'CHASTITY_RULE_PUBLIC_REMOVAL_ENABLED'    => (int) ($config['chastity_rule_public_removal_enabled'] ?? 1),
            'CHASTITY_RULE_MEDICAL_REMOVAL_ENABLED'   => (int) ($config['chastity_rule_medical_removal_enabled'] ?? 1),
            'U_ACTION'                                => $this->u_action,
            'COLOR_CAGEEXIT'                          => (!empty($config['chastity_color_cageexit'])) ? $config['chastity_color_cageexit'] : 'FFF3CD',
            'COLOR_ACTIVITY'                          => (!empty($config['chastity_color_activity'])) ? $config['chastity_color_activity'] : 'EDE0F7',
            'COLOR_MIXED'                             => (!empty($config['chastity_color_mixed']))    ? $config['chastity_color_mixed']    : 'F5E6D3',
            'CHASTITY_NOTIFY_ADMIN_ID'                => (int) ($config['chastity_notify_admin_id'] ?? 0),
            'CHASTITY_BADGE_ENABLED'                  => (int) ($config['chastity_badge_enabled'] ?? 1),
            'CHASTITY_INACTIVITY_ENABLED'             => (int) ($config['chastity_inactivity_enabled'] ?? 0),
            'CHASTITY_INACTIVITY_WARN_DAYS'           => (int) ($config['chastity_inactivity_warn_days'] ?? 10),
            'CHASTITY_INACTIVITY_CANCEL_DAYS'         => (int) ($config['chastity_inactivity_cancel_days'] ?? 20),
            'CHASTITY_INACTIVITY_WARN_MESSAGE'        => $config['chastity_inactivity_warn_message'] ?? '',
            'CHASTITY_INACTIVITY_CANCEL_MESSAGE'      => $config['chastity_inactivity_cancel_message'] ?? '',
            'COLOR_DEFAULT_CAGEEXIT'                  => 'FFF3CD',
            'COLOR_DEFAULT_ACTIVITY'                  => 'EDE0F7',
            'COLOR_DEFAULT_MIXED'                     => 'F5E6D3',
        ]);
    }

    /**
     * Suppression manuelle d'une période d'un membre (sans SQL) + recalcul complet.
     */
    private function delperiod_mode($user, $template, $request, $db, $periods_table, $phpbb_container)
    {
        $users_table   = USERS_TABLE;
        $cu_table      = $this->chastity_users_table;
        $history_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_history');

        $selected_user = $request->variable('sel_user', 0);

        // --- Action : suppression d'une période ---
        if ($request->is_set_post('delete_period'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

            $period_id = $request->variable('period_id', 0);
            $del_uid   = $request->variable('del_user', 0);

            if ($period_id > 0 && $del_uid > 0)
            {
                // Sécurité : la période appartient bien à ce membre
                $sql = 'SELECT period_id FROM ' . $periods_table
                     . ' WHERE period_id = ' . (int) $period_id . ' AND user_id = ' . (int) $del_uid;
                $res = $db->sql_query($sql);
                $ok  = (bool) $db->sql_fetchrow($res);
                $db->sql_freeresult($res);

                if ($ok)
                {
                    $db->sql_query('DELETE FROM ' . $periods_table . ' WHERE period_id = ' . (int) $period_id);
                    $this->recalc_user($db, $phpbb_container, (int) $del_uid, $periods_table, $cu_table, $history_table);
                    $selected_user = (int) $del_uid;

                    $template->assign_var('DELPERIOD_DONE', $user->lang('ACP_CHASTITY_DELPERIOD_DONE'));
                }
            }
        }

        // --- Liste déroulante des membres ayant (ou ayant eu) des périodes ---
        $sql = 'SELECT DISTINCT p.user_id, u.username, u.username_clean
                FROM ' . $periods_table . ' p
                INNER JOIN ' . $users_table . ' u ON u.user_id = p.user_id
                ORDER BY u.username_clean ASC';
        $res = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('memberlist', [
                'USER_ID'  => (int) $row['user_id'],
                'USERNAME' => $row['username'],
                'SELECTED' => ((int) $row['user_id'] === $selected_user),
            ]);
        }
        $db->sql_freeresult($res);

        // --- Périodes du membre sélectionné ---
        if ($selected_user > 0)
        {
            $sql = 'SELECT username FROM ' . $users_table . ' WHERE user_id = ' . (int) $selected_user;
            $res = $db->sql_query($sql);
            $sel_name = (string) $db->sql_fetchfield('username');
            $db->sql_freeresult($res);

            $template->assign_vars([
                'S_USER_SELECTED' => true,
                'SEL_USER_ID'     => $selected_user,
                'SEL_USERNAME'    => $sel_name,
            ]);

            $sql = 'SELECT period_id, start_date, end_date, status, days_count
                    FROM ' . $periods_table . '
                    WHERE user_id = ' . (int) $selected_user . '
                    ORDER BY start_date DESC';
            $res = $db->sql_query($sql);
            while ($row = $db->sql_fetchrow($res))
            {
                $start = (int) $row['start_date'];
                $end   = (int) $row['end_date'];
                $template->assign_block_vars('periods', [
                    'PERIOD_ID'  => (int) $row['period_id'],
                    'START'      => $start > 0 ? date('d/m/Y H:i', $start) : '-',
                    'END'        => ($row['status'] === 'active') ? $user->lang('ACP_CHASTITY_DELPERIOD_ACTIVE') : ($end > 0 ? date('d/m/Y H:i', $end) : '-'),
                    'STATUS'     => $row['status'],
                    'DAYS'       => (int) $row['days_count'],
                ]);
            }
            $db->sql_freeresult($res);
        }

        add_form_key('acp_chastity');
        $this->tpl_name = 'acp_chastity_delperiod';
    }

    /**
     * Recalcule TOUT pour un membre après modification de ses périodes :
     * total, statut, cache et historique annuel. Purge l'historique d'abord
     * pour supprimer les années devenues orphelines.
     */
    private function recalc_user($db, $phpbb_container, $user_id, $periods_table, $cu_table, $history_table)
    {
        $user_id = (int) $user_id;

        // 1) Total des jours complétés : somme des SECONDES RÉELLES (pas les
        // days_count déjà arrondis individuellement, pour ne pas perdre les
        // périodes de moins de 24h dans le cumul).
        $sql = 'SELECT SUM(end_date - start_date) as total_seconds FROM ' . $periods_table
             . " WHERE user_id = $user_id AND status = 'completed' AND end_date > start_date";
        $res = $db->sql_query($sql);
        $total_seconds = (int) $db->sql_fetchfield('total_seconds');
        $db->sql_freeresult($res);
        $total_days = (int) floor($total_seconds / 86400);

        // 2) Période active éventuelle
        $sql = 'SELECT period_id, start_date FROM ' . $periods_table
             . " WHERE user_id = $user_id AND status = 'active' ORDER BY start_date DESC LIMIT 1";
        $res = $db->sql_query($sql);
        $active = $db->sql_fetchrow($res);
        $db->sql_freeresult($res);

        if ($active)
        {
            $total_days += (int) floor((time() - (int) $active['start_date']) / 86400);
            $db->sql_query('UPDATE ' . $cu_table . " SET chastity_status = 'locked',"
                . ' chastity_current_period = ' . (int) $active['period_id'] . ','
                . " chastity_total_days = $total_days, updated_time = " . time()
                . " WHERE user_id = $user_id");
        }
        else
        {
            $db->sql_query('UPDATE ' . $cu_table . " SET chastity_status = 'free',"
                . " chastity_current_period = 0, chastity_total_days = $total_days, updated_time = " . time()
                . " WHERE user_id = $user_id");
        }

        // 3) Purger l'historique annuel du membre, puis le recalculer
        //    (sinon les années sans période restante gardent leurs vieilles valeurs)
        $db->sql_query('DELETE FROM ' . $history_table . ' WHERE user_id = ' . $user_id);
        try {
            $history = $phpbb_container->get('verturin.chastitytracker.history_updater');
            $history->update_user_history($user_id);
        } catch (\Throwable $e) {}

        // 4) Rafraîchir le cache d'affichage du membre
        try {
            $cache = $phpbb_container->get('verturin.chastitytracker.cache_updater');
            $cache->update_user_cache($user_id);
        } catch (\Throwable $e) {}
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

            // Réparation préalable : corriger les périodes "completed" dont
            // days_count est incohérent avec start_date/end_date (par exemple
            // suite à une modification de période), et les éventuelles
            // périodes "completed" avec end_date = 0 (état invalide, remises
            // en "active" pour que le membre les re-clôture correctement).
            $db->sql_query("UPDATE " . $periods_table . "
                SET status = 'active'
                WHERE status = 'completed' AND (end_date IS NULL OR end_date = 0)");

            $fix_res = $db->sql_query("SELECT period_id, start_date, end_date FROM " . $periods_table . "
                WHERE status = 'completed' AND end_date > start_date AND start_date > 0");
            while ($fix_row = $db->sql_fetchrow($fix_res))
            {
                $correct_days = (int) floor(((int) $fix_row['end_date'] - (int) $fix_row['start_date']) / 86400);
                $db->sql_query('UPDATE ' . $periods_table . '
                    SET days_count = ' . $correct_days . '
                    WHERE period_id = ' . (int) $fix_row['period_id']);
            }
            $db->sql_freeresult($fix_res);

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
                // Total des jours complétés : secondes réelles, pas les
                // days_count déjà arrondis individuellement.
                $sql = 'SELECT SUM(end_date - start_date) as total_seconds FROM ' . $periods_table . "
                        WHERE user_id = $uid AND status = 'completed' AND end_date > start_date";
                $result = $db->sql_query($sql);
                $total_seconds = (int) $db->sql_fetchfield('total_seconds');
                $db->sql_freeresult($result);
                $total_days = (int) floor($total_seconds / 86400);

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

    private function statistics_mode($user, $template, $db, $periods_table, $phpbb_container = null)
    {
        // Récupérer les noms des tables annexes (avec fallback si non dispo)
        $prefs_table = $cageexits_table = $activities_table = '';
        if ($phpbb_container) {
            try { $prefs_table      = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_user_prefs'); } catch (\Exception $e) {}
            try { $cageexits_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexits'); } catch (\Exception $e) {}
            try { $activities_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activities'); } catch (\Exception $e) {}
        }

        $sql = 'SELECT COUNT(*) as total_periods FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $global = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $sql = 'SELECT SUM(end_date - start_date) as total_seconds FROM ' . $periods_table . " WHERE end_date > start_date";
        $result = $db->sql_query($sql);
        $global_seconds = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        $global['total_days'] = isset($global_seconds['total_seconds']) ? (int) floor((int) $global_seconds['total_seconds'] / 86400) : 0;

        $sql = 'SELECT COUNT(DISTINCT user_id) as total_users FROM ' . $periods_table;
        $result = $db->sql_query($sql);
        $total_users = (int) $db->sql_fetchfield('total_users');
        $db->sql_freeresult($result);

        $sql = 'SELECT COUNT(*) as active_periods FROM ' . $periods_table . " WHERE status = 'active'";
        $result = $db->sql_query($sql);
        $active_periods = (int) $db->sql_fetchfield('active_periods');
        $db->sql_freeresult($result);

        // Top 50 avec calcul temps réel des périodes actives
        $sql = 'SELECT p.user_id, p.start_date, p.status, p.days_count,
                       u.username, u.user_colour
                FROM ' . $periods_table . ' p
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id';
        $result = $db->sql_query($sql);

        $user_data = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $uid = (int) $row['user_id'];
            if (!isset($user_data[$uid]))
            {
                $user_data[$uid] = [
                    'username'      => $row['username'],
                    'user_colour'   => $row['user_colour'],
                    'total_days'    => 0,
                    'total_periods' => 0,
                    'has_active'    => false,
                ];
            }
            $user_data[$uid]['total_periods']++;
            if ($row['status'] === 'active')
            {
                $user_data[$uid]['total_days'] += (int) floor((time() - (int) $row['start_date']) / 86400);
                $user_data[$uid]['has_active'] = true;
            }
            else
            {
                $user_data[$uid]['total_days'] += (int) $row['days_count'];
            }
        }
        $db->sql_freeresult($result);

        uasort($user_data, function ($a, $b) { return $b['total_days'] - $a['total_days']; });

        // Compteurs API activé / sorties / activités par utilisateur (sur le top 50)
        $api_users = [];
        $exits_by_user = [];
        $activities_by_user = [];
        $top_uids = array_slice(array_keys($user_data), 0, 50);
        if (!empty($top_uids))
        {
            if ($prefs_table) {
                try {
                    $sql_api = 'SELECT user_id FROM ' . $prefs_table . ' WHERE api_enabled = 1 AND ' . $db->sql_in_set('user_id', $top_uids);
                    $r = $db->sql_query($sql_api);
                    while ($row = $db->sql_fetchrow($r)) { $api_users[(int) $row['user_id']] = true; }
                    $db->sql_freeresult($r);
                } catch (\Exception $e) {}
            }
            if ($cageexits_table) {
                try {
                    $sql_e = 'SELECT user_id, COUNT(*) AS cnt FROM ' . $cageexits_table . ' WHERE ' . $db->sql_in_set('user_id', $top_uids) . ' GROUP BY user_id';
                    $r = $db->sql_query($sql_e);
                    while ($row = $db->sql_fetchrow($r)) { $exits_by_user[(int) $row['user_id']] = (int) $row['cnt']; }
                    $db->sql_freeresult($r);
                } catch (\Exception $e) {}
            }
            if ($activities_table) {
                try {
                    $sql_a = 'SELECT user_id, COUNT(*) AS cnt FROM ' . $activities_table . ' WHERE ' . $db->sql_in_set('user_id', $top_uids) . ' GROUP BY user_id';
                    $r = $db->sql_query($sql_a);
                    while ($row = $db->sql_fetchrow($r)) { $activities_by_user[(int) $row['user_id']] = (int) $row['cnt']; }
                    $db->sql_freeresult($r);
                } catch (\Exception $e) {}
            }
        }

        $rank = 1;
        foreach (array_slice($user_data, 0, 50, true) as $uid => $data)
        {
            $template->assign_block_vars('top_users', [
                'RANK'          => $rank++,
                'USERNAME'      => get_username_string('full', $uid, $data['username'], $data['user_colour']),
                'TOTAL_DAYS'    => $data['total_days'],
                'TOTAL_PERIODS' => $data['total_periods'],
                'HAS_ACTIVE'    => $data['has_active'],
                'API_ENABLED'   => isset($api_users[$uid]),
                'CAGE_EXITS'    => isset($exits_by_user[$uid]) ? $exits_by_user[$uid] : 0,
                'ACTIVITIES'    => isset($activities_by_user[$uid]) ? $activities_by_user[$uid] : 0,
            ]);
        }

        $total = (int) $global['total_periods'];
        $days  = (int) $global['total_days'];

        // Ajouter les jours des périodes actives au total global
        $sql_active = 'SELECT start_date FROM ' . $periods_table . " WHERE status = 'active'";
        $result_active = $db->sql_query($sql_active);
        while ($row_active = $db->sql_fetchrow($result_active))
        {
            $days += (int) floor((time() - (int) $row_active['start_date']) / 86400);
        }
        $db->sql_freeresult($result_active);

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
                . (int) $row['updated_time'] . ", "
                . (int) (isset($row['inactivity_warned']) ? $row['inactivity_warned'] : 0)
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

        // ── CageExit reasons ──
        $ce_r_table = str_replace('chastity_periods', 'chastity_cageexit_reasons', $periods_table);
        $dump .= "\nTRUNCATE TABLE `" . $ce_r_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $ce_r_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $ce_r_table . "` VALUES ("
                . (int) $row['reason_id'] . ", "
                . "'" . $db->sql_escape($row['label']) . "', "
                . (int) $row['is_global'] . ", "
                . (int) $row['user_id'] . ", "
                . (int) $row['is_approved'] . ", "
                . (int) $row['created_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        // ── CageExits ──
        $ce_table = str_replace('chastity_periods', 'chastity_cageexits', $periods_table);
        $dump .= "\nTRUNCATE TABLE `" . $ce_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $ce_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $ce_table . "` VALUES ("
                . (int) $row['cageexit_id'] . ", "
                . (int) $row['user_id'] . ", "
                . (int) $row['period_id'] . ", "
                . (int) $row['cageexit_date'] . ", "
                . (int) $row['duration_min'] . ", "
                . (int) $row['reason_id'] . ", "
                . "'" . $db->sql_escape($row['notes']) . "', "
                . (int) $row['auto_closed'] . ", "
                . (int) $row['created_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        // ── Activity reasons ──
        $act_r_table = str_replace('chastity_periods', 'chastity_activity_reasons', $periods_table);
        $dump .= "\nTRUNCATE TABLE `" . $act_r_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $act_r_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $act_r_table . "` VALUES ("
                . (int) $row['reason_id'] . ", "
                . "'" . $db->sql_escape($row['label']) . "', "
                . (int) $row['is_global'] . ", "
                . (int) $row['user_id'] . ", "
                . (int) $row['is_approved'] . ", "
                . (int) $row['created_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        // ── Activities ──
        $act_table = str_replace('chastity_periods', 'chastity_activities', $periods_table);
        $dump .= "\nTRUNCATE TABLE `" . $act_table . "`; \n";
        $result = $db->sql_query('SELECT * FROM ' . $act_table);
        while ($row = $db->sql_fetchrow($result))
        {
            $dump .= "INSERT INTO `" . $act_table . "` VALUES ("
                . (int) $row['activity_id'] . ", "
                . (int) $row['user_id'] . ", "
                . (int) $row['period_id'] . ", "
                . (int) $row['activity_date'] . ", "
                . (int) $row['reason_id'] . ", "
                . "'" . $db->sql_escape($row['intensity']) . "', "
                . "'" . $db->sql_escape($row['notes']) . "', "
                . (int) $row['created_time']
                . ");\n";
        }
        $db->sql_freeresult($result);

        // ── v3.5.0+ — Tables cages (si elles existent) ──
        $prefix = str_replace('chastity_periods', '', $periods_table);
        $cage_tables_list = [
            'chastity_cage_manufacturers',
            'chastity_cage_catalog',
            'chastity_cage_photos',
            'chastity_cages',
            'chastity_cage_usage',
            'chastity_cage_materials',
            'chastity_cage_ratings',
            'chastity_user_prefs',
            'chastity_keyholders',
        ];
        foreach ($cage_tables_list as $tname)
        {
            $full_name = $prefix . $tname;
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($full_name) . "'");
            $exists = $db->sql_fetchrow($check);
            $db->sql_freeresult($check);
            if (!$exists) { continue; }

            $dump .= "\nTRUNCATE TABLE `" . $full_name . "`; \n";
            $res = $db->sql_query('SELECT * FROM ' . $full_name);
            while ($row = $db->sql_fetchrow($res))
            {
                $values = [];
                foreach ($row as $val)
                {
                    if ($val === null) { $values[] = 'NULL'; }
                    else if (is_numeric($val) && !is_string($val)) { $values[] = $val; }
                    else { $values[] = "'" . $db->sql_escape($val) . "'"; }
                }
                $dump .= "INSERT INTO `" . $full_name . "` VALUES (" . implode(', ', $values) . ");\n";
            }
            $db->sql_freeresult($res);
        }

        // ── Réglages de configuration (table config) ──
        // Tous les réglages de l'extension (chastity_*) sont sauvegardés.
        // Les valeurs sont encodées en base64 : elles peuvent contenir des
        // apostrophes, des sauts de ligne ou des backslashes (ex. messages
        // d'inactivité multi-lignes), ce qui casserait le découpage ligne par
        // ligne de la restauration. base64 garantit une seule ligne sûre.
        // À l'import, FROM_BASE64() restitue la valeur d'origine.
        $dump .= "\n-- Reglages de configuration\n";
        $dump .= "DELETE FROM `" . CONFIG_TABLE . "` WHERE config_name LIKE 'chastity\\_%'; \n";
        $result = $db->sql_query("SELECT config_name, config_value, is_dynamic FROM " . CONFIG_TABLE . "
                                  WHERE config_name LIKE 'chastity\\_%'");
        while ($row = $db->sql_fetchrow($result))
        {
            $name_b64 = base64_encode($row['config_name']);
            $val_b64  = base64_encode($row['config_value']);
            $dump .= "INSERT INTO `" . CONFIG_TABLE . "` (config_name, config_value, is_dynamic) VALUES ("
                . "FROM_BASE64('" . $name_b64 . "'), "
                . "FROM_BASE64('" . $val_b64 . "'), "
                . (int) $row['is_dynamic']
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

        // Purger le cache pour appliquer immédiatement les réglages restaurés
        global $cache;
        if (isset($cache) && is_object($cache) && method_exists($cache, 'purge'))
        {
            $cache->purge();
        }

        trigger_error(sprintf($user->lang['ACP_CHASTITY_BACKUP_RESTORED'], $count) . adm_back_link($this->u_action));
    }

    // ── EXPORT PHOTOS (zip du dossier images/cages/) ──
    if ($request->is_set_post('export_photos'))
    {
        if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

        global $phpbb_root_path;
        $photos_dir = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/';
        if (!is_dir($photos_dir) || !class_exists('ZipArchive'))
        {
            trigger_error('Dossier introuvable ou ZipArchive non disponible.' . adm_back_link($this->u_action));
        }

        $zip_path = $phpbb_root_path . 'store/chastity_photos_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true)
        {
            trigger_error('Impossible de créer le zip.' . adm_back_link($this->u_action));
        }
        $files = glob($photos_dir . '*.{jpg,jpeg,png}', GLOB_BRACE);
        foreach ($files as $f)
        {
            $zip->addFile($f, basename($f));
        }
        $zip->close();

        if (!file_exists($zip_path)) { trigger_error('Zip vide.' . adm_back_link($this->u_action)); }

        $filename = basename($zip_path);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        @unlink($zip_path);
        exit;
    }

    // ── IMPORT PHOTOS (depuis un zip) ──
    if ($request->is_set_post('import_photos'))
    {
        if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
        global $phpbb_root_path;

        $file_data = $request->file('photos_zip');
        if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name']))
        {
            trigger_error('Aucun fichier zip envoyé.' . adm_back_link($this->u_action));
        }
        if (!class_exists('ZipArchive'))
        {
            trigger_error('ZipArchive non disponible.' . adm_back_link($this->u_action));
        }

        $photos_dir = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/';
        if (!is_dir($photos_dir)) { @mkdir($photos_dir, 0777, true); }

        $zip = new \ZipArchive();
        if ($zip->open($file_data['tmp_name']) !== true)
        {
            trigger_error('Zip invalide.' . adm_back_link($this->u_action));
        }
        $extracted = 0;
        for ($i = 0; $i < $zip->numFiles; $i++)
        {
            $name = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) { continue; }
            $safe_name = basename($name);
            if (copy('zip://' . $file_data['tmp_name'] . '#' . $name, $photos_dir . $safe_name))
            {
                $extracted++;
            }
        }
        $zip->close();
        trigger_error('Photos importées : ' . $extracted . adm_back_link($this->u_action));
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

    private function acp_cageexits_mode($template, $request, $db, $reasons_table, $config, $user)
    {


        if ($request->is_set_post('save_threshold'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $t = max(30, min(14400, (int)$request->variable('cageexit_threshold', 480)));
            $config->set('chastity_cageexit_threshold', $t);
            trigger_error($user->lang['ACP_CHASTITY_SAVED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('add_global_reason'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $label = trim($request->variable('new_reason_label', '', true));
            if (mb_strlen($label) >= 2 && mb_strlen($label) <= 100)
            { $db->sql_query("INSERT INTO $reasons_table (label,is_global,user_id,is_approved,created_time) VALUES ('" . $db->sql_escape($label) . "',1,0,1," . time() . ')'); }
            trigger_error($user->lang['ACP_CHASTITY_REASON_ADDED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('approve_reason'))
        {
            $rid = (int)$request->variable('reason_id', 0);
            // Récupérer user_id et label avant d'approuver (pour PM si proposé par un user)
            $res_pm = $db->sql_query("SELECT user_id, label, is_approved FROM $reasons_table WHERE reason_id=$rid");
            $pm_row = $db->sql_fetchrow($res_pm);
            $db->sql_freeresult($res_pm);
            if ($pm_row && (int)$pm_row['is_approved'] === 0)
            {
                $db->sql_query("UPDATE $reasons_table SET is_approved=1 WHERE reason_id=$rid");
                if ((int)$pm_row['user_id'] > 1)
                {
                    $this->send_approval_pm($db, $pm_row['user_id'], $pm_row['label'], 'cageexit', $user);
                }
            }
            trigger_error($user->lang['ACP_CHASTITY_REASON_APPROVED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('delete_reason'))
        {
            $rid = (int)$request->variable('reason_id', 0);
            // Vérifier si ce motif est utilisé dans des entrées existantes
            $ce_table = str_replace('chastity_cageexit_reasons', 'chastity_cageexits', $reasons_table);
            $res_use = $db->sql_query('SELECT COUNT(*) as nb FROM ' . $ce_table . ' WHERE reason_id=' . $rid);
            $row_use = $db->sql_fetchrow($res_use); $db->sql_freeresult($res_use);
            $nb_use  = (int)($row_use['nb'] ?? 0);
            if ($nb_use > 0 && !$request->variable('confirm_delete_reason', 0))
            {
                // Demander confirmation
                $template->assign_vars([
                    'S_CONFIRM_DELETE_REASON' => true,
                    'CONFIRM_DELETE_REASON_ID' => $rid,
                    'CONFIRM_DELETE_NB_USE'    => $nb_use,
                ]);
            }
            else
            {
                $db->sql_query('DELETE FROM ' . $reasons_table . ' WHERE reason_id=' . $rid);
                trigger_error($user->lang['ACP_CHASTITY_REASON_DELETED'] . adm_back_link($this->u_action));
            }
        }
                if ($request->is_set_post('toggle_global'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('reason_id', 0);
            if ($rid > 0)
            {
                $res_cur = $db->sql_query("SELECT is_global FROM $reasons_table WHERE reason_id=$rid");
                $cur = $db->sql_fetchrow($res_cur); $db->sql_freeresult($res_cur);
                $new_global = $cur ? (((int)$cur['is_global'] === 1) ? 0 : 1) : 1;
                $db->sql_query("UPDATE $reasons_table SET is_global=$new_global, is_approved=1 WHERE reason_id=$rid");
            }
            trigger_error($user->lang['ACP_CHASTITY_SAVED'] . adm_back_link($this->u_action));
        }
        $res = $db->sql_query('SELECT r.reason_id,r.label,r.is_global,r.is_approved,r.user_id,u.username FROM ' . $reasons_table . ' r LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id=r.user_id ORDER BY r.is_global DESC,r.is_approved DESC,r.label ASC');
        while ($row = $db->sql_fetchrow($res))
        { $template->assign_block_vars('cageexit_reasons', ['REASON_ID' => $row['reason_id'], 'LABEL' => $row['label'], 'IS_GLOBAL' => (bool)$row['is_global'], 'IS_APPROVED' => (bool)$row['is_approved'], 'USERNAME' => $row['username'] ?? '—']); }
        $db->sql_freeresult($res);
        add_form_key('acp_chastity');
        $template->assign_vars([
            'U_ACTION'               => $this->u_action,
            'CAGEEXIT_THRESHOLD'     => (int)$config['chastity_cageexit_threshold'],
            'CAGEEXIT_THRESHOLD_H'   => round((int)$config['chastity_cageexit_threshold'] / 60, 1),

        ]);
    }

    private function acp_activities_mode($template, $request, $db, $reasons_table, $user)
    {
        if ($request->is_set_post('add_global_activity_reason'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $label = trim($request->variable('new_reason_label', '', true));
            if (mb_strlen($label) >= 2 && mb_strlen($label) <= 100)
            { $db->sql_query("INSERT INTO $reasons_table (label,is_global,user_id,is_approved,created_time) VALUES ('" . $db->sql_escape($label) . "',1,0,1," . time() . ')'); }
            trigger_error($user->lang['ACP_CHASTITY_REASON_ADDED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('approve_activity_reason'))
        {
            $rid = (int)$request->variable('reason_id', 0);
            $res_pm = $db->sql_query("SELECT user_id, label, is_approved FROM $reasons_table WHERE reason_id=$rid");
            $pm_row = $db->sql_fetchrow($res_pm);
            $db->sql_freeresult($res_pm);
            if ($pm_row && (int)$pm_row['is_approved'] === 0)
            {
                $db->sql_query("UPDATE $reasons_table SET is_approved=1 WHERE reason_id=$rid");
                if ((int)$pm_row['user_id'] > 1)
                {
                    $this->send_approval_pm($db, $pm_row['user_id'], $pm_row['label'], 'activity', $user);
                }
            }
            trigger_error($user->lang['ACP_CHASTITY_REASON_APPROVED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('delete_activity_reason'))
        {
            $rid = (int)$request->variable('reason_id', 0);
            $act_table = str_replace('chastity_activity_reasons', 'chastity_activities', $reasons_table);
            $res_use = $db->sql_query('SELECT COUNT(*) as nb FROM ' . $act_table . ' WHERE reason_id=' . $rid);
            $row_use = $db->sql_fetchrow($res_use); $db->sql_freeresult($res_use);
            $nb_use  = (int)($row_use['nb'] ?? 0);
            if ($nb_use > 0 && !$request->variable('confirm_delete_activity_reason', 0))
            {
                $template->assign_vars([
                    'S_CONFIRM_DELETE_ACTIVITY_REASON' => true,
                    'CONFIRM_DELETE_ACTIVITY_REASON_ID' => $rid,
                    'CONFIRM_DELETE_ACTIVITY_NB_USE'    => $nb_use,
                ]);
            }
            else
            {
                $db->sql_query('DELETE FROM ' . $reasons_table . ' WHERE reason_id=' . $rid);
                trigger_error($user->lang['ACP_CHASTITY_REASON_DELETED'] . adm_back_link($this->u_action));
            }
        }
        if ($request->is_set_post('rename_activity_reason'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $new_label = trim($request->variable('new_label', '', true));
            $rid = (int) $request->variable('reason_id', 0);
            if ($rid > 0 && mb_strlen($new_label) >= 2 && mb_strlen($new_label) <= 100)
                $db->sql_query("UPDATE $reasons_table SET label='" . $db->sql_escape($new_label) . "' WHERE reason_id=$rid");
            trigger_error($user->lang['ACP_CHASTITY_REASON_RENAMED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('toggle_activity_global'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('reason_id', 0);
            if ($rid > 0)
            {
                $res_cur = $db->sql_query("SELECT is_global FROM $reasons_table WHERE reason_id=$rid");
                $cur = $db->sql_fetchrow($res_cur); $db->sql_freeresult($res_cur);
                $new_global = $cur ? (((int)$cur['is_global'] === 1) ? 0 : 1) : 1;
                $db->sql_query("UPDATE $reasons_table SET is_global=$new_global, is_approved=1 WHERE reason_id=$rid");
            }
            trigger_error($user->lang['ACP_CHASTITY_SAVED'] . adm_back_link($this->u_action));
        }
        $res = $db->sql_query('SELECT r.reason_id,r.label,r.is_global,r.is_approved,r.user_id,u.username FROM ' . $reasons_table . ' r LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id=r.user_id ORDER BY r.is_global DESC,r.is_approved ASC,r.label ASC');
        while ($row = $db->sql_fetchrow($res))
        { $template->assign_block_vars('activity_reasons', ['REASON_ID' => $row['reason_id'], 'LABEL' => $row['label'], 'IS_GLOBAL' => (bool)$row['is_global'], 'IS_APPROVED' => (bool)$row['is_approved'], 'USERNAME' => $row['username'] ?? '—']); }
        $db->sql_freeresult($res);
        add_form_key('acp_chastity');
        $template->assign_vars(['U_ACTION' => $this->u_action]);
    }

    /**
     * Envoie un MP à un utilisateur pour l'informer de l'approbation de son motif.
     */
    private function send_approval_pm($db, $to_user_id, $label, $type, $user)
    {
        if (empty($to_user_id) || (int)$to_user_id <= 1) { return; }
        $uid      = (int)$to_user_id;
        $admin_id = (int)$user->data['user_id'];
        $now      = time();

        if ($type === 'cageexit') {
            $type_label = $user->lang['ACP_PM_TYPE_CAGEEXIT'];
        } else if ($type === 'activity') {
            $type_label = $user->lang['ACP_PM_TYPE_ACTIVITY'];
        } else if ($type === 'cage') {
            $type_label = isset($user->lang['ACP_PM_TYPE_CAGE']) ? $user->lang['ACP_PM_TYPE_CAGE'] : 'cage';
        } else if ($type === 'comment') {
            $type_label = isset($user->lang['ACP_PM_TYPE_COMMENT']) ? $user->lang['ACP_PM_TYPE_COMMENT'] : 'commentaire';
        } else {
            $type_label = $type;
        }

        $subject = sprintf($user->lang['ACP_PM_SUBJECT_APPROVED'], $type_label);
        $body    = sprintf($user->lang['ACP_PM_BODY_APPROVED'], $label, $type_label);

        // Générer un bbcode_uid pour que [b]...[/b] soit rendu par phpBB
        $bbcode_uid = substr(md5(uniqid(rand(), true)), 0, 8);
        $body_bb    = str_replace(['[b]', '[/b]'],
                                   ['[b:' . $bbcode_uid . ']', '[/b:' . $bbcode_uid . ']'],
                                   $body);

        $sub_esc  = $db->sql_escape($subject);
        $body_esc = $db->sql_escape($body_bb);
        $uid_addr = 'u_' . $uid;

        // Construction du SQL sans interpolation ambiguë
        $q = "'" ;
        $sql = 'INSERT INTO ' . PRIVMSGS_TABLE
             . ' (root_level,author_id,icon_id,author_ip,message_time,'
             . 'enable_bbcode,enable_smilies,enable_magic_url,enable_sig,'
             . 'message_subject,message_text,message_edit_reason,message_edit_user,'
             . 'message_attachment,bbcode_bitfield,bbcode_uid,to_address,bcc_address)'
             . ' VALUES'
             . ' (0,' . $admin_id . ',0,' . $q . '127.0.0.1' . $q . ',' . $now . ','
             . '1,0,1,0,'
             . $q . $sub_esc . $q . ',' . $q . $body_esc . $q . ',' . $q . $q . ',0,'
             . '0,' . $q . 'AQ==' . $q . ',' . $q . $bbcode_uid . $q . ','
             . $q . $uid_addr . $q . ',' . $q . $q . ')';
        $db->sql_query($sql);

        $msg_id = (int) $db->sql_nextid();
        if (!$msg_id) { return; }

        $db->sql_query('INSERT INTO ' . PRIVMSGS_TO_TABLE
            . ' (msg_id,user_id,author_id,pm_deleted,pm_new,pm_unread,pm_replied,pm_marked,pm_forwarded,folder_id)'
            . ' VALUES (' . $msg_id . ',' . $uid . ',' . $admin_id . ',0,1,1,0,0,0,0)');

        $db->sql_query('UPDATE ' . USERS_TABLE
            . ' SET user_unread_privmsg=user_unread_privmsg+1,user_new_privmsg=user_new_privmsg+1'
            . ' WHERE user_id=' . $uid);
    }

    // ════════════════════════════════════════════════════════════
    // ACP — Fabricants de cages
    // ════════════════════════════════════════════════════════════
    private function acp_cage_manufacturers_mode($template, $request, $db, $tables, $user)
    {
        $mfr_table     = $tables['manufacturers'];
        $catalog_table = $tables['catalog'];

        // Ajout / modification
        if ($request->is_set_post('save_manufacturer'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

            $mfr_id  = (int) $request->variable('manufacturer_id', 0);
            $sql_ary = [
                'name'          => $request->variable('mfr_name', '', true),
                'address'       => $request->variable('mfr_address', '', true),
                'phone'         => $request->variable('mfr_phone', '', true),
                'email'         => $request->variable('mfr_email', '', true),
                'website'       => $request->variable('mfr_website', '', true),
                'is_partner'    => (int) $request->variable('mfr_is_partner', 0),
                'partner_notes' => $request->variable('mfr_partner_notes', '', true),
                'updated_at'    => time(),
            ];
            if (empty($sql_ary['name']))
            {
                trigger_error($user->lang['CHASTITY_MANUFACTURER_NAME_REQUIRED'] . adm_back_link($this->u_action));
            }
            if ($mfr_id > 0)
            {
                $db->sql_query('UPDATE ' . $mfr_table . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . ' WHERE manufacturer_id = ' . $mfr_id);
            }
            else
            {
                $sql_ary['created_at'] = time();
                $db->sql_query('INSERT INTO ' . $mfr_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
            }
            trigger_error($user->lang['CHASTITY_MANUFACTURER_SAVED'] . adm_back_link($this->u_action));
        }

        // Suppression
        if ($request->is_set_post('delete_manufacturer'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $mfr_id = (int) $request->variable('manufacturer_id', 0);

            $sql = 'SELECT COUNT(*) AS nb FROM ' . $catalog_table . ' WHERE manufacturer_id = ' . $mfr_id;
            $res = $db->sql_query($sql);
            $nb  = (int) $db->sql_fetchfield('nb');
            $db->sql_freeresult($res);

            // Dissocier puis supprimer
            if ($nb > 0)
            {
                $db->sql_query('UPDATE ' . $catalog_table . ' SET manufacturer_id = 0 WHERE manufacturer_id = ' . $mfr_id);
            }
            $db->sql_query('DELETE FROM ' . $mfr_table . ' WHERE manufacturer_id = ' . $mfr_id);
            trigger_error($user->lang['CHASTITY_MANUFACTURER_DELETED'] . adm_back_link($this->u_action));
        }

        // Édition (pré-remplir)
        $edit_data = [];
        $edit_id   = (int) $request->variable('edit_mfr', 0);
        if (!$edit_id) {
            $edit_id = (int) $request->variable('edit_mfr_btn', 0);
        }
        if ($edit_id > 0)
        {
            $res = $db->sql_query('SELECT * FROM ' . $mfr_table . ' WHERE manufacturer_id = ' . $edit_id);
            $edit_data = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if (!$edit_data) { $edit_data = []; }
        }

        // Liste
        $sql = 'SELECT m.*, (SELECT COUNT(*) FROM ' . $catalog_table . ' c WHERE c.manufacturer_id = m.manufacturer_id) AS cage_count
                FROM ' . $mfr_table . ' m ORDER BY m.name ASC';
        $res = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('manufacturers', [
                'ID'         => (int) $row['manufacturer_id'],
                'NAME'       => $row['name'],
                'ADDRESS'    => $row['address'],
                'PHONE'      => $row['phone'],
                'EMAIL'      => $row['email'],
                'WEBSITE'    => $row['website'],
                'IS_PARTNER' => (int) $row['is_partner'],
                'NOTES'      => $row['partner_notes'],
                'CAGE_COUNT' => (int) $row['cage_count'],
            ]);
        }
        $db->sql_freeresult($res);

        $template->assign_vars([
            'U_ACTION'         => $this->u_action,
            'EDIT_MFR_ID'      => isset($edit_data['manufacturer_id']) ? (int) $edit_data['manufacturer_id'] : 0,
            'EDIT_MFR_NAME'    => isset($edit_data['name'])          ? $edit_data['name']          : '',
            'EDIT_MFR_ADDRESS' => isset($edit_data['address'])       ? $edit_data['address']       : '',
            'EDIT_MFR_PHONE'   => isset($edit_data['phone'])         ? $edit_data['phone']         : '',
            'EDIT_MFR_EMAIL'   => isset($edit_data['email'])         ? $edit_data['email']         : '',
            'EDIT_MFR_WEBSITE' => isset($edit_data['website'])       ? $edit_data['website']       : '',
            'EDIT_MFR_PARTNER' => isset($edit_data['is_partner'])    ? (int) $edit_data['is_partner'] : 0,
            'EDIT_MFR_NOTES'   => isset($edit_data['partner_notes']) ? $edit_data['partner_notes'] : '',
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // ACP — Catalogue de cages
    // ════════════════════════════════════════════════════════════
    private function acp_cage_catalog_mode($template, $request, $db, $tables, $user, $config)
    {
        global $phpbb_root_path;

        $catalog_table = $tables['catalog'];
        $photos_table  = $tables['photos'];
        $mfr_table     = $tables['manufacturers'];
        $cages_table   = $tables['cages'];

        // Valider une cage proposée
        if ($request->is_set_post('validate_cage'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('catalog_id', 0);
            // Récupérer le proposeur pour MP
            $res = $db->sql_query('SELECT cage_name, added_by_user_id FROM ' . $catalog_table . ' WHERE catalog_id = ' . $cid);
            $cage_row = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            $db->sql_query('UPDATE ' . $catalog_table . ' SET is_validated = 1, updated_at = ' . time() . ' WHERE catalog_id = ' . $cid);
            // Aussi valider les photos liées
            $db->sql_query('UPDATE ' . $photos_table . ' SET is_validated = 1 WHERE catalog_id = ' . $cid);
            if ($cage_row && (int) $cage_row['added_by_user_id'] > 1)
            {
                $this->send_approval_pm($db, $cage_row['added_by_user_id'], $cage_row['cage_name'], 'cage', $user);
            }
            trigger_error($user->lang['CHASTITY_CAGE_VALIDATED_MSG'] . adm_back_link($this->u_action));
        }

        // Rejeter (supprimer) une cage proposée
        if ($request->is_set_post('reject_cage'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('catalog_id', 0);
            // Supprimer aussi les photos sur le disque
            global $phpbb_root_path;
            $res = $db->sql_query('SELECT filename FROM ' . $photos_table . ' WHERE catalog_id = ' . $cid);
            while ($p = $db->sql_fetchrow($res))
            {
                $path = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/' . $p['filename'];
                if (file_exists($path)) { @unlink($path); }
            }
            $db->sql_freeresult($res);
            $db->sql_query('DELETE FROM ' . $photos_table . ' WHERE catalog_id = ' . $cid);
            $db->sql_query('DELETE FROM ' . $catalog_table . ' WHERE catalog_id = ' . $cid . ' AND is_validated = 0');
            trigger_error($user->lang['CHASTITY_CAGE_REJECTED'] . adm_back_link($this->u_action));
        }

        // Valider un commentaire (rating avec comment, is_validated = 0)
        if ($request->is_set_post('validate_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';
            if ($ratings_table && $rid > 0)
            {
                $res = $db->sql_query('SELECT r.user_id, r.comment, c.cage_name FROM ' . $ratings_table . ' r
                                       JOIN ' . $catalog_table . ' c ON c.catalog_id = r.catalog_id
                                       WHERE r.rating_id = ' . $rid);
                $row = $db->sql_fetchrow($res);
                $db->sql_freeresult($res);
                $db->sql_query('UPDATE ' . $ratings_table . ' SET is_validated = 1 WHERE rating_id = ' . $rid);
                if ($row && (int) $row['user_id'] > 1)
                {
                    $this->send_approval_pm($db, $row['user_id'], $row['cage_name'], 'comment', $user);
                }
            }
            trigger_error($user->lang['CHASTITY_COMMENT_VALIDATED'] . adm_back_link($this->u_action));
        }

        // Rejeter un commentaire (le supprime sans toucher à la note)
        if ($request->is_set_post('reject_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';
            if ($ratings_table && $rid > 0)
            {
                // Vider le commentaire mais garder la note
                $db->sql_query('UPDATE ' . $ratings_table . " SET comment = '', is_validated = 1 WHERE rating_id = " . $rid);
            }
            trigger_error($user->lang['CHASTITY_COMMENT_REJECTED'] . adm_back_link($this->u_action));
        }

        // Modifier un commentaire (admin réécrit le texte, valide automatiquement)
        if ($request->is_set_post('edit_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            $new_text = trim($request->variable('comment_text', '', true));
            if (mb_strlen($new_text) > 500) { $new_text = mb_substr($new_text, 0, 500); }
            $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';
            if ($ratings_table && $rid > 0)
            {
                $db->sql_query('UPDATE ' . $ratings_table . " SET comment = '" . $db->sql_escape($new_text) . "', is_validated = 1 WHERE rating_id = " . $rid);
            }
            trigger_error($user->lang['CHASTITY_COMMENT_VALIDATED'] . adm_back_link($this->u_action));
        }

        // Enregistrer une cage du catalogue (ajout / édition)
        if ($request->is_set_post('save_catalog_cage'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('catalog_id', 0);
            $sql_ary = [
                'cage_name'        => $request->variable('cage_name', '', true),
                'cage_brand'       => $request->variable('cage_brand', '', true),
                'cage_material'    => $request->variable('cage_material', '', true),
                'cage_type'        => $request->variable('cage_type', '', true),
                'cage_description' => $request->variable('cage_description', '', true),
                'manufacturer_id'  => (int) $request->variable('manufacturer_id_select', 0),
                'updated_at'       => time(),
            ];
            if (empty($sql_ary['cage_name']))
            {
                trigger_error($user->lang['CHASTITY_CAGE_NAME_REQUIRED'] . adm_back_link($this->u_action));
            }
            if ($cid > 0)
            {
                $db->sql_query('UPDATE ' . $catalog_table . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . ' WHERE catalog_id = ' . $cid);
            }
            else
            {
                $sql_ary['is_validated']     = 1;
                $sql_ary['added_by_user_id'] = 0;
                $sql_ary['usage_count']      = 0;
                $sql_ary['created_at']       = time();
                $db->sql_query('INSERT INTO ' . $catalog_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
            }
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Supprimer une cage
        if ($request->is_set_post('delete_catalog_cage'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('catalog_id', 0);
            // Supprimer les photos sur le disque
            $res = $db->sql_query('SELECT filename FROM ' . $photos_table . ' WHERE catalog_id = ' . $cid);
            while ($p = $db->sql_fetchrow($res))
            {
                $path = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/' . $p['filename'];
                if (file_exists($path)) { @unlink($path); }
            }
            $db->sql_freeresult($res);
            $db->sql_query('DELETE FROM ' . $photos_table . ' WHERE catalog_id = ' . $cid);
            $db->sql_query('DELETE FROM ' . $cages_table . ' WHERE catalog_id = ' . $cid);
            $db->sql_query('DELETE FROM ' . $catalog_table . ' WHERE catalog_id = ' . $cid);
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Upload de photo
        if ($request->is_set_post('upload_cage_photo'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid       = (int) $request->variable('photo_catalog_id', 0);
            $file_data = $request->file('cage_photo');

            if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name']))
            {
                trigger_error($user->lang['CHASTITY_CAGE_NO_PHOTO'] . adm_back_link($this->u_action));
            }
            $ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png']))
            {
                trigger_error($user->lang['CHASTITY_CAGE_PHOTO_INVALID'] . adm_back_link($this->u_action));
            }
            if ($file_data['size'] > 512 * 1024)
            {
                trigger_error($user->lang['CHASTITY_CAGE_PHOTO_TOO_LARGE'] . adm_back_link($this->u_action));
            }

            $filename   = 'cage_' . $cid . '_' . time() . '.' . $ext;
            $upload_dir = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/';
            if (!is_dir($upload_dir))
            {
                @mkdir($upload_dir, 0777, true);
                @chmod($upload_dir, 0777);
            }
            if (!move_uploaded_file($file_data['tmp_name'], $upload_dir . $filename))
            {
                trigger_error('Erreur d\'écriture dans ' . $upload_dir . '. Vérifiez les permissions (chmod 777).' . adm_back_link($this->u_action));
            }

            // Redimensionnement automatique pour cartes uniformes (max 800x600)
            $this->resize_cage_image($upload_dir . $filename, $ext, 800, 600);

            // Première photo = principale
            $res = $db->sql_query('SELECT COUNT(*) AS nb FROM ' . $photos_table . ' WHERE catalog_id = ' . $cid);
            $is_main = ((int) $db->sql_fetchfield('nb') === 0) ? 1 : 0;
            $db->sql_freeresult($res);
            $db->sql_query('INSERT INTO ' . $photos_table . ' ' . $db->sql_build_array('INSERT', [
                'catalog_id'   => $cid,
                'user_id'      => (int) $user->data['user_id'],
                'filename'     => $filename,
                'is_main'      => $is_main,
                'is_validated' => 1,
                'uploaded_at'  => time(),
            ]));
            trigger_error($user->lang['CHASTITY_CAGE_PHOTO_UPLOADED'] . adm_back_link($this->u_action));
        }

        // Supprimer une photo
        if ($request->is_set_post('delete_cage_photo'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $pid = (int) $request->variable('photo_id', 0);
            $res = $db->sql_query('SELECT filename FROM ' . $photos_table . ' WHERE photo_id = ' . $pid);
            $p = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if ($p)
            {
                $path = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/' . $p['filename'];
                if (file_exists($path)) { @unlink($path); }
                $db->sql_query('DELETE FROM ' . $photos_table . ' WHERE photo_id = ' . $pid);
            }
            trigger_error($user->lang['CHASTITY_CAGE_PHOTO_DELETED'] . adm_back_link($this->u_action));
        }

        // Définir photo principale
        if ($request->is_set_post('set_main_photo'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $pid = (int) $request->variable('photo_id', 0);
            $res = $db->sql_query('SELECT catalog_id FROM ' . $photos_table . ' WHERE photo_id = ' . $pid);
            $p = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if ($p)
            {
                $db->sql_query('UPDATE ' . $photos_table . ' SET is_main = 0 WHERE catalog_id = ' . (int) $p['catalog_id']);
                $db->sql_query('UPDATE ' . $photos_table . ' SET is_main = 1 WHERE photo_id = ' . $pid);
            }
            trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
        }

        // Édition (pré-remplir) — accept both GET ?edit_cage=N and POST edit_cage_btn submission
        $edit_data = [];
        $edit_id   = (int) $request->variable('edit_cage', 0);
        if (!$edit_id) {
            $edit_id = (int) $request->variable('edit_cage_btn', 0);
        }
        if ($edit_id > 0)
        {
            $res = $db->sql_query('SELECT * FROM ' . $catalog_table . ' WHERE catalog_id = ' . $edit_id);
            $edit_data = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if (!$edit_data) { $edit_data = []; }

            // Photos pour cette cage
            $res = $db->sql_query('SELECT * FROM ' . $photos_table . ' WHERE catalog_id = ' . $edit_id . ' ORDER BY is_main DESC, uploaded_at ASC');
            while ($p = $db->sql_fetchrow($res))
            {
                $template->assign_block_vars('edit_photos', [
                    'PHOTO_ID' => (int) $p['photo_id'],
                    'FILENAME' => $p['filename'],
                    'IS_MAIN'  => (int) $p['is_main'],
                ]);
            }
            $db->sql_freeresult($res);
        }

        // Liste des fabricants pour le sélecteur
        $res = $db->sql_query('SELECT manufacturer_id, name FROM ' . $mfr_table . ' ORDER BY name ASC');
        while ($m = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('mfr_select', [
                'ID'   => (int) $m['manufacturer_id'],
                'NAME' => $m['name'],
            ]);
        }
        $db->sql_freeresult($res);

        // Liste des matériaux depuis BDD
        $materials_table = isset($tables['materials']) ? $tables['materials'] : '';
        if ($materials_table) {
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($materials_table) . "'");
            if ($db->sql_fetchrow($check)) {
                $db->sql_freeresult($check);
                $res = $db->sql_query('SELECT material_key, material_name FROM ' . $materials_table . ' WHERE is_validated = 1 ORDER BY material_name ASC');
                while ($m = $db->sql_fetchrow($res)) {
                    $template->assign_block_vars('material_select', [
                        'KEY'  => $m['material_key'],
                        'NAME' => $m['material_name'],
                    ]);
                }
                $db->sql_freeresult($res);
            } else { $db->sql_freeresult($check); }
        }

        // Filtrage
        $filter = $request->variable('filter_status', 'all');
        $where  = '';
        if ($filter === 'pending')   { $where = ' WHERE c.is_validated = 0'; }
        if ($filter === 'validated') { $where = ' WHERE c.is_validated = 1'; }

        // Liste du catalogue (sans LIMIT en sous-requête)
        $sql = 'SELECT c.catalog_id, c.cage_name, c.cage_brand, c.cage_material, c.cage_type,
                       c.cage_description, c.is_validated, c.added_by_user_id, m.name AS manufacturer_name,
                       (SELECT COUNT(*) FROM ' . $cages_table . ' uc WHERE uc.catalog_id = c.catalog_id) AS user_count
                FROM ' . $catalog_table . ' c
                LEFT JOIN ' . $mfr_table . ' m ON m.manufacturer_id = c.manufacturer_id'
                . $where . ' ORDER BY c.is_validated ASC, c.cage_name ASC';
        $res = $db->sql_query($sql);
        $rows = [];
        while ($r = $db->sql_fetchrow($res)) { $rows[] = $r; }
        $db->sql_freeresult($res);

        // Récupérer les photos principales en lot
        $main_photos = [];
        if (!empty($rows))
        {
            $ids = array_map(function($r) { return (int) $r['catalog_id']; }, $rows);
            $sql = 'SELECT catalog_id, filename FROM ' . $photos_table . ' WHERE is_main = 1 AND ' . $db->sql_in_set('catalog_id', $ids);
            $res = $db->sql_query($sql);
            while ($p = $db->sql_fetchrow($res))
            {
                $main_photos[(int) $p['catalog_id']] = $p['filename'];
            }
            $db->sql_freeresult($res);
        }

        // Map matériau pour affichage du nom
        $materials_map_acp = [];
        if (!empty($materials_table)) {
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($materials_table) . "'");
            if ($db->sql_fetchrow($check)) {
                $db->sql_freeresult($check);
                $res = $db->sql_query('SELECT material_key, material_name FROM ' . $materials_table);
                while ($r = $db->sql_fetchrow($res)) { $materials_map_acp[$r['material_key']] = $r['material_name']; }
                $db->sql_freeresult($res);
            } else { $db->sql_freeresult($check); }
        }

        $total = count($rows);
        $pending = 0;
        foreach ($rows as $row)
        {
            if (!(int) $row['is_validated']) { $pending++; }
            $mkey = $row['cage_material'];
            $template->assign_block_vars('catalog', [
                'ID'           => (int) $row['catalog_id'],
                'NAME'         => $row['cage_name'],
                'BRAND'        => $row['cage_brand'],
                'MATERIAL'     => isset($materials_map_acp[$mkey]) ? $materials_map_acp[$mkey] : $mkey,
                'TYPE'         => $row['cage_type'],
                'DESCRIPTION'  => $row['cage_description'],
                'MANUFACTURER' => $row['manufacturer_name'] ?: '-',
                'IS_VALIDATED' => (int) $row['is_validated'],
                'USER_COUNT'   => (int) $row['user_count'],
                'MAIN_PHOTO'   => isset($main_photos[(int) $row['catalog_id']]) ? $main_photos[(int) $row['catalog_id']] : '',
            ]);
        }

        $template->assign_vars([
            'U_ACTION'           => $this->u_action,
            'U_ACTION_EDIT'      => $this->u_action . '&amp;edit_cage=' . (isset($edit_data['catalog_id']) ? (int) $edit_data['catalog_id'] : 0),
            'BOARD_URL'          => generate_board_url() . '/',
            'ADM_I'              => '-verturin-chastitytracker-acp-main_module',
            'TOTAL_CATALOG'      => $total,
            'PENDING_CATALOG'    => $pending,
            'FILTER_STATUS'      => $filter,
            'EDIT_CAGE_ID'       => isset($edit_data['catalog_id']) ? (int) $edit_data['catalog_id'] : 0,
            'EDIT_CAGE_NAME'     => isset($edit_data['cage_name']) ? $edit_data['cage_name'] : '',
            'EDIT_CAGE_BRAND'    => isset($edit_data['cage_brand']) ? $edit_data['cage_brand'] : '',
            'EDIT_CAGE_MATERIAL' => isset($edit_data['cage_material']) ? $edit_data['cage_material'] : '',
            'EDIT_CAGE_TYPE'     => isset($edit_data['cage_type']) ? $edit_data['cage_type'] : '',
            'EDIT_CAGE_DESC'     => isset($edit_data['cage_description']) ? $edit_data['cage_description'] : '',
            'EDIT_CAGE_MFR'      => isset($edit_data['manufacturer_id']) ? (int) $edit_data['manufacturer_id'] : 0,
        ]);
    }

    /**
     * ACP — Gestion des matériaux
     */
    private function acp_cage_materials_mode($template, $request, $db, $tables, $user)
    {
        $materials_table = $tables['materials'];

        // Ajout / modification
        if ($request->is_set_post('save_material'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $mid  = (int) $request->variable('material_id', 0);
            $name = $request->variable('material_name', '', true);
            $key  = strtolower(preg_replace('/[^a-z0-9_]/i', '', $request->variable('material_key', '', true)));
            if (empty($name)) {
                trigger_error('Le nom est obligatoire.' . adm_back_link($this->u_action));
            }
            if (empty($key)) {
                // Auto-générer une clé à partir du nom
                $key = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                if (empty($key)) { $key = 'mat_' . time(); }
            }
            if ($mid > 0) {
                $db->sql_query('UPDATE ' . $materials_table . ' SET ' . $db->sql_build_array('UPDATE', [
                    'material_key'  => $key,
                    'material_name' => $name,
                    'is_validated'  => 1,
                ]) . ' WHERE material_id = ' . $mid);
            } else {
                $db->sql_query('INSERT INTO ' . $materials_table . ' ' . $db->sql_build_array('INSERT', [
                    'material_key'  => $key,
                    'material_name' => $name,
                    'is_validated'  => 1,
                    'created_at'    => time(),
                ]));
            }
            trigger_error('Matériau enregistré.' . adm_back_link($this->u_action));
        }

        // Validation d'un matériau proposé
        if ($request->is_set_post('validate_material'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $mid = (int) $request->variable('material_id', 0);
            $db->sql_query('UPDATE ' . $materials_table . ' SET is_validated = 1 WHERE material_id = ' . $mid);
            trigger_error('Matériau validé.' . adm_back_link($this->u_action));
        }

        // Suppression
        if ($request->is_set_post('delete_material'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $mid = (int) $request->variable('material_id', 0);
            $db->sql_query('DELETE FROM ' . $materials_table . ' WHERE material_id = ' . $mid);
            trigger_error('Matériau supprimé.' . adm_back_link($this->u_action));
        }

        // Édition (pré-remplir)
        $edit_data = [];
        $edit_id = (int) $request->variable('edit_material_btn', 0);
        if ($edit_id > 0) {
            $res = $db->sql_query('SELECT * FROM ' . $materials_table . ' WHERE material_id = ' . $edit_id);
            $edit_data = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if (!$edit_data) { $edit_data = []; }
        }

        // Liste
        $res = $db->sql_query('SELECT * FROM ' . $materials_table . ' ORDER BY is_validated ASC, material_name ASC');
        while ($row = $db->sql_fetchrow($res)) {
            $template->assign_block_vars('materials', [
                'ID'           => (int) $row['material_id'],
                'KEY'          => $row['material_key'],
                'NAME'         => $row['material_name'],
                'IS_VALIDATED' => (int) $row['is_validated'],
            ]);
        }
        $db->sql_freeresult($res);

        $template->assign_vars([
            'U_ACTION'         => $this->u_action,
            'EDIT_MATERIAL_ID'   => isset($edit_data['material_id']) ? (int) $edit_data['material_id'] : 0,
            'EDIT_MATERIAL_KEY'  => isset($edit_data['material_key']) ? $edit_data['material_key'] : '',
            'EDIT_MATERIAL_NAME' => isset($edit_data['material_name']) ? $edit_data['material_name'] : '',
        ]);
    }

    /**
     * Redimensionne une image en conservant le ratio, max width × max height.
     */
    private function resize_cage_image($filepath, $ext, $max_w, $max_h)
    {
        if (!function_exists('imagecreatefromjpeg')) { return; }
        if (!file_exists($filepath)) { return; }

        $info = @getimagesize($filepath);
        if (!$info) { return; }
        list($w, $h) = $info;

        if ($w <= $max_w && $h <= $max_h) { return; }

        $ratio = min($max_w / $w, $max_h / $h);
        $new_w = (int) round($w * $ratio);
        $new_h = (int) round($h * $ratio);

        $ext = strtolower($ext);
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $src = @imagecreatefromjpeg($filepath);
        } else if ($ext === 'png') {
            $src = @imagecreatefrompng($filepath);
        } else {
            return;
        }
        if (!$src) { return; }

        $dst = imagecreatetruecolor($new_w, $new_h);
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $w, $h);

        if ($ext === 'jpg' || $ext === 'jpeg') {
            imagejpeg($dst, $filepath, 85);
        } else {
            imagepng($dst, $filepath, 6);
        }
        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Mode ACP : gestion dédiée des commentaires (validation, modification, rejet)
     */
    private function acp_cage_comments_mode($template, $request, $db, $tables, $user)
    {
        $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';
        $catalog_table = isset($tables['catalog']) ? $tables['catalog'] : '';
        if (!$ratings_table || !$catalog_table) {
            trigger_error('Tables manquantes' . adm_back_link($this->u_action));
        }

        // Actions
        if ($request->is_set_post('validate_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            if ($rid > 0)
            {
                $res = $db->sql_query('SELECT r.user_id, r.comment, c.cage_name FROM ' . $ratings_table . ' r
                                       JOIN ' . $catalog_table . ' c ON c.catalog_id = r.catalog_id
                                       WHERE r.rating_id = ' . $rid);
                $row = $db->sql_fetchrow($res);
                $db->sql_freeresult($res);
                $db->sql_query('UPDATE ' . $ratings_table . ' SET is_validated = 1 WHERE rating_id = ' . $rid);
                if ($row && (int) $row['user_id'] > 1)
                {
                    $this->send_approval_pm($db, $row['user_id'], $row['cage_name'], 'comment', $user);
                }
            }
            trigger_error($user->lang['CHASTITY_COMMENT_VALIDATED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('reject_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            if ($rid > 0)
            {
                $db->sql_query('UPDATE ' . $ratings_table . " SET comment = '', is_validated = 1 WHERE rating_id = " . $rid);
            }
            trigger_error($user->lang['CHASTITY_COMMENT_REJECTED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('delete_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            if ($rid > 0)
            {
                // Suppression complète de la ligne (commentaire + note)
                $db->sql_query('DELETE FROM ' . $ratings_table . ' WHERE rating_id = ' . $rid);
            }
            trigger_error($user->lang['CHASTITY_COMMENT_DELETED'] . adm_back_link($this->u_action));
        }
        if ($request->is_set_post('edit_comment'))
        {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $rid = (int) $request->variable('rating_id', 0);
            $new_text = trim($request->variable('comment_text', '', true));
            if (mb_strlen($new_text) > 500) { $new_text = mb_substr($new_text, 0, 500); }
            if ($rid > 0)
            {
                $db->sql_query('UPDATE ' . $ratings_table . " SET comment = '" . $db->sql_escape($new_text) . "', is_validated = 1 WHERE rating_id = " . $rid);
            }
            trigger_error($user->lang['CHASTITY_COMMENT_VALIDATED'] . adm_back_link($this->u_action));
        }

        // Filtres
        $filter_text = trim($request->variable('search_text', '', true));
        $filter_user = trim($request->variable('search_user', '', true));
        $filter_date_from = trim($request->variable('date_from', ''));
        $filter_date_to = trim($request->variable('date_to', ''));

        // On exclut les commentaires vides OU whitespace-only (ils n'ont rien à modérer)
        $where_common = " TRIM(r.comment) <> '' ";
        if ($filter_text !== '') {
            $where_common .= " AND r.comment LIKE '%" . $db->sql_escape($filter_text) . "%'";
        }
        if ($filter_user !== '') {
            $where_common .= " AND u.username_clean LIKE '%" . $db->sql_escape(utf8_clean_string($filter_user)) . "%'";
        }
        if ($filter_date_from !== '') {
            $ts = strtotime($filter_date_from);
            if ($ts) { $where_common .= ' AND r.created_at >= ' . (int) $ts; }
        }
        if ($filter_date_to !== '') {
            $ts = strtotime($filter_date_to . ' 23:59:59');
            if ($ts) { $where_common .= ' AND r.created_at <= ' . (int) $ts; }
        }

        // Charger les listes
        $pending_count = 0;
        $validated_count = 0;
        try {
            $sql = 'SELECT r.rating_id, r.rating, r.comment, r.created_at, r.user_id, r.catalog_id,
                           u.username, u.user_colour, c.cage_name
                    FROM ' . $ratings_table . ' r
                    LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = r.user_id
                    LEFT JOIN " . $catalog_table . " c ON c.catalog_id = r.catalog_id
                    WHERE r.is_validated = 0 AND " . $where_common . "
                    ORDER BY r.created_at DESC";
            $res = $db->sql_query($sql);
            while ($row = $db->sql_fetchrow($res)) {
                $pending_count++;

                $cn = trim((string) $row['cage_name']);
                $cm = trim((string) $row['comment']);
                $ca = (int) $row['created_at'];
                $cid = (int) $row['catalog_id'];

                $cage_disp = $cn !== '' ? $cn : ('Cage #' . $cid);
                $comm_disp = $cm !== '' ? $cm : '—';
                $date_disp = $ca > 0 ? date('d/m/Y H:i', $ca) : '—';

                $template->assign_block_vars('ctpendingrow', [
                    'RATING_ID'     => (int) $row['rating_id'],
                    'CATALOG_ID'    => $cid,
                    'RATING'        => (int) $row['rating'],
                    'COMMENT'       => ($row['comment'] !== null && $row['comment'] !== '') ? $row['comment'] : '—',
                    'AUTHOR'        => $row['username'] ?: '?',
                    'AUTHOR_COLOUR' => $row['user_colour'] ?: '',
                    'CAGE_NAME'     => ($row['cage_name'] !== null && $row['cage_name'] !== '') ? $row['cage_name'] : ('Cage #' . $cid),
                    'DATE'          => ((int) $row['created_at'] > 0) ? date('d/m/Y H:i', (int) $row['created_at']) : '—',
                ]);
            }
            $db->sql_freeresult($res);

            $sql = 'SELECT r.rating_id, r.rating, r.comment, r.created_at, r.user_id, r.catalog_id,
                           u.username, u.user_colour, c.cage_name
                    FROM ' . $ratings_table . ' r
                    LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = r.user_id
                    LEFT JOIN " . $catalog_table . " c ON c.catalog_id = r.catalog_id
                    WHERE r.is_validated = 1 AND " . $where_common . "
                    ORDER BY r.created_at DESC";
            $res = $db->sql_query($sql);
            while ($row = $db->sql_fetchrow($res)) {
                $validated_count++;
                $cage_name_disp = trim((string) $row['cage_name']) !== '' ? $row['cage_name'] : ('Cage #' . (int) $row['catalog_id']);
                $comment_disp = trim((string) $row['comment']) !== '' ? $row['comment'] : '(commentaire vide)';
                $date_disp = ((int) $row['created_at'] > 0) ? date('d/m/Y H:i', (int) $row['created_at']) : '—';
                $template->assign_block_vars('ctvalidrow', [
                    'RATING_ID'     => (int) $row['rating_id'],
                    'CATALOG_ID'    => (int) $row['catalog_id'],
                    'RATING'        => (int) $row['rating'],
                    'COMMENT'       => $comment_disp,
                    'AUTHOR'        => $row['username'] ?: '?',
                    'AUTHOR_COLOUR' => $row['user_colour'] ?: '',
                    'CAGE_NAME'     => $cage_name_disp,
                    'DATE'          => $date_disp,
                ]);
            }
            $db->sql_freeresult($res);
        } catch (\Exception $e) {}

        $template->assign_vars([
            'U_ACTION'           => $this->u_action,
            'PENDING_COMMENTS'   => $pending_count,
            'VALIDATED_COMMENTS' => $validated_count,
            'FILTER_TEXT'        => $filter_text,
            'FILTER_USER'        => $filter_user,
            'FILTER_DATE_FROM'   => $filter_date_from,
            'FILTER_DATE_TO'     => $filter_date_to,
            'HAS_FILTERS'        => ($filter_text !== '' || $filter_user !== '' || $filter_date_from !== '' || $filter_date_to !== ''),
        ]);
    }

    /**
     * ACP — Gestion des duos Keyholder ↔ Sub
     */
    private function acp_keyholders_mode($template, $request, $db, $user, $kh_table, $cu_table)
    {
        // ── Action : forcer la rupture d'un duo ──
        if ($request->is_set_post('force_end')) {
            if (!check_link_hash($request->variable('hash', ''), 'force_end_kh')) {
                trigger_error('Form invalid' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $db->sql_query('UPDATE ' . $kh_table . " SET status = 'ended', ended_at = " . time() . ", ended_by = 0, end_reason = 'Admin' WHERE kh_id = $kh_id AND status IN ('pending', 'active')");
                trigger_error('Duo rompu.' . adm_back_link($this->u_action));
            }
        }

        // Filtre par statut
        $filter_status = $request->variable('status', 'all');
        if ($request->is_set_post('clear_filter')) {
            $filter_status = 'all';
        }
        $allowed = ['all', 'pending', 'active', 'ended', 'refused'];
        if (!in_array($filter_status, $allowed, true)) { $filter_status = 'all'; }

        $where = ($filter_status === 'all') ? '1=1' : "k.status = '" . $db->sql_escape($filter_status) . "'";

        $sql = 'SELECT k.*,
                       us.username AS sub_username, us.user_colour AS sub_colour,
                       uk.username AS kh_username, uk.user_colour AS kh_colour,
                       cus.chastity_status AS sub_chastity_status
                FROM ' . $kh_table . ' k
                LEFT JOIN ' . USERS_TABLE . ' us ON us.user_id = k.sub_user_id
                LEFT JOIN ' . USERS_TABLE . " uk ON uk.user_id = k.kh_user_id
                LEFT JOIN " . $cu_table . " cus ON cus.user_id = k.sub_user_id
                WHERE $where
                ORDER BY
                    CASE k.status
                        WHEN 'pending' THEN 1
                        WHEN 'active'  THEN 2
                        WHEN 'ended'   THEN 3
                        WHEN 'refused' THEN 4
                    END,
                    k.created_at DESC";
        try {
            $res = $db->sql_query($sql);
        } catch (\Throwable $e) {
            trigger_error('Table des keyholders introuvable. Désactivez/réactivez l\'extension pour jouer la migration v3.7.0.' . adm_back_link($this->u_action));
        }

        $counts = ['pending' => 0, 'active' => 0, 'ended' => 0, 'refused' => 0];
        $hash = generate_link_hash('force_end_kh');

        while ($row = $db->sql_fetchrow($res)) {
            $st = $row['status'];
            if (isset($counts[$st])) { $counts[$st]++; }

            $template->assign_block_vars('duos', [
                'KH_ID'        => (int) $row['kh_id'],
                'SUB_USERNAME' => $row['sub_username'],
                'SUB_COLOUR'   => $row['sub_colour'],
                'SUB_USER_ID'  => (int) $row['sub_user_id'],
                'SUB_LOCKED'   => ($row['sub_chastity_status'] === 'locked'),
                'KH_USERNAME'  => $row['kh_username'],
                'KH_COLOUR'    => $row['kh_colour'],
                'KH_USER_ID'   => (int) $row['kh_user_id'],
                'STATUS'       => $st,
                'IS_PENDING'   => ($st === 'pending'),
                'IS_ACTIVE'    => ($st === 'active'),
                'IS_ENDED'     => ($st === 'ended'),
                'IS_REFUSED'   => ($st === 'refused'),
                'CREATED_AT'   => date('d/m/Y H:i', (int) $row['created_at']),
                'ACCEPTED_AT'  => $row['accepted_at'] ? date('d/m/Y H:i', (int) $row['accepted_at']) : '—',
                'ENDED_AT'     => $row['ended_at']    ? date('d/m/Y H:i', (int) $row['ended_at'])    : '—',
                'ENDED_BY'     => (int) $row['ended_by'],
                'END_BY_ADMIN' => ((int) $row['ended_by'] === 0 && $row['ended_at']),
            ]);
        }
        $db->sql_freeresult($res);

        $template->assign_vars([
            'U_ACTION'        => $this->u_action,
            'FILTER_STATUS'   => $filter_status,
            'COUNT_PENDING'   => $counts['pending'],
            'COUNT_ACTIVE'    => $counts['active'],
            'COUNT_ENDED'     => $counts['ended'],
            'COUNT_REFUSED'   => $counts['refused'],
            'HASH'            => $hash,
        ]);
    }

    /**
     * CTR — Contrat de chasteté : supervision ACP.
     * Vue globale de tous les contrats (tous membres, tous statuts), avec
     * filtre par statut et possibilité de forcer la fin d'un contrat actif
     * ou suspendu si nécessaire (modération).
     */
    private function acp_contract_mode($template, $request, $db, $user, $contracts_table, $links_table, $articles_table, $categories_table, $phpbb_container)
    {
        // ── Aperçu HTML d'un contrat depuis l'ACP, quel que soit son statut
        // (y compris Terminé/Remplacé), pour pouvoir le relire ou s'en servir
        // de modèle. Contrairement à l'UCP, aucune restriction aux 2 parties
        // du contrat n'est appliquée ici : l'accès à ce mode ACP est déjà
        // conditionné par la permission d'administration.
        $acp_preview_id = $request->variable('acp_preview_contract', 0);
        if ($acp_preview_id > 0) {
            $chk_res = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . '
                WHERE contract_id = ' . $acp_preview_id);
            $chk_row = $db->sql_fetchrow($chk_res);
            $db->sql_freeresult($chk_res);

            if ($chk_row) {
                $generator = $phpbb_container->get('verturin.chastitytracker.contract_pdf_generator');
                $data = $generator->build_contract_data($acp_preview_id, true);
                if ($data) {
                    header('Content-Type: text/html; charset=utf-8');
                    echo $generator->generate_html($data);
                    exit;
                }
            }
            trigger_error($user->lang['ACP_CONTRACT_NOT_FOUND'] . adm_back_link($this->u_action));
        }

        // ── Réparer les contrats "Remplacé" dont le remplaçant a été
        // supprimé depuis (un brouillon jamais soumis peut être supprimé
        // complètement par l'encagé) : les repasser en "Terminé" pour ne
        // plus afficher un statut trompeur pointant vers un contrat fantôme.
        if ($request->is_set_post('fix_orphan_replaced')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $orphans_res = $db->sql_query("SELECT contract_id, replaced_by FROM " . $contracts_table . "
                WHERE status = 'replaced' AND replaced_by > 0");
            $fixed_count = 0;
            while ($orphan_row = $db->sql_fetchrow($orphans_res)) {
                $target_chk = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . '
                    WHERE contract_id = ' . (int) $orphan_row['replaced_by']);
                $target_row = $db->sql_fetchrow($target_chk);
                $db->sql_freeresult($target_chk);
                if (!$target_row) {
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'ended', replaced_by = 0, updated_time = " . time() . '
                        WHERE contract_id = ' . (int) $orphan_row['contract_id']);
                    $fixed_count++;
                }
            }
            $db->sql_freeresult($orphans_res);
            trigger_error(sprintf($user->lang['ACP_CONTRACT_ORPHAN_FIXED'], $fixed_count) . adm_back_link($this->u_action));
        }

        // ── Gestion des catégories d'articles ──
        if ($request->is_set_post('delete_category')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('category_id_del', 0);
            if ($cid > 0) {
                $chk = $db->sql_query('SELECT category_key, is_locked FROM ' . $categories_table . ' WHERE category_id = ' . $cid);
                $chk_row = $db->sql_fetchrow($chk);
                $db->sql_freeresult($chk);
                if ($chk_row && !$chk_row['is_locked']) {
                    // Empêcher la suppression si des articles de la
                    // bibliothèque référencent encore cette catégorie —
                    // sinon leur libellé de catégorie devient orphelin
                    // (clé technique introuvable, affichage cassé).
                    $usage_res = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $articles_table . "
                        WHERE category = '" . $db->sql_escape($chk_row['category_key']) . "'");
                    $usage_count = (int) $db->sql_fetchfield('cnt');
                    $db->sql_freeresult($usage_res);

                    if ($usage_count > 0) {
                        trigger_error($user->lang['ACP_CONTRACT_CATEGORY_IN_USE'] . adm_back_link($this->u_action), E_USER_WARNING);
                    }

                    $db->sql_query('DELETE FROM ' . $categories_table . ' WHERE category_id = ' . $cid);
                }
            }
            trigger_error($user->lang['ACP_CONTRACT_CATEGORY_DELETED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit_category')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid    = (int) $request->variable('category_id_edit', 0);
            $c_label= $request->variable('category_label', '', true);

            if ($c_label === '') {
                trigger_error($user->lang['ACP_CONTRACT_CATEGORY_LABEL_REQUIRED'] . adm_back_link($this->u_action), E_USER_WARNING);
            }

            if ($cid > 0) {
                $db->sql_query('UPDATE ' . $categories_table . " SET
                    label = '" . $db->sql_escape($c_label) . "'
                    WHERE category_id = " . $cid . ' AND is_locked = 0');
            } else {
                $c_key = 'cat_' . substr(md5(uniqid()), 0, 8);
                $max_res = $db->sql_query('SELECT MAX(sort_order) AS m FROM ' . $categories_table);
                $next_order = ((int) $db->sql_fetchfield('m')) + 1;
                $db->sql_freeresult($max_res);
                $db->sql_query('INSERT INTO ' . $categories_table . ' ' . $db->sql_build_array('INSERT', [
                    'category_key' => $c_key,
                    'label'        => $c_label,
                    'sort_order'   => $next_order,
                    'is_locked'    => 0,
                    'created_time' => time(),
                ]));
            }
            trigger_error($user->lang['ACP_CONTRACT_CATEGORY_SAVED'] . adm_back_link($this->u_action));
        }

        // ── Forcer une catégorie en première position ──
        // Sa sort_order est mise à 0 (réservé) ; toutes les autres sont
        // renumérotées à partir de 1, dans leur ordre actuel.
        if ($request->is_set_post('set_category_first')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('category_id_first', 0);
            if ($cid > 0) {
                $others_res = $db->sql_query('SELECT category_id FROM ' . $categories_table . '
                    WHERE category_id <> ' . $cid . ' ORDER BY sort_order ASC');
                $order = 1;
                while ($o_row = $db->sql_fetchrow($others_res)) {
                    $db->sql_query('UPDATE ' . $categories_table . ' SET sort_order = ' . $order . '
                        WHERE category_id = ' . (int) $o_row['category_id']);
                    $order++;
                }
                $db->sql_freeresult($others_res);
                $db->sql_query('UPDATE ' . $categories_table . ' SET sort_order = 0 WHERE category_id = ' . $cid);
            }
            trigger_error($user->lang['ACP_CONTRACT_CATEGORY_SAVED'] . adm_back_link($this->u_action));
        }

        // ── Monter / descendre une catégorie (échange avec la voisine) ──
        // La catégorie en position 0 (première forcée) n'est jamais concernée
        // par ces déplacements : on ne réordonne qu'entre les autres.
        if ($request->is_set_post('move_category_up') || $request->is_set_post('move_category_down')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cid = (int) $request->variable('category_id_move', 0);
            $direction = $request->is_set_post('move_category_up') ? 'up' : 'down';

            $cur_res = $db->sql_query('SELECT sort_order FROM ' . $categories_table . ' WHERE category_id = ' . $cid);
            $cur_row = $db->sql_fetchrow($cur_res);
            $db->sql_freeresult($cur_res);

            if ($cur_row && (int) $cur_row['sort_order'] > 0) {
                $cur_order = (int) $cur_row['sort_order'];
                if ($direction === 'up') {
                    $swap_res = $db->sql_query('SELECT category_id, sort_order FROM ' . $categories_table . '
                        WHERE sort_order > 0 AND sort_order < ' . $cur_order . '
                        ORDER BY sort_order DESC');
                } else {
                    $swap_res = $db->sql_query('SELECT category_id, sort_order FROM ' . $categories_table . '
                        WHERE sort_order > ' . $cur_order . '
                        ORDER BY sort_order ASC');
                }
                $swap_row = $db->sql_fetchrow($swap_res);
                $db->sql_freeresult($swap_res);

                if ($swap_row) {
                    $db->sql_query('UPDATE ' . $categories_table . ' SET sort_order = ' . (int) $swap_row['sort_order'] . ' WHERE category_id = ' . $cid);
                    $db->sql_query('UPDATE ' . $categories_table . ' SET sort_order = ' . $cur_order . ' WHERE category_id = ' . (int) $swap_row['category_id']);
                }
            }
            trigger_error($user->lang['ACP_CONTRACT_CATEGORY_SAVED'] . adm_back_link($this->u_action));
        }

        // Liste des catégories pour affichage et pour les select des formulaires
        $categories_list = [];
        $cat_res = $db->sql_query('SELECT * FROM ' . $categories_table . ' ORDER BY sort_order ASC');
        $all_cats_ordered = [];
        while ($cat_row = $db->sql_fetchrow($cat_res)) {
            $all_cats_ordered[] = $cat_row;
        }
        $db->sql_freeresult($cat_res);

        foreach ($all_cats_ordered as $idx => $cat_row) {
            $categories_list[$cat_row['category_key']] = $cat_row['label'];
            $template->assign_block_vars('categories', [
                'ID'        => (int) $cat_row['category_id'],
                'KEY'       => $cat_row['category_key'],
                'LABEL'     => $cat_row['label'],
                'IS_LOCKED' => (bool) $cat_row['is_locked'],
                'IS_FIRST'  => ((int) $cat_row['sort_order'] === 0),
                'CAN_MOVE_UP'   => ((int) $cat_row['sort_order'] > 1),
                'CAN_MOVE_DOWN' => ((int) $cat_row['sort_order'] > 0 && $idx < count($all_cats_ordered) - 1),
            ]);
        }

        // ── Gestion de la bibliothèque d'articles modèles ──
        if ($request->is_set_post('delete_article')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $aid = (int) $request->variable('article_id_del', 0);
            if ($aid > 0) {
                // Vérifier que l'article n'est pas déjà utilisé dans un
                // contrat (proposé, en attente ou validé) avant suppression.
                $usage_res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $links_table . '
                    WHERE article_id = ' . $aid . " AND proposal_status IN ('approved', 'pending')");
                $usage_count = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($usage_res);

                if ($usage_count > 0) {
                    trigger_error($user->lang['ACP_CONTRACT_ARTICLE_IN_USE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }

                $db->sql_query('DELETE FROM ' . $articles_table . ' WHERE article_id = ' . $aid);
            }
            trigger_error($user->lang['ACP_CONTRACT_ARTICLE_DELETED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit_article')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $aid      = (int) $request->variable('article_id_edit', 0);
            $a_title  = $request->variable('article_title', '', true);
            $a_body   = $request->variable('article_body', '', true);
            $a_cat    = $request->variable('article_category', '');
            $a_scope  = $request->variable('article_scope', 'global'); // 'global' ou 'personal'

            if ($a_title === '') {
                trigger_error($user->lang['ACP_CONTRACT_ARTICLE_TITLE_REQUIRED'] . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $is_global = ($a_scope === 'global') ? 1 : 0;

            if ($aid > 0) {
                // Un article déjà VALIDÉ dans un contrat ACTIF ne peut plus
                // être modifié : le contrat actif est figé, son contenu ne
                // doit plus pouvoir bouger rétroactivement via la bibliothèque.
                $active_use_res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $links_table . ' l
                    LEFT JOIN ' . $contracts_table . " c ON c.contract_id = l.contract_id
                    WHERE l.article_id = " . $aid . "
                      AND l.proposal_status = 'approved'
                      AND c.status = 'active'");
                $active_use_count = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($active_use_res);

                if ($active_use_count > 0) {
                    trigger_error($user->lang['ACP_CONTRACT_ARTICLE_LOCKED_ACTIVE'] . adm_back_link($this->u_action), E_USER_WARNING);
                }

                $db->sql_query('UPDATE ' . $articles_table . " SET
                    title = '" . $db->sql_escape($a_title) . "',
                    body = '" . $db->sql_escape($a_body) . "',
                    category = '" . $db->sql_escape($a_cat) . "',
                    is_global = " . $is_global . ',
                    updated_time = ' . time() . '
                    WHERE article_id = ' . $aid);
            } else {
                $db->sql_query('INSERT INTO ' . $articles_table . ' ' . $db->sql_build_array('INSERT', [
                    'user_id'      => 0,
                    'title'        => $a_title,
                    'body'         => $a_body,
                    'is_draft'     => 0,
                    'is_global'    => $is_global,
                    'category'     => $a_cat,
                    'created_time' => time(),
                    'updated_time' => time(),
                ]));
            }
            trigger_error($user->lang['ACP_CONTRACT_ARTICLE_SAVED'] . adm_back_link($this->u_action));
        }

        // ── Valider un article personnalisé proposé par un membre, en le
        // faisant rejoindre la bibliothèque (global ou personnel) ──
        if ($request->is_set_post('approve_submitted_article')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $link_id  = (int) $request->variable('submitted_link_id', 0);
            $a_cat    = $request->variable('submitted_category', 'personnalise');
            $a_scope  = $request->variable('submitted_scope', 'global');

            $sub_res = $db->sql_query('SELECT l.article_title, l.article_body, l.proposed_by, c.encage_user_id
                FROM ' . $links_table . ' l
                LEFT JOIN ' . $contracts_table . ' c ON c.contract_id = l.contract_id
                WHERE l.link_id = ' . $link_id . ' AND l.article_id = 0');
            $sub_row = $db->sql_fetchrow($sub_res);
            $db->sql_freeresult($sub_res);

            if ($sub_row) {
                $is_global = ($a_scope === 'global') ? 1 : 0;
                $owner_id  = $is_global ? 0 : (int) $sub_row['encage_user_id'];

                $db->sql_query('INSERT INTO ' . $articles_table . ' ' . $db->sql_build_array('INSERT', [
                    'user_id'      => $owner_id,
                    'submitted_by' => (int) $sub_row['proposed_by'],
                    'title'        => $sub_row['article_title'],
                    'body'         => $sub_row['article_body'],
                    'is_draft'     => 0,
                    'is_global'    => $is_global,
                    'category'     => $a_cat,
                    'created_time' => time(),
                    'updated_time' => time(),
                ]));

                $db->sql_query("UPDATE " . $links_table . "
                    SET admin_review_status = 'approved'
                    WHERE link_id = " . $link_id);
            }
            trigger_error($user->lang['ACP_CONTRACT_ARTICLE_SAVED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('reject_submitted_article')) {
            if (!check_form_key('acp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $link_id = (int) $request->variable('submitted_link_id', 0);
            // Refuser ne fait rien de plus ici : l'article reste dans son
            // contrat d'origine (géré entre les 2 parties), il ne rejoint
            // simplement pas la bibliothèque de modèles réutilisables.
            $db->sql_query("UPDATE " . $links_table . "
                SET admin_review_status = 'rejected'
                WHERE link_id = " . $link_id);
            trigger_error($user->lang['ACP_CONTRACT_ARTICLE_NOT_ADDED'] . adm_back_link($this->u_action));
        }

        // Liste des articles modèles pour affichage, groupés par catégorie
        $art_res = $db->sql_query('SELECT a.*, us.username AS submitter_username
            FROM ' . $articles_table . ' a
            LEFT JOIN ' . USERS_TABLE . ' us ON us.user_id = a.submitted_by
            ORDER BY a.category ASC, a.title ASC');
        while ($art_row = $db->sql_fetchrow($art_res)) {
            $template->assign_block_vars('lib_articles', [
                'ID'       => (int) $art_row['article_id'],
                'TITLE'    => $art_row['title'],
                'BODY'     => $art_row['body'],
                'CATEGORY' => $art_row['category'],
                'CATEGORY_LABEL' => $categories_list[$art_row['category']] ?? $art_row['category'],
                'IS_GLOBAL'=> (bool) $art_row['is_global'],
                'SUBMITTER_USERNAME' => (int) $art_row['submitted_by'] > 0 ? $art_row['submitter_username'] : '',
            ]);
        }
        $db->sql_freeresult($art_res);

        // ── Action : forcer la fin d'un contrat ──
        if ($request->is_set_post('force_end_contract')) {
            if (!check_link_hash($request->variable('hash', ''), 'force_end_contract')) {
                trigger_error('Form invalid' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $contract_id = (int) $request->variable('contract_id', 0);
            if ($contract_id > 0) {
                $db->sql_query('UPDATE ' . $contracts_table . "
                    SET status = 'ended', ended_time = " . time() . "
                    WHERE contract_id = $contract_id AND status IN ('draft', 'pending_validation', 'active', 'suspended')");
                trigger_error($user->lang['ACP_CHASTITY_CONTRACT_ENDED'] . adm_back_link($this->u_action));
            }
        }

        // Filtre par statut
        $filter_status = $request->variable('status', 'all');
        if ($request->is_set_post('clear_filter')) {
            $filter_status = 'all';
        }
        $allowed = ['all', 'draft', 'pending_validation', 'active', 'suspended', 'ended', 'replaced'];
        if (!in_array($filter_status, $allowed, true)) { $filter_status = 'all'; }

        $where = ($filter_status === 'all') ? '1=1' : "c.status = '" . $db->sql_escape($filter_status) . "'";

        $sql = 'SELECT c.*,
                       ue.username AS encage_username, ue.user_colour AS encage_colour,
                       uk.username AS kh_username, uk.user_colour AS kh_colour
                FROM ' . $contracts_table . ' c
                LEFT JOIN ' . USERS_TABLE . ' ue ON ue.user_id = c.encage_user_id
                LEFT JOIN ' . USERS_TABLE . " uk ON uk.user_id = c.kh_user_id
                WHERE $where
                ORDER BY
                    CASE c.status
                        WHEN 'pending_validation' THEN 1
                        WHEN 'active'    THEN 2
                        WHEN 'suspended' THEN 3
                        WHEN 'draft'     THEN 4
                        WHEN 'ended'     THEN 5
                        WHEN 'replaced'  THEN 6
                    END,
                    c.created_time DESC";
        try {
            $res = $db->sql_query($sql);
        } catch (\Throwable $e) {
            trigger_error('Table des contrats introuvable. Désactivez/réactivez l\'extension pour jouer les migrations du CTR.' . adm_back_link($this->u_action));
        }

        $counts = ['draft' => 0, 'pending_validation' => 0, 'active' => 0, 'suspended' => 0, 'ended' => 0, 'replaced' => 0];
        $hash = generate_link_hash('force_end_contract');

        while ($row = $db->sql_fetchrow($res)) {
            $st = $row['status'];
            if (isset($counts[$st])) { $counts[$st]++; }

            $kh_name = '';
            if ((int) $row['kh_user_id'] > 0) {
                $kh_name = $row['kh_username'];
            } elseif ($row['kh_external_name'] !== '') {
                $kh_name = $row['kh_external_name'] . ' (' . $user->lang['ACP_CHASTITY_CONTRACT_EXTERNAL'] . ')';
            }

            $nb_articles_res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $links_table . '
                WHERE contract_id = ' . (int) $row['contract_id']);
            $nb_articles = (int) $db->sql_fetchfield('cnt');
            $db->sql_freeresult($nb_articles_res);

            $template->assign_block_vars('contracts', [
                'CONTRACT_ID'      => (int) $row['contract_id'],
                'ENCAGE_USERNAME'  => $row['encage_username'],
                'ENCAGE_COLOUR'    => $row['encage_colour'],
                'ENCAGE_USER_ID'   => (int) $row['encage_user_id'],
                'KH_NAME'          => $kh_name,
                'KH_USER_ID'       => (int) $row['kh_user_id'],
                'STATUS'           => $st,
                'STATUS_LABEL'     => $user->lang['CHASTITY_CONTRACT_STATUS_' . strtoupper($st)],
                'IS_DRAFT'         => ($st === 'draft'),
                'IS_PENDING'       => ($st === 'pending_validation'),
                'IS_ACTIVE'        => ($st === 'active'),
                'IS_SUSPENDED'     => ($st === 'suspended'),
                'IS_ENDED'         => ($st === 'ended'),
                'IS_REPLACED'      => ($st === 'replaced'),
                'CAN_FORCE_END'    => in_array($st, ['draft', 'pending_validation', 'active', 'suspended'], true),
                'NB_ARTICLES'      => $nb_articles,
                'CREATED'          => date('d/m/Y H:i', (int) $row['created_time']),
                'VALIDATED'        => $row['validated_time'] ? date('d/m/Y H:i', (int) $row['validated_time']) : '—',
                'ENDED'            => $row['ended_time'] ? date('d/m/Y H:i', (int) $row['ended_time']) : '—',
            ]);
        }
        $db->sql_freeresult($res);

        // ── Articles PERSONNALISÉS en attente d'examen admin (pour devenir
        // un modèle réutilisable, global ou personnel). Distinct du statut
        // de validation entre les 2 parties du contrat (proposal_status). ──
        $pending_res = $db->sql_query('SELECT l.*, c.encage_user_id, c.status AS contract_status,
                       up.username AS proposer_username, up.user_colour AS proposer_colour,
                       ue.username AS encage_username
                FROM ' . $links_table . ' l
                LEFT JOIN ' . $contracts_table . ' c ON c.contract_id = l.contract_id
                LEFT JOIN ' . USERS_TABLE . ' up ON up.user_id = l.proposed_by
                LEFT JOIN ' . USERS_TABLE . " ue ON ue.user_id = c.encage_user_id
                WHERE l.article_id = 0 AND l.admin_review_status = 'pending'
                ORDER BY l.created_time DESC");
        $pending_hash = generate_link_hash('review_submitted_article');
        while ($p_row = $db->sql_fetchrow($pending_res)) {
            $template->assign_block_vars('pending_articles', [
                'LINK_ID'         => (int) $p_row['link_id'],
                'CONTRACT_ID'     => (int) $p_row['contract_id'],
                'TITLE'           => $p_row['article_title'],
                'BODY'            => $p_row['article_body'],
                'ENCAGE_USERNAME' => $p_row['encage_username'],
                'PROPOSER_USERNAME' => $p_row['proposer_username'],
                'SUGGESTED_CATEGORY' => $p_row['category'],
                'CREATED'         => date('d/m/Y H:i', (int) $p_row['created_time']),
            ]);
        }
        $db->sql_freeresult($pending_res);

        $template->assign_vars([
            'U_ACTION'                => $this->u_action,
            'FILTER_STATUS'           => $filter_status,
            'COUNT_DRAFT'             => $counts['draft'],
            'COUNT_PENDING_VAL'       => $counts['pending_validation'],
            'COUNT_ACTIVE_C'          => $counts['active'],
            'COUNT_SUSPENDED'         => $counts['suspended'],
            'COUNT_ENDED_C'           => $counts['ended'],
            'COUNT_REPLACED'          => $counts['replaced'],
            'HASH'                    => $hash,
        ]);
    }

}
