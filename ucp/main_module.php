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
    private $container;
    private $period_calc;

    function main($id, $mode)
    {
        global $user, $template, $request, $db, $phpbb_container, $auth, $config;

        $this->container = $phpbb_container;
        try {
            $this->period_calc = $phpbb_container->get('verturin.chastitytracker.period_calculator');
        } catch (\Throwable $e) {
            $this->period_calc = new \verturin\chastitytracker\service\period_calculator($user);
        }
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
                $cal_real_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexits');
                $cal_act_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activities');
                $cal_ce_reasons_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexit_reasons');
                $cal_act_reasons_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activity_reasons');
                $this->calendar_mode($user, $template, $request, $db, $periods_table, $auth, $config, $cal_real_table, $cal_act_table, $cal_ce_reasons_table, $cal_act_reasons_table);
            break;

            case 'statistics':
                $stat_ce_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexits');
                $stat_act_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activities');
                $this->statistics_mode($user, $template, $db, $periods_table, $stat_ce_table, $stat_act_table);
            break;

            case 'locktober':
                $this->locktober_mode($user, $template, $request, $db, $periods_table, $auth, $config);
            break;

            case 'rewards':
                $rewards_calc = $phpbb_container->get('verturin.chastitytracker.rewards_calculator');
                $rh_table     = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_rewards_history');
                $pc_table     = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_perfect_counts');
                $this->rewards_mode($user, $template, $db, $config, $rewards_calc, $rh_table, $pc_table);
            break;

            case 'yearview':
                $yv_real_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexits');
                $yv_act_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activities');
                $this->yearview_mode($user, $template, $request, $db, $periods_table, $yv_real_table, $yv_act_table);
            break;

            case 'cageexits':
                $real_table   = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexits');
                $real_r_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cageexit_reasons');
                $this->cageexits_mode($user, $template, $request, $db, $periods_table, $real_table, $real_r_table, $config);
            break;

            case 'activities':
                $act_table   = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activities');
                $act_r_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_activity_reasons');
                $this->activities_mode($user, $template, $request, $db, $periods_table, $act_table, $act_r_table, $config);
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

            case 'cage_collection':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables) {
                    trigger_error('Les tables cages ne sont pas installées. Contactez un administrateur.');
                }
                $this->ucp_cage_collection_mode($template, $request, $db, $user, $cage_tables);
                $this->tpl_name = 'ucp_chastity_cage_collection';
                $this->page_title = $user->lang['UCP_CHASTITY_CAGE_COLLECTION'];
            break;

            case 'cage_catalog':
                $cage_tables = $this->get_cage_tables($phpbb_container, $db);
                if (!$cage_tables) {
                    trigger_error('Les tables cages ne sont pas installées. Contactez un administrateur.');
                }
                $this->ucp_cage_catalog_mode($template, $request, $db, $user, $cage_tables, $config);
                $this->tpl_name = 'ucp_chastity_cage_catalog';
                $this->page_title = $user->lang['UCP_CHASTITY_CAGE_CATALOG'];
            break;

            case 'api_access':
                $prefs_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_user_prefs');
                $this->api_access_mode($user, $template, $request, $db, $prefs_table, $config);
                $this->tpl_name = 'ucp_chastity_api_access';
                $this->page_title = $user->lang['UCP_CHASTITY_API_ACCESS'];
            break;

            case 'contract':
                $contracts_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contracts');
                $articles_table  = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_articles');
                $links_table     = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_links');
                $kh_table        = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
                $categories_table= $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_contract_categories');
                $this->contract_mode($user, $template, $request, $db, $contracts_table, $articles_table, $links_table, $kh_table, $categories_table, $config, $phpbb_container);
                $this->tpl_name = 'ucp_chastity_contract';
                $this->page_title = $user->lang['UCP_CHASTITY_CONTRACT'];
            break;

            case 'my_keyholder':
                $kh_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
                $this->my_keyholder_mode($user, $template, $request, $db, $kh_table, $config);
                $this->tpl_name = 'ucp_chastity_my_keyholder';
                $this->page_title = $user->lang['UCP_CHASTITY_MY_KEYHOLDER'];
            break;

            case 'my_subs':
                $kh_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
                $cu_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_users');
                $cache_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_cache');
                $this->my_subs_mode($user, $template, $request, $db, $kh_table, $cu_table, $cache_table, $config);
                $this->tpl_name = 'ucp_chastity_my_subs';
                $this->page_title = $user->lang['UCP_CHASTITY_MY_SUBS'];
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
        $sql = "SHOW TABLES LIKE '" . $db->sql_escape($tables['catalog']) . "'";
        $res = $db->sql_query($sql);
        $exists = $db->sql_fetchrow($res);
        $db->sql_freeresult($res);
        return $exists ? $tables : false;
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
        // Total des jours des périodes complétées : on somme les SECONDES
        // RÉELLES (end_date - start_date) de chaque période, PAS les
        // days_count déjà arrondis individuellement. Sommer des valeurs
        // arrondies période par période perd de la précision : plusieurs
        // périodes de moins de 24h contribueraient chacune 0 au total, même
        // si leur cumul réel dépasse un jour plein.
        $sql = 'SELECT SUM(end_date - start_date) as total_seconds FROM ' . $periods_table . "
                WHERE user_id = " . (int) $user_id . " AND status = 'completed' AND end_date > start_date";
        $result = $db->sql_query($sql);
        $total_seconds = (int) $db->sql_fetchfield('total_seconds');
        $db->sql_freeresult($result);
        $total_days = (int) floor($total_seconds / 86400);

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

    private function calendar_mode($user, $template, $request, $db, $periods_table, $auth, $config, $cageexits_table = '', $activities_table = '', $cageexit_reasons_table = '', $activity_reasons_table = '')
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
            $this->trigger_user_reward_recalc((int) $user->data['user_id']);

            // Cage sélectionnée (optionnel) — uniquement si tables v3.5.0 existent
            $selected_cage_id = (int) $request->variable('cage_id', 0);
            if ($selected_cage_id > 0)
            {
                try {
                    $usage_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
                    $sql_check = "SHOW TABLES LIKE '" . $db->sql_escape($usage_table) . "'";
                    $res_check = $db->sql_query($sql_check);
                    if ($db->sql_fetchrow($res_check))
                    {
                        $db->sql_query('INSERT INTO ' . $usage_table . ' ' . $db->sql_build_array('INSERT', [
                            'user_id'    => (int) $user->data['user_id'],
                            'period_id'  => (int) $period_id,
                            'cage_id'    => $selected_cage_id,
                            'start_date' => $start_timestamp,
                            'end_date'   => 0,
                        ]));
                    }
                    $db->sql_freeresult($res_check);
                } catch (\Exception $e) {}
            }

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

                // Clôturer l'usage cage en cours (si tables existent)
                try {
                    $usage_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
                    $sql_check = "SHOW TABLES LIKE '" . $db->sql_escape($usage_table) . "'";
                    $res_check = $db->sql_query($sql_check);
                    if ($db->sql_fetchrow($res_check))
                    {
                        $db->sql_query('UPDATE ' . $usage_table . ' SET end_date = ' . (int) $end_date
                            . ' WHERE period_id = ' . (int) $period_id . ' AND end_date = 0');
                    }
                    $db->sql_freeresult($res_check);
                } catch (\Exception $e) {}

                $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);
                trigger_error($user->lang['CHASTITY_PERIOD_ENDED']);
            }
        }

        // Changer de cage en cours de période
        if ($request->is_set_post('change_cage'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $new_cage_id = (int) $request->variable('new_cage_id', 0);
            $period_id   = (int) $request->variable('period_id', 0);
            $now = time();
            try {
                $usage_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
                // Clôturer l'usage en cours
                $db->sql_query('UPDATE ' . $usage_table . ' SET end_date = ' . $now
                    . ' WHERE period_id = ' . $period_id . ' AND user_id = ' . (int) $user->data['user_id'] . ' AND end_date = 0');
                // Nouvel usage
                if ($new_cage_id > 0)
                {
                    $db->sql_query('INSERT INTO ' . $usage_table . ' ' . $db->sql_build_array('INSERT', [
                        'user_id'    => (int) $user->data['user_id'],
                        'period_id'  => $period_id,
                        'cage_id'    => $new_cage_id,
                        'start_date' => $now,
                        'end_date'   => 0,
                    ]));
                }
            } catch (\Exception $e) {}
            trigger_error($user->lang['CONFIG_UPDATED']);
        }

        // Supprimer une période
        if ($request->is_set_post('edit_period'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $period_id = $request->variable('period_id', 0);

            // Vérifier que la période appartient bien au membre et n'est pas active
            // (une période active se modifie via "Terminer la période").
            $chk = $db->sql_query('SELECT status FROM ' . $periods_table . '
                WHERE period_id = ' . (int) $period_id . '
                  AND user_id = ' . (int) $user->data['user_id']);
            $chk_row = $db->sql_fetchrow($chk);
            $db->sql_freeresult($chk);

            if (!$chk_row || $chk_row['status'] === 'active')
            {
                trigger_error($user->lang['CHASTITY_PERIOD_EDIT_DENIED'], E_USER_WARNING);
            }

            $start_date_str = $request->variable('edit_start_date', '');
            $start_time_str = $request->variable('edit_start_time', '00:00');
            $end_date_str   = $request->variable('edit_end_date', '');
            $end_time_str   = $request->variable('edit_end_time', '00:00');
            $notes          = $request->variable('edit_notes', '', true);

            $new_start = $start_date_str !== '' ? strtotime($start_date_str . ' ' . $start_time_str) : false;
            $new_end   = $end_date_str !== '' ? strtotime($end_date_str . ' ' . $end_time_str) : false;

            if ($new_start === false || $new_start > time())
            {
                trigger_error($user->lang['CHASTITY_PERIOD_EDIT_BAD_START'], E_USER_WARNING);
            }

            // Une période TERMINÉE doit obligatoirement garder une date de fin :
            // vider ce champ créerait un état incohérent (completed + end_date=0)
            // qui fausserait tous les calculs de totaux (SUM(days_count) WHERE
            // status='completed' présume toujours end_date > 0 pour ce statut).
            if ($new_end === false)
            {
                trigger_error($user->lang['CHASTITY_PERIOD_EDIT_BAD_END'], E_USER_WARNING);
            }
            if ($new_end <= $new_start)
            {
                trigger_error($user->lang['CHASTITY_PERIOD_EDIT_BAD_END'], E_USER_WARNING);
            }

            // days_count TOUJOURS recalculé pour rester cohérent avec les
            // nouvelles dates (jamais laissé à son ancienne valeur).
            $days_count = (int) floor(($new_end - $new_start) / 86400);

            $update = [
                'start_date' => (int) $new_start,
                'end_date'   => (int) $new_end,
                'days_count' => $days_count,
                'notes'      => $notes,
            ];

            $db->sql_query('UPDATE ' . $periods_table . ' SET ' . $db->sql_build_array('UPDATE', $update) . '
                WHERE period_id = ' . (int) $period_id . '
                  AND user_id = ' . (int) $user->data['user_id']);

            $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);
            $this->trigger_user_reward_recalc((int) $user->data['user_id']);
            trigger_error($user->lang['CHASTITY_PERIOD_EDITED']);
        }

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

            // Supprimer aussi les cage_usage liés à cette période
            try {
                $usage_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
                $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($usage_table) . "'");
                if ($db->sql_fetchrow($check))
                {
                    $db->sql_query('DELETE FROM ' . $usage_table . ' WHERE period_id = ' . (int) $period_id . ' AND user_id = ' . (int) $user->data['user_id']);
                }
                $db->sql_freeresult($check);
            } catch (\Exception $e) {}

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
        $current_secs = 0;
        $active       = null;

        foreach ($periods as $period)
        {
            $is_active = ($period['status'] === 'active');
            if ($is_active)
            {
                $has_active   = true;
                $active       = $period;
                $current_secs = max(0, time() - (int) $period['start_date']);
                $current_days = (int) floor($current_secs / 86400);
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
                'EDIT_START_DATE'      => date('Y-m-d', (int) $period['start_date']),
                'EDIT_START_TIME'      => date('H:i', (int) $period['start_date']),
                'EDIT_END_DATE'        => ((int) $period['end_date'] > 0) ? date('Y-m-d', (int) $period['end_date']) : '',
                'EDIT_END_TIME'        => ((int) $period['end_date'] > 0) ? date('H:i', (int) $period['end_date']) : '',
                'STATUS'               => $user->lang['CHASTITY_STATUS_' . strtoupper($period['status'])],
                'DAYS_COUNT'           => $this->format_duration($duration_seconds),
                'NOTES'                => $period['notes'],
                'IS_ACTIVE'            => $is_active,
                'IS_PERMANENT'         => (bool) $period['is_permanent'],
                'IS_LOCKTOBER'         => (bool) $period['is_locktober'],
                'CAN_DELETE'           => !$is_active,
                'CAN_EDIT'             => !$is_active,
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
        
        // Jours de réalisation et activités pour le mois — avec tous les détails pour tooltip
        $cageexit_days = [];   // date => true
        $activity_days = [];   // date => true
        $day_tooltips  = [];   // date => texte tooltip
        if (!empty($cageexits_table))
        {
            // Charger libellés des motifs sortie
            $ce_reason_labels = [];
            if (!empty($cageexit_reasons_table)) {
                $res = $db->sql_query('SELECT reason_id, label FROM ' . $cageexit_reasons_table);
                while ($r = $db->sql_fetchrow($res)) { $ce_reason_labels[(int)$r['reason_id']] = $r['label']; }
                $db->sql_freeresult($res);
            }

            $res = $db->sql_query('SELECT cageexit_date, duration_min, reason_id, notes FROM ' . $cageexits_table . ' WHERE user_id=' . (int)$user->data['user_id'] . ' AND cageexit_date>=' . $first_day_month . ' AND cageexit_date<=' . $last_day_month . ' ORDER BY cageexit_date ASC');
            while ($r = $db->sql_fetchrow($res)) {
                $date = date('Y-m-d', (int)$r['cageexit_date']);
                $cageexit_days[$date] = true;

                $duration = (int) $r['duration_min'];
                $reason = isset($ce_reason_labels[(int)$r['reason_id']]) ? $ce_reason_labels[(int)$r['reason_id']] : '';
                $hours = floor($duration / 60);
                $mins  = $duration % 60;
                $dur_text = $duration > 0 ? ($hours > 0 ? ($hours . 'h' . ($mins > 0 ? sprintf('%02d', $mins) : '')) : ($mins . ' min')) : '';

                $line = '🚪 ' . (isset($user->lang['CHASTITY_CE_TOOLTIP']) ? $user->lang['CHASTITY_CE_TOOLTIP'] : 'Sortie');
                if ($reason !== '')   { $line .= ' : ' . $reason; }
                if ($dur_text !== '') { $line .= ' (' . $dur_text . ')'; }
                if (!empty($r['notes'])) {
                    $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                    if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                    $line .= ' — ' . $note;
                }
                if (!isset($day_tooltips[$date])) { $day_tooltips[$date] = []; }
                $day_tooltips[$date][] = $line;
            }
            $db->sql_freeresult($res);
        }
        if (!empty($activities_table))
        {
            // Charger libellés des motifs activité
            $act_reason_labels = [];
            if (!empty($activity_reasons_table)) {
                $res = $db->sql_query('SELECT reason_id, label FROM ' . $activity_reasons_table);
                while ($r = $db->sql_fetchrow($res)) { $act_reason_labels[(int)$r['reason_id']] = $r['label']; }
                $db->sql_freeresult($res);
            }

            $res = $db->sql_query('SELECT activity_date, reason_id, intensity, notes FROM ' . $activities_table . ' WHERE user_id=' . (int)$user->data['user_id'] . ' AND activity_date>=' . $first_day_month . ' AND activity_date<=' . $last_day_month . ' ORDER BY activity_date ASC');
            while ($r = $db->sql_fetchrow($res)) {
                $date = date('Y-m-d', (int)$r['activity_date']);
                $activity_days[$date] = true;

                $reason = isset($act_reason_labels[(int)$r['reason_id']]) ? $act_reason_labels[(int)$r['reason_id']] : '';
                $intensity = (string) $r['intensity'];
                $intensity_lang_key = 'CHASTITY_INTENSITY_' . strtoupper($intensity);
                $intensity_label = isset($user->lang[$intensity_lang_key]) ? $user->lang[$intensity_lang_key] : $intensity;

                $line = '🔥 ' . (isset($user->lang['CHASTITY_ACT_TOOLTIP']) ? $user->lang['CHASTITY_ACT_TOOLTIP'] : 'Activité');
                if ($reason !== '')   { $line .= ' : ' . $reason; }
                if ($intensity !== '' && $intensity_label !== '') { $line .= ' [' . $intensity_label . ']'; }
                if (!empty($r['notes'])) {
                    $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                    if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                    $line .= ' — ' . $note;
                }
                if (!isset($day_tooltips[$date])) { $day_tooltips[$date] = []; }
                $day_tooltips[$date][] = $line;
            }
            $db->sql_freeresult($res);
        }

        // Jours du mois actuel
        $today = date('Y-m-d');
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
            $is_locked = isset($locked_days[$date]);
            $is_today = ($date === $today);

            $tooltip = '';
            if (isset($day_tooltips[$date])) {
                // Joindre les lignes avec un saut de ligne (title HTML accepte \n via &#10; ou newline réel)
                $tooltip = implode("\n", $day_tooltips[$date]);
            }

            $template->assign_block_vars('calendar_days', [
                'DAY'            => $day,
                'OTHER_MONTH'    => false,
                'IS_LOCKED'      => $is_locked,
                'IS_TODAY'       => $is_today,
                'IS_CAGEEXIT'    => isset($cageexit_days[$date]),
                'IS_ACTIVITY'    => isset($activity_days[$date]),
                'TOOLTIP'        => $tooltip,
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
            'ACTIVE_PERIOD_ID'               => $has_active && isset($active['period_id']) ? (int) $active['period_id'] : 0,
            'CURRENT_DAYS'                   => $current_days,
            'CURRENT_SINCE_TEXT'             => $this->period_calc->format_duration($current_secs > 0 ? $current_secs : ($current_days * 86400)),
            'U_ACTION'                       => $this->u_action,
            'COLOR_CAGEEXIT'                 => $config['chastity_color_cageexit'] ?? 'FFF3CD',
            'COLOR_ACTIVITY'                 => $config['chastity_color_activity'] ?? 'EDE0F7',
            'COLOR_MIXED'                    => $config['chastity_color_mixed'] ?? 'F5E6D3',
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

        // Charger les cages utilisateur (si tables v3.5.0 existent)
        $current_cage_name = '';
        $current_cage_id   = 0;
        $active_period_id_for_cage = ($has_active && !empty($active)) ? (int) $active['period_id'] : 0;
        try {
            $cages_table   = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cages');
            $catalog_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_catalog');
            $usage_table   = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
            $sql_check = "SHOW TABLES LIKE '" . $db->sql_escape($cages_table) . "'";
            $res_check = $db->sql_query($sql_check);
            if ($db->sql_fetchrow($res_check))
            {
                $sql = 'SELECT uc.cage_id, cc.cage_name, cc.cage_brand FROM ' . $cages_table . ' uc
                        JOIN ' . $catalog_table . ' cc ON cc.catalog_id = uc.catalog_id
                        WHERE uc.user_id = ' . (int) $user->data['user_id'] . ' AND uc.is_active = 1
                        ORDER BY cc.cage_name ASC';
                $res = $db->sql_query($sql);
                while ($crow = $db->sql_fetchrow($res))
                {
                    $template->assign_block_vars('my_cages_select', [
                        'CAGE_ID' => (int) $crow['cage_id'],
                        'NAME'    => $crow['cage_name'],
                        'BRAND'   => $crow['cage_brand'],
                    ]);
                }
                $db->sql_freeresult($res);

                if ($active_period_id_for_cage > 0)
                {
                    $sql = 'SELECT cu.cage_id, cc.cage_name FROM ' . $usage_table . ' cu
                            JOIN ' . $cages_table . ' uc ON uc.cage_id = cu.cage_id
                            JOIN ' . $catalog_table . ' cc ON cc.catalog_id = uc.catalog_id
                            WHERE cu.period_id = ' . $active_period_id_for_cage . ' AND cu.end_date = 0';
                    $res = $db->sql_query($sql);
                    $crow = $db->sql_fetchrow($res);
                    if ($crow) {
                        $current_cage_name = $crow['cage_name'];
                        $current_cage_id   = (int) $crow['cage_id'];
                    }
                    $db->sql_freeresult($res);
                }
            }
            $db->sql_freeresult($res_check);
        } catch (\Exception $e) {}
        $template->assign_vars([
            'CURRENT_CAGE_NAME' => $current_cage_name,
            'CURRENT_CAGE_ID'   => $current_cage_id,
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
            $period_id = (int) $db->sql_nextid();
            $this->trigger_user_reward_recalc((int) $user->data['user_id']);

            // Cage sélectionnée pour cette période passée (si table existe)
            $selected_cage_id = (int) $request->variable('cage_id', 0);
            if ($selected_cage_id > 0)
            {
                try {
                    $usage_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_usage');
                    $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($usage_table) . "'");
                    if ($db->sql_fetchrow($check))
                    {
                        $db->sql_query('INSERT INTO ' . $usage_table . ' ' . $db->sql_build_array('INSERT', [
                            'user_id'    => (int) $user->data['user_id'],
                            'period_id'  => $period_id,
                            'cage_id'    => $selected_cage_id,
                            'start_date' => $start_ts,
                            'end_date'   => $end_ts,
                        ]));
                    }
                    $db->sql_freeresult($check);
                } catch (\Exception $e) {}
            }

            // Recalcul complet des totaux
            $this->recalc_user_totals($db, $periods_table, $user->data['user_id']);

            trigger_error($user->lang['CHASTITY_PAST_PERIOD_ADDED']);
        }

        $u_calendar = str_replace('&amp;mode=add_past', '&amp;mode=calendar', $this->u_action);

        // Charger les cages utilisateur (si tables v3.5.0+ existent)
        try {
            $cages_table   = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cages');
            $catalog_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_catalog');
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($cages_table) . "'");
            if ($db->sql_fetchrow($check))
            {
                $sql = 'SELECT uc.cage_id, cc.cage_name, cc.cage_brand FROM ' . $cages_table . ' uc
                        JOIN ' . $catalog_table . ' cc ON cc.catalog_id = uc.catalog_id
                        WHERE uc.user_id = ' . (int) $user->data['user_id'] . ' AND uc.is_active = 1
                        ORDER BY cc.cage_name ASC';
                $res = $db->sql_query($sql);
                while ($crow = $db->sql_fetchrow($res))
                {
                    $template->assign_block_vars('my_cages_select', [
                        'CAGE_ID' => (int) $crow['cage_id'],
                        'NAME'    => $crow['cage_name'],
                        'BRAND'   => $crow['cage_brand'],
                    ]);
                }
                $db->sql_freeresult($res);
            }
            $db->sql_freeresult($check);
        } catch (\Exception $e) {}

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

    private function statistics_mode($user, $template, $db, $periods_table, $cageexits_table = '', $activities_table = '')
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
        $current_secs  = 0;
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
				$current_secs = max(0, time() - (int) $period['start_date']);
				$days         = (int) floor($current_secs / 86400);
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

        // Compteurs sorties et activités totaux + par année + par mois
        $total_cageexits = 0;
        $total_activities = 0;
        $year_cageexits  = [];
        $year_activities = [];
        $month_cageexits  = array_fill(1, 12, 0);
        $month_activities = array_fill(1, 12, 0);

        if (!empty($cageexits_table))
        {
            $res = $db->sql_query('SELECT cageexit_date FROM ' . $cageexits_table . ' WHERE user_id=' . (int)$user->data['user_id']);
            while ($row = $db->sql_fetchrow($res))
            {
                $total_cageexits++;
                $y = (int) date('Y', (int)$row['cageexit_date']);
                $m = (int) date('n', (int)$row['cageexit_date']);
                $year_cageexits[$y]  = ($year_cageexits[$y]  ?? 0) + 1;
                if ($y === $current_year) { $month_cageexits[$m]++; }
            }
            $db->sql_freeresult($res);
        }
        if (!empty($activities_table))
        {
            $res = $db->sql_query('SELECT activity_date FROM ' . $activities_table . ' WHERE user_id=' . (int)$user->data['user_id']);
            while ($row = $db->sql_fetchrow($res))
            {
                $total_activities++;
                $y = (int) date('Y', (int)$row['activity_date']);
                $m = (int) date('n', (int)$row['activity_date']);
                $year_activities[$y]  = ($year_activities[$y]  ?? 0) + 1;
                if ($y === $current_year) { $month_activities[$m]++; }
            }
            $db->sql_freeresult($res);
        }

        // Sorties + activités de la période active
        $current_period_exits = 0;
        $current_period_activities = 0;
        $active_period_id = 0;
        foreach ($periods as $period) {
            if ($period['status'] === 'active') { $active_period_id = (int) $period['period_id']; break; }
        }
        if ($active_period_id > 0)
        {
            if (!empty($cageexits_table))
            {
                $res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $cageexits_table . '
                        WHERE user_id=' . (int) $user->data['user_id'] . ' AND period_id=' . $active_period_id);
                $current_period_exits = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($res);
            }
            if (!empty($activities_table))
            {
                $res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $activities_table . '
                        WHERE user_id=' . (int) $user->data['user_id'] . ' AND period_id=' . $active_period_id);
                $current_period_activities = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($res);
            }
        }

        $template->assign_vars([
            'TOTAL_DAYS'        => $total_days,
            'TOTAL_PERIODS'     => $total_periods,
            'CURRENT_DAYS'      => $current_days,
            'S_CURRENT_LOCKED'  => $s_locked,
            'CURRENT_SINCE_TEXT' => $this->period_calc->format_duration($current_secs > 0 ? $current_secs : ($current_days * 86400)),
            'CHASTITY_STATUS'   => $user->lang['CHASTITY_STATUS_' . strtoupper($status)],
            'S_CHASTITY_LOCKED' => $s_locked,
            'LONGEST_PERIOD'    => $longest,
            'AVERAGE_PERIOD'    => $average,
            'CURRENT_YEAR_DAYS' => isset($year_stats[$current_year]) ? $year_stats[$current_year]['days'] : 0,
            'TOTAL_CAGEEXITS'   => $total_cageexits,
            'TOTAL_ACTIVITIES'  => $total_activities,
            'S_HAS_ACTIVE_PERIOD'        => ($active_period_id > 0),
            'CURRENT_PERIOD_EXITS'       => $current_period_exits,
            'CURRENT_PERIOD_ACTIVITIES'  => $current_period_activities,
        ]);

        krsort($year_stats);
        foreach ($year_stats as $year => $stats)
        {
            $template->assign_block_vars('year_stats', [
                'YEAR'      => $year,
                'DAYS'      => $stats['days'],
                'PERIODS'   => $stats['periods'],
                'CAGEEXITS' => $year_cageexits[$year]  ?? 0,
                'ACTIVITIES'=> $year_activities[$year] ?? 0,
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
                'MONTH'      => $month_name,
                'DAYS'       => $days,
                'CAGEEXITS'  => $month_cageexits[$month]  ?? 0,
                'ACTIVITIES' => $month_activities[$month] ?? 0,
            ]);
        }
    }

    /**
     * Recalcule les récompenses stockées du membre courant après l'ajout ou la
     * modification d'une de ses périodes (complétion Locktober + périodes
     * parfaites). Tolérant aux erreurs : ne bloque jamais l'enregistrement.
     */
    private function trigger_user_reward_recalc($user_id)
    {
        global $phpbb_container, $config;
        if (empty($config['chastity_rewards_enabled']) && empty($config['chastity_locktober_enabled'])) {
            return;
        }
        try {
            $calc     = $phpbb_container->get('verturin.chastitytracker.rewards_calculator');
            $pc_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_perfect_counts');
            $calc->recalc_user($user_id, $pc_table);
        } catch (\Throwable $e) {}

        // Félicitations record (à la clôture/modification d'une période)
        try {
            $notifier = $phpbb_container->get('verturin.chastitytracker.record_notifier');
            $notifier->check_and_notify($user_id, true);
        } catch (\Throwable $e) {}
    }

    private function rewards_mode($user, $template, $db, $config, $rewards_calc, $rh_table, $pc_table = '')
    {
        if (empty($config['chastity_rewards_enabled']))
        {
            trigger_error($user->lang['CHASTITY_REWARDS_DISABLED']);
        }

        $user_id = (int) $user->data['user_id'];
        $rings = $rewards_calc->get_rings($user_id);
        $current_year = (int) date('Y');

        // Compteurs de périodes parfaites (jour / mois / année)
        if ($pc_table !== '')
        {
            $perfect = ['day' => 0, 'month' => 0, 'year' => 0];
            $db->sql_return_on_error(true);
            $res = $db->sql_query('SELECT pscale, pcount FROM ' . $pc_table . ' WHERE user_id = ' . $user_id);
            $db->sql_return_on_error(false);
            if ($res !== false)
            {
                while ($row = $db->sql_fetchrow($res))
                {
                    $perfect[$row['pscale']] = (int) $row['pcount'];
                }
                $db->sql_freeresult($res);
            }
            $template->assign_vars([
                'CHASTITY_PERFECT_DAYS'   => $perfect['day'],
                'CHASTITY_PERFECT_MONTHS' => $perfect['month'],
                'CHASTITY_PERFECT_YEARS'  => $perfect['year'],
                'S_CHASTITY_PERFECT'      => ($perfect['day'] + $perfect['month'] + $perfect['year'] > 0),
            ]);

            // Anneaux réels du membre, par échelle, pour la section périodes parfaites
            $perfect_labels = [
                'day'   => $user->lang('CHASTITY_PERFECT_DAYS_LBL'),
                'month' => $user->lang('CHASTITY_PERFECT_MONTHS_LBL'),
                'year'  => $user->lang('CHASTITY_PERFECT_YEARS_LBL'),
            ];
            foreach (['day', 'month', 'year'] as $scale)
            {
                $template->assign_block_vars('perfect_ring', [
                    'LABEL'      => $perfect_labels[$scale],
                    'COUNT'      => (int) $perfect[$scale],
                    'CAGE_PCT'   => $rings[$scale]['cage']['pct'],
                    'POSTS_PCT'  => $rings[$scale]['posts']['pct'],
                    'LOGINS_PCT' => $rings[$scale]['logins']['pct'],
                ]);
            }
        }

        // Libellés et icônes des anneaux
        $ring_meta = [
            'cage'   => ['icon' => '🔒', 'lang' => 'CHASTITY_RING_CAGE',   'color' => '#ff2d55'],
            'posts'  => ['icon' => '✍️', 'lang' => 'CHASTITY_RING_POSTS',  'color' => '#a8e000'],
            'logins' => ['icon' => '📅', 'lang' => 'CHASTITY_RING_LOGINS', 'color' => '#00b0ff'],
        ];
        $period_meta = [
            'day'   => 'CHASTITY_RING_PERIOD_DAY',
            'month' => 'CHASTITY_RING_PERIOD_MONTH',
            'year'  => 'CHASTITY_RING_PERIOD_YEAR',
        ];

        foreach (['day', 'month', 'year'] as $period)
        {
            $template->assign_block_vars('ring_period', [
                'PERIOD'      => $period,
                'PERIOD_NAME' => $user->lang($period_meta[$period]),
            ]);

            foreach (['cage', 'posts', 'logins'] as $type)
            {
                $r = $rings[$period][$type];
                $meta = $ring_meta[$type];
                $template->assign_block_vars('ring_period.ring', [
                    'TYPE'       => $type,
                    'ICON'       => $meta['icon'],
                    'NAME'       => $user->lang($meta['lang']),
                    'COLOR'      => $meta['color'],
                    'VALUE'      => $r['value'],
                    'GOAL'       => $r['goal'],
                    'PCT'        => $r['pct'],
                    'COMPLETED'  => $r['completed'],
                    'UNIT'       => ($type === 'cage') ? $user->lang('CHASTITY_HOURS_UNIT') : '',
                ]);

                // Enregistrer la complétion annuelle dans l'historique (une fois)
                if ($r['completed'] && $period === 'year')
                {
                    $this->record_reward($db, $rh_table, $user_id, $current_year, $type, $period, $r['goal'], $r['value']);
                }
            }
        }

        // Historique des récompenses annuelles (années antérieures)
        $sql = 'SELECT reward_year, ring_type, goal_value, reached_value, completed_at
                FROM ' . $rh_table . '
                WHERE user_id = ' . $user_id . "
                  AND ring_period = 'year'
                ORDER BY reward_year DESC, ring_type ASC";
        $res = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($res))
        {
            $template->assign_block_vars('reward_history', [
                'YEAR'    => (int) $row['reward_year'],
                'TYPE'    => $row['ring_type'],
                'GOAL'    => (int) $row['goal_value'],
                'REACHED' => (int) $row['reached_value'],
                'DATE'    => $user->format_date((int) $row['completed_at']),
            ]);
        }
        $db->sql_freeresult($res);

        // Section badges spéciaux : synchroniser les acquis puis lire
        // (badges figés des années passées + calcul à la volée pour l'année en cours)
        global $phpbb_container;
        $kh_table_all = '';
        try {
            $kh_table_all = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_keyholders');
            $rewards_calc->sync_earned_badges($user_id, $kh_table_all);
        } catch (\Throwable $e) {}
        $all_badges = $rewards_calc->get_all_badges($user_id, $kh_table_all);

        foreach ($all_badges['locktober'] as $b)
        {
            $template->assign_block_vars('special_badge', [
                'TYPE'        => 'locktober',
                'YEAR'        => $b['year'],
                'LEVEL'       => $b['level'],
                'SUCCESS'     => ($b['level'] === 'success'),
                'REWARD_LABEL'=> $b['reward_label'] ?? '',
                'REWARD_IMAGE'=> $b['reward_image'] ?? '',
            ]);
        }

        // Badge de palier de fidélité (plus haut palier atteint)
        $milestone = $rewards_calc->get_milestone_badge($user_id);
        if ($milestone)
        {
            $template->assign_vars([
                'S_CHASTITY_MILESTONE'       => true,
                'CHASTITY_MILESTONE_COUNT'   => $milestone['count'],
                'CHASTITY_MILESTONE_THRESHOLD'=> $milestone['threshold'],
                'CHASTITY_MILESTONE_LABEL'   => $milestone['label'],
                'CHASTITY_MILESTONE_IMAGE'   => $milestone['image'],
            ]);
        }

        // Badges "journée spéciale" (figés + année courante)
        foreach ($all_badges['sday'] as $sd)
        {
            $template->assign_block_vars('special_day_badge', [
                'DATE'  => $sd['date'],
                'YEAR'  => $sd['year'],
                'LABEL' => $sd['label'],
                'IMAGE' => $sd['image'],
            ]);
        }

        // Badges anniversaire (figés + année courante)
        foreach ($all_badges['birthday'] as $bd)
        {
            $template->assign_block_vars('birthday_badge', [
                'TYPE'  => $bd['type'],
                'DATE'  => $bd['date'],
                'YEAR'  => $bd['year'],
                'LABEL' => $bd['label'],
                'IMAGE' => $bd['image'],
            ]);
        }

        // Badges jours consécutifs et jours totaux (tous paliers atteints)
        foreach ($all_badges['streak'] as $b)
        {
            $template->assign_block_vars('streak_badge', [
                'THRESHOLD' => $b['threshold'],
                'LABEL'     => $b['label'],
                'IMAGE'     => $b['image'],
                'EARNED'    => !empty($b['earned']),
            ]);
        }
        foreach ($all_badges['total'] as $b)
        {
            $template->assign_block_vars('total_badge', [
                'THRESHOLD' => $b['threshold'],
                'LABEL'     => $b['label'],
                'IMAGE'     => $b['image'],
                'EARNED'    => !empty($b['earned']),
            ]);
        }

        $template->assign_vars([
            'CHASTITY_REWARDS_YEAR' => $current_year,
            'S_REWARDS'             => true,
        ]);
    }

    /**
     * Enregistre une récompense annuelle si elle n'existe pas déjà.
     */
    private function record_reward($db, $rh_table, $user_id, $year, $type, $period, $goal, $reached)
    {
        $sql = 'SELECT reward_id FROM ' . $rh_table . '
                WHERE user_id = ' . (int) $user_id . '
                  AND reward_year = ' . (int) $year . "
                  AND ring_type = '" . $db->sql_escape($type) . "'
                  AND ring_period = '" . $db->sql_escape($period) . "'";
        $res = $db->sql_query($sql);
        $exists = (bool) $db->sql_fetchrow($res);
        $db->sql_freeresult($res);

        if (!$exists)
        {
            $db->sql_query('INSERT INTO ' . $rh_table . ' ' . $db->sql_build_array('INSERT', [
                'user_id'      => (int) $user_id,
                'reward_year'  => (int) $year,
                'ring_type'    => $type,
                'ring_period'  => $period,
                'goal_value'   => (int) $goal,
                'reached_value'=> (int) $reached,
                'completed_at' => time(),
            ]));
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

        // Mode test admin : Locktober ouvert n'importe quel mois, mais
        // uniquement pour les administrateurs (acl_a_), pour valider le
        // fonctionnement hors octobre.
        $is_test_mode = !empty($config['chastity_locktober_test_mode']) && $auth->acl_get('a_');
        $can_participate = $is_october || $is_test_mode;

        if ($request->is_set_post('start_locktober'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $sql = 'SELECT period_id, is_locktober, locktober_year FROM ' . $periods_table . '
                    WHERE user_id = ' . (int) $user->data['user_id'] . " AND status = 'active'
                    ORDER BY start_date DESC";
            $result = $db->sql_query($sql);
            $active_period = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($active_period)
            {
                // Le membre est déjà encagé : on rattache sa période active au
                // challenge Locktober plutôt que d'en créer une nouvelle, pour
                // éviter tout double comptage des durées.
                if ((int) $active_period['is_locktober'] === 1
                    && (int) $active_period['locktober_year'] === $current_year)
                {
                    trigger_error($user->lang['CHASTITY_LOCKTOBER_ALREADY_IN']);
                }

                $db->sql_query('UPDATE ' . $periods_table . '
                    SET is_locktober = 1,
                        locktober_year = ' . (int) $current_year . ',
                        locktober_completed = 0,
                        updated_time = ' . time() . '
                    WHERE period_id = ' . (int) $active_period['period_id']);

                trigger_error($user->lang['CHASTITY_LOCKTOBER_JOINED_EXISTING']);
            }

            if ($is_october)
            {
                $start_date = mktime(0, 0, 0, 10, (int) date('d'), $current_year);
            }
            else
            {
                // Mode test admin hors octobre : démarrage au jour courant réel
                $start_date = mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'));
            }

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
            $this->trigger_user_reward_recalc((int) $user->data['user_id']);

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
            // A3 : le tri se faisait par date de début de période
            // (ORDER BY p.start_date ASC), pas par nombre de jours — sans
            // effet pratique puisque presque tout le monde démarre autour du
            // 1er octobre. Le nombre de jours est calculé en PHP (fuseaux
            // horaires/moteurs SQL hétérogènes selon le backend phpBB), donc
            // le tri par jours doit aussi se faire en PHP : on récupère tous
            // les participants actifs, on calcule leurs jours, puis on trie
            // le tableau résultant par jours décroissants avant de limiter
            // à 20 pour l'affichage.
            //
            // Inclut aussi les périodes 'completed' (terminées avant la fin
            // du mois) : un participant qui arrête ou termine en cours de
            // mois doit rester au classement avec son total final de jours,
            // pas disparaître du seul fait que sa période n'est plus active.
            $sql = 'SELECT u.username, u.user_colour, u.user_id, p.start_date, p.end_date, p.status, p.days_count
                    FROM ' . $periods_table . ' p
                    LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.user_id
                    WHERE p.is_locktober = 1
                      AND p.locktober_year = ' . $current_year . "
                      AND p.status IN ('active', 'completed')";
            $result = $db->sql_query($sql);
            $lb_rows = [];
            while ($row = $db->sql_fetchrow($result))
            {
                if ($row['status'] === 'active')
                {
                    $lb_days = (int) floor((time() - (int) $row['start_date']) / 86400) + 1;
                }
                else
                {
                    // Période terminée : days_count est déjà calculé et figé
                    // à la clôture (cohérent avec l'historique
                    // "completed_locktober"), avec repli sur end_date si
                    // jamais absent.
                    $lb_days = (int) $row['days_count'];
                    if ($lb_days <= 0 && (int) $row['end_date'] > 0)
                    {
                        $lb_days = (int) floor(((int) $row['end_date'] - (int) $row['start_date']) / 86400) + 1;
                    }
                }

                $lb_rows[] = [
                    'username'    => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
                    'days'        => $lb_days,
                    'start_date'  => (int) $row['start_date'],
                ];
            }
            $db->sql_freeresult($result);

            // Tri décroissant par jours ; à égalité, celui qui a démarré en
            // premier (start_date le plus ancien) passe devant.
            usort($lb_rows, function ($a, $b) {
                if ($a['days'] !== $b['days'])
                {
                    return $b['days'] <=> $a['days'];
                }
                return $a['start_date'] <=> $b['start_date'];
            });

            $rank = 1;
            foreach (array_slice($lb_rows, 0, 20) as $lb_row)
            {
                $template->assign_block_vars('leaderboard', [
                    'RANK'     => $rank,
                    'USERNAME' => $lb_row['username'],
                    'DAYS'     => $lb_row['days'],
                ]);
                $rank++;
            }
        }

        // Badges Locktober acquis par le membre (image + année + niveau)
        global $phpbb_container;
        try {
            $rewards_calc = $phpbb_container->get('verturin.chastitytracker.rewards_calculator');
            $lk_badges = $rewards_calc->get_locktober_badges((int) $user->data['user_id']);
            foreach ($lk_badges as $b) {
                $template->assign_block_vars('lk_member_badge', [
                    'YEAR'         => $b['year'],
                    'SUCCESS'      => ($b['level'] === 'success'),
                    'REWARD_LABEL' => $b['reward_label'] ?? '',
                    'REWARD_IMAGE' => $b['reward_image'] ?? '',
                ]);
            }
            if (!empty($lk_badges)) {
                $template->assign_var('S_CHASTITY_LK_BADGES', true);
            }
        } catch (\Throwable $e) {}

        $template->assign_vars([
            'LOCKTOBER_YEAR'                => $current_year,
            'IS_OCTOBER'                    => $is_october,
            'CAN_PARTICIPATE'               => $can_participate,
            'IS_TEST_MODE'                  => $is_test_mode,
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
            // Colonne show_calendar_details ajoutée par migration v3.5.10 — ajout safe
            try {
                $check = $db->sql_query_limit('SELECT show_calendar_details FROM '.$prefs_table, 1);
                $db->sql_freeresult($check);
                $pd['show_calendar_details'] = $request->variable('show_calendar_details', 1);
            } catch (\Throwable $e) {}
            // Colonne gender ajoutée par migration v3.7.9 — ajout safe
            try {
                $check = $db->sql_query_limit('SELECT gender FROM '.$prefs_table, 1);
                $db->sql_freeresult($check);
                $g = $request->variable('gender', 'male');
                if (!in_array($g, ['male', 'female', 'other'], true)) { $g = 'male'; }
                $pd['gender'] = $g;
            } catch (\Throwable $e) {}
            // record_pm_optout (v3.9.1) - ajout safe
            try {
                $check = $db->sql_query_limit('SELECT record_pm_optout FROM '.$prefs_table, 1);
                $db->sql_freeresult($check);
                $pd['record_pm_optout'] = $request->variable('record_pm_receive', 1) ? 0 : 1;
            } catch (\Throwable $e) {}
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
            'SHOW_CALENDAR_DETAILS' => ($prefs && isset($prefs['show_calendar_details'])) ? (bool)$prefs['show_calendar_details'] : (bool)$def,
            'BADGE_TAGLINE'   => $prefs && isset($prefs['badge_tagline']) ? $prefs['badge_tagline'] : '',
            'GENDER'          => ($prefs && isset($prefs['gender']) && $prefs['gender'] !== '') ? $prefs['gender'] : 'male',
            'RECORD_PM_RECEIVE' => ($prefs && isset($prefs['record_pm_optout'])) ? !((bool)$prefs['record_pm_optout']) : true,
        ]);
    }

    /**
     * CTR — Contrat de chasteté (étape 1 : structure de base).
     * Affiche le contrat en cours de l'utilisateur (brouillon ou actif), son
     * historique de contrats précédents, et permet de créer un nouveau
     * contrat vide en brouillon s'il n'en a pas déjà un en cours.
     * L'ajout/gestion des articles est traité dans une étape suivante.
     */
    /**
     * Termes d'accord grammatical (civilité, "dit/dite"...) selon le genre,
     * pour le préambule affiché dans le contrat. Même logique et même
     * convention que service/contract_pdf_generator.php::civility_terms()
     * (seul 'female' déclenche l'accord féminin).
     */
    private function civility_terms($gender, $role)
    {
        $is_female = ($gender === 'female');
        if ($role === 'kh')
        {
            return $is_female
                ? ['civility' => 'Madame',  'dit' => 'dite', 'label' => 'LA KEYHOLDER', 'alt_label' => 'LA MAÎTRESSE', 'possessive' => 'détentrice']
                : ['civility' => 'Monsieur','dit' => 'dit',  'label' => 'LE KEYHOLDER', 'alt_label' => 'LE MAÎTRE',    'possessive' => 'détenteur'];
        }
        return $is_female
            ? ['civility' => 'Madame',  'dit' => 'dite', 'label' => 'L\'ENCAGÉE']
            : ['civility' => 'Monsieur','dit' => 'dit',  'label' => 'L\'ENCAGÉ'];
    }

    private function contract_mode($user, $template, $request, $db, $contracts_table, $articles_table, $links_table, $kh_table, $categories_table, $config, $phpbb_container)
    {
        $user_id = (int) $user->data['user_id'];
        $prefs_table = $phpbb_container->getParameter('verturin.chastitytracker.tables.chastity_user_prefs');

        // ── Génération du document du contrat (PDF ou HTML imprimable) ──
        // Sortie directe HTTP, en dehors du flux normal de rendu du template.
        // export_contract  : export OFFICIEL, articles VALIDÉS uniquement.
        // preview_contract : aperçu de travail, TOUS les articles (y compris
        //                    en attente/refusés), toujours en HTML (jamais
        //                    un vrai PDF téléchargeable, pour ne pas laisser
        //                    croire qu'un brouillon est un document définitif).
        $export_id  = $request->variable('export_contract', 0);
        $preview_id = $request->variable('preview_contract', 0);

        if ($export_id > 0 || $preview_id > 0)
        {
            $doc_id = ($export_id > 0) ? $export_id : $preview_id;
            $is_preview_mode = ($preview_id > 0);

            // Sécurité : seule une des 2 parties peut consulter/exporter le contrat.
            $exp_res = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . '
                WHERE contract_id = ' . $doc_id . '
                  AND (encage_user_id = ' . $user_id . ' OR kh_user_id = ' . $user_id . ')');
            $exp_row = $db->sql_fetchrow($exp_res);
            $db->sql_freeresult($exp_res);

            if ($exp_row)
            {
                $generator = $phpbb_container->get('verturin.chastitytracker.contract_pdf_generator');
                $data = $generator->build_contract_data($doc_id, $is_preview_mode);

                if ($data)
                {
                    if ($is_preview_mode)
                    {
                        // Toujours en HTML, jamais en PDF téléchargeable.
                        header('Content-Type: text/html; charset=utf-8');
                        echo $generator->generate_html($data);
                        exit;
                    }

                    $method = $config['chastity_pdf_method'] ?? 'html';
                    $pdf_bytes = ($method === 'tcpdf') ? $generator->generate_pdf($data) : null;

                    if ($pdf_bytes !== null)
                    {
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="contrat-chastete-' . $doc_id . '.pdf"');
                        header('Content-Length: ' . strlen($pdf_bytes));
                        echo $pdf_bytes;
                        exit;
                    }
                    else
                    {
                        // Secours : page HTML imprimable (aucune dépendance).
                        // Utilisée directement si la méthode ACP est "html",
                        // ou automatiquement si TCPDF n'est pas installé
                        // alors que la méthode ACP est "tcpdf".
                        header('Content-Type: text/html; charset=utf-8');
                        echo $generator->generate_html($data);
                        exit;
                    }
                }
            }
        }

        // Création d'un nouveau contrat vide en brouillon
        if ($request->is_set_post('create_contract'))
        {
            if (!check_form_key('ucp_chastity'))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            // Un encagé ne peut avoir qu'UN SEUL contrat "en cours" à la fois
            // (draft/pending/active/suspended). Une Keyholder, en revanche,
            // peut avoir plusieurs contrats en cours simultanément (un par
            // encagé) — cette vérification ne s'applique donc qu'au rôle
            // d'encagé, jamais à celui de Keyholder.
            $chk = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . '
                WHERE encage_user_id = ' . $user_id . "
                  AND status IN ('draft', 'pending_validation', 'active', 'suspended')");
            $chk_row = $db->sql_fetchrow($chk);
            $db->sql_freeresult($chk);

            if ($chk_row)
            {
                trigger_error($user->lang['CHASTITY_CONTRACT_ALREADY_EXISTS'], E_USER_WARNING);
            }

            // Keyholder active éventuelle, pré-remplie automatiquement
            $kh_user_id = 0;
            if ($kh_table !== '')
            {
                $kh_res = $db->sql_query('SELECT kh_user_id FROM ' . $kh_table . '
                    WHERE sub_user_id = ' . $user_id . " AND status = 'active'");
                $kh_row = $db->sql_fetchrow($kh_res);
                $db->sql_freeresult($kh_res);
                if ($kh_row)
                {
                    $kh_user_id = (int) $kh_row['kh_user_id'];
                }
            }

            // Reprendre les articles d'un contrat ARCHIVÉ précédent, si demandé
            $from_archived_id = $request->variable('from_archived_contract', 0);

            $db->sql_query('INSERT INTO ' . $contracts_table . ' ' . $db->sql_build_array('INSERT', [
                'encage_user_id' => $user_id,
                'kh_user_id'     => $kh_user_id,
                'status'         => 'draft',
                'created_time'   => time(),
                'updated_time'   => time(),
            ]));
            $new_contract_id = (int) $db->sql_nextid();

            if ($from_archived_id > 0)
            {
                // Vérifie que le contrat source appartient bien à ce membre et
                // est bien archivé (ended/replaced), pour éviter de copier le
                // contenu d'un contrat qui ne lui appartient pas. Récupère
                // aussi les infos de la Keyholder externe et le mot de
                // sécurité, à reporter sur le nouveau contrat.
                $src_chk = $db->sql_query("SELECT contract_id, kh_external_name, kh_external_email,
                        safeword_hash, safeword_plain
                    FROM " . $contracts_table . "
                    WHERE contract_id = " . $from_archived_id . "
                      AND encage_user_id = " . $user_id . "
                      AND status IN ('ended', 'replaced')");
                $src_chk_row = $db->sql_fetchrow($src_chk);
                $db->sql_freeresult($src_chk);

                if ($src_chk_row)
                {
                    // Reporter la Keyholder externe et le mot de sécurité sur
                    // le nouveau contrat (uniquement si aucune Keyholder
                    // inscrite n'a déjà été pré-remplie automatiquement).
                    $carry_over = [];
                    if ($kh_user_id === 0 && $src_chk_row['kh_external_name'] !== '')
                    {
                        $carry_over['kh_external_name']  = $src_chk_row['kh_external_name'];
                        $carry_over['kh_external_email'] = $src_chk_row['kh_external_email'];
                    }
                    if ($src_chk_row['safeword_hash'] !== '')
                    {
                        $carry_over['safeword_hash']  = $src_chk_row['safeword_hash'];
                        $carry_over['safeword_plain'] = $src_chk_row['safeword_plain'];
                    }
                    if (!empty($carry_over))
                    {
                        $db->sql_query('UPDATE ' . $contracts_table . ' SET ' . $db->sql_build_array('UPDATE', $carry_over) . '
                            WHERE contract_id = ' . $new_contract_id);
                    }

                    // Reprise depuis un contrat archivé : les articles copiés
                    // repassent TOUJOURS par 'pending', même s'ils étaient
                    // approuvés sur l'ancien contrat — un nouveau contrat est
                    // un nouvel accord, qui doit être revalidé (cf. C4 du
                    // tuto v3.14.11). Pour une KH externe, la résolution
                    // automatique de ces articles a lieu au moment de la
                    // soumission finale (submit_for_validation), pas ici.
                    $src_arts = $db->sql_query("SELECT * FROM " . $links_table . "
                        WHERE contract_id = " . $from_archived_id . " AND proposal_status IN ('approved', 'pending')
                        ORDER BY sort_order ASC");
                    $copy_sort = 0;
                    while ($src_art = $db->sql_fetchrow($src_arts))
                    {
                        $copy_sort++;
                        $db->sql_query('INSERT INTO ' . $links_table . ' ' . $db->sql_build_array('INSERT', [
                            'contract_id'     => $new_contract_id,
                            'article_id'      => (int) $src_art['article_id'],
                            'article_title'   => $src_art['article_title'],
                            'article_body'    => $src_art['article_body'],
                            'category'        => $src_art['category'],
                            'sort_order'      => $copy_sort,
                            'proposal_status' => 'pending',
                            'admin_review_status' => (int) $src_art['article_id'] === 0 ? 'pending' : 'none',
                            'proposed_by'     => $user_id,
                            'created_time'    => time(),
                            'updated_time'    => time(),
                        ]));
                    }
                    $db->sql_freeresult($src_arts);

                    // Marquer le contrat archivé source comme REMPLACÉ par ce
                    // nouveau contrat (au lieu de rester "ended" sans lien
                    // traçable vers celui qui lui succède).
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'replaced',
                            replaced_by = " . $new_contract_id . ',
                            updated_time = ' . time() . '
                        WHERE contract_id = ' . $from_archived_id);
                }
            }

            trigger_error($user->lang['CHASTITY_CONTRACT_CREATED']);
        }

        // Tous les contrats "en cours" de ce membre, qu'il y soit encagé OU
        // Keyholder (une Keyholder peut avoir plusieurs contrats en cours,
        // un par encagé — affichés en liste plutôt qu'un seul à la fois).
        $current_res = $db->sql_query('SELECT c.*, ue.username AS encage_username, ue.user_colour AS encage_colour
                FROM ' . $contracts_table . ' c
                LEFT JOIN ' . USERS_TABLE . ' ue ON ue.user_id = c.encage_user_id
                WHERE (c.encage_user_id = ' . $user_id . ' OR c.kh_user_id = ' . $user_id . ")
                  AND c.status IN ('draft', 'pending_validation', 'active', 'suspended')
                ORDER BY c.created_time DESC");
        $has_any_current = false;
        while ($current = $db->sql_fetchrow($current_res))
        {
            $has_any_current = true;
            $is_viewer_kh = ((int) $current['kh_user_id'] === $user_id);

            // Nom affiché : si je suis la KH, je vois le nom de l'ENCAGÉ ;
            // si je suis l'encagé, je vois le nom de MA Keyholder.
            if ($is_viewer_kh)
            {
                $other_party_name = get_username_string('username', (int) $current['encage_user_id'], $current['encage_username'], $current['encage_colour']);
            }
            else
            {
                $other_party_name = '';
                if ((int) $current['kh_user_id'] > 0)
                {
                    $kh_u_res = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                        WHERE user_id = ' . (int) $current['kh_user_id']);
                    $kh_u_row = $db->sql_fetchrow($kh_u_res);
                    $db->sql_freeresult($kh_u_res);
                    if ($kh_u_row)
                    {
                        $other_party_name = get_username_string('username', (int) $current['kh_user_id'], $kh_u_row['username'], $kh_u_row['user_colour']);
                    }
                }
                elseif ($current['kh_external_name'] !== '')
                {
                    $other_party_name = $current['kh_external_name'];
                }
            }

            $nb_articles_res = $db->sql_query('SELECT COUNT(*) AS cnt FROM ' . $links_table . '
                WHERE contract_id = ' . (int) $current['contract_id']);
            $nb_articles = (int) $db->sql_fetchfield('cnt');
            $db->sql_freeresult($nb_articles_res);

            $template->assign_block_vars('current_contracts', [
                'CONTRACT_ID'       => (int) $current['contract_id'],
                'CONTRACT_STATUS'   => $current['status'],
                'CONTRACT_STATUS_LABEL' => $user->lang['CHASTITY_CONTRACT_STATUS_' . strtoupper($current['status'])],
                'OTHER_PARTY_NAME'  => $other_party_name,
                'IS_VIEWER_KH'      => $is_viewer_kh,
                'CONTRACT_NB_ARTICLES' => $nb_articles,
                'CONTRACT_CREATED'  => $user->format_date((int) $current['created_time']),
            ]);
        }
        $db->sql_freeresult($current_res);
        $template->assign_var('S_HAS_CONTRACT', $has_any_current);

        // ── Distinction rôle-spécifique pour la création/duplication ──
        // Un membre peut être Keyholder d'un contrat en cours ET vouloir
        // créer/dupliquer SON PROPRE contrat en tant qu'encagé (ou
        // inversement) : ces deux rôles sont indépendants. Le bouton
        // "Créer un contrat" et le lien "Repartir de ce contrat" ne doivent
        // donc être masqués que si le membre a DÉJÀ un contrat en cours en
        // tant qu'ENCAGÉ, jamais à cause d'un contrat où il n'est que KH.
        $has_current_as_encage_res = $db->sql_query('SELECT 1 FROM ' . $contracts_table . '
            WHERE encage_user_id = ' . $user_id . "
              AND status IN ('draft', 'pending_validation', 'active', 'suspended') LIMIT 1");
        $has_current_as_encage = (bool) $db->sql_fetchrow($has_current_as_encage_res);
        $db->sql_freeresult($has_current_as_encage_res);
        $template->assign_var('S_CAN_CREATE_CONTRACT', !$has_current_as_encage);

        // Utilisé côté template pour séparer visuellement "mon contrat en
        // tant qu'encagé" de "mes contrats en tant que Keyholder" dans le
        // bloc "Votre contrat en cours" (un membre peut cumuler les 2 rôles
        // avec des personnes différentes).
        $has_current_as_kh_res = $db->sql_query('SELECT 1 FROM ' . $contracts_table . '
            WHERE kh_user_id = ' . $user_id . "
              AND status IN ('draft', 'pending_validation', 'active', 'suspended') LIMIT 1");
        $has_current_as_kh = (bool) $db->sql_fetchrow($has_current_as_kh_res);
        $db->sql_freeresult($has_current_as_kh_res);
        $template->assign_vars([
            'S_HAS_CURRENT_AS_ENCAGE' => $has_current_as_encage,
            'S_HAS_CURRENT_AS_KH'     => $has_current_as_kh,
        ]);

        // Historique (archives) des contrats précédents, terminés ou
        // remplacés — visible pour LES DEUX rôles, TOUJOURS affiché (même
        // si un contrat est en cours), avec possibilité de repartir de
        // l'un d'eux pour un nouveau contrat.
        $hist_res = $db->sql_query('SELECT c.*, ue.username AS encage_username, ue.user_colour AS encage_colour
            FROM ' . $contracts_table . ' c
            LEFT JOIN ' . USERS_TABLE . ' ue ON ue.user_id = c.encage_user_id
            WHERE (c.encage_user_id = ' . $user_id . ' OR c.kh_user_id = ' . $user_id . ")
              AND c.status IN ('ended', 'replaced')
            ORDER BY c.created_time DESC");
        while ($hist_row = $db->sql_fetchrow($hist_res))
        {
            $is_hist_viewer_kh = ((int) $hist_row['kh_user_id'] === $user_id);

            if ($is_hist_viewer_kh)
            {
                $kh_name_h = get_username_string('username', (int) $hist_row['encage_user_id'], $hist_row['encage_username'], $hist_row['encage_colour']);
            }
            else
            {
                $kh_name_h = '';
                if ((int) $hist_row['kh_user_id'] > 0)
                {
                    $kh_u_res_h = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                        WHERE user_id = ' . (int) $hist_row['kh_user_id']);
                    $kh_u_row_h = $db->sql_fetchrow($kh_u_res_h);
                    $db->sql_freeresult($kh_u_res_h);
                    if ($kh_u_row_h)
                    {
                        $kh_name_h = get_username_string('username', (int) $hist_row['kh_user_id'], $kh_u_row_h['username'], $kh_u_row_h['user_colour']);
                    }
                }
                elseif ($hist_row['kh_external_name'] !== '')
                {
                    $kh_name_h = $hist_row['kh_external_name'];
                }
            }

            // Le contrat REMPLAÇANT peut avoir été supprimé depuis (un
            // brouillon jamais soumis peut être supprimé complètement) : ne
            // proposer le lien "Voir le contrat qui l'a remplacé" que s'il
            // existe encore réellement, pour ne jamais pointer vers un
            // contrat fantôme.
            $replaced_by_valid = 0;
            if ((int) $hist_row['replaced_by'] > 0)
            {
                $rb_chk = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . '
                    WHERE contract_id = ' . (int) $hist_row['replaced_by']);
                $rb_chk_row = $db->sql_fetchrow($rb_chk);
                $db->sql_freeresult($rb_chk);
                if ($rb_chk_row)
                {
                    $replaced_by_valid = (int) $hist_row['replaced_by'];
                }
            }

            $template->assign_block_vars('contract_history', [
                'CONTRACT_ID'      => (int) $hist_row['contract_id'],
                'STATUS_LABEL'     => $user->lang['CHASTITY_CONTRACT_STATUS_' . strtoupper($hist_row['status'])],
                'KH_NAME'          => $kh_name_h,
                'IS_VIEWER_KH'     => $is_hist_viewer_kh,
                // Repartir d'un contrat archivé n'est proposé qu'à l'ENCAGÉ,
                // seulement s'il n'a pas déjà un contrat en cours, et
                // seulement si ce contrat n'a pas DÉJÀ été remplacé une fois
                // (un contrat "replaced" a déjà son successeur, inutile d'en
                // repartir une seconde fois).
                'CAN_RESTART_FROM' => (!$is_hist_viewer_kh && !$has_current_as_encage && $hist_row['status'] === 'ended'),
                'IS_REPLACED'      => ($hist_row['status'] === 'replaced'),
                'REPLACED_BY_ID'   => $replaced_by_valid,
                'CREATED'          => $user->format_date((int) $hist_row['created_time']),
                'ENDED'            => ((int) $hist_row['ended_time'] > 0) ? $user->format_date((int) $hist_row['ended_time']) : '-',
            ]);
        }
        $db->sql_freeresult($hist_res);

        // ── Vue détail d'un contrat (liste des articles + proposition) ──
        $view_id = $request->variable('view_contract', 0);
        if ($view_id > 0)
        {
            // Sécurité : le contrat doit appartenir au membre connecté, soit
            // en tant qu'encagé, soit en tant que Keyholder inscrite.
            $cv_res = $db->sql_query('SELECT * FROM ' . $contracts_table . '
                WHERE contract_id = ' . $view_id . '
                  AND (encage_user_id = ' . $user_id . ' OR kh_user_id = ' . $user_id . ')');
            $cv = $db->sql_fetchrow($cv_res);
            $db->sql_freeresult($cv_res);

            if ($cv)
            {
                $is_frozen = in_array($cv['status'], ['pending_validation', 'active', 'ended', 'replaced'], true);
                $is_kh_viewer = ((int) $cv['kh_user_id'] === $user_id);

                // ── Keyholder : définir / modifier les infos externes ──
                // Modifiable tant qu'il n'y a pas de KH INSCRITE (une KH
                // inscrite passe par la relation Keyholder classique, pas
                // par ce formulaire texte libre) et que le contrat n'est
                // pas encore actif.
                if ($request->is_set_post('set_kh_external') && (int) $cv['kh_user_id'] === 0 && $cv['status'] !== 'active')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $kh_name   = $request->variable('kh_external_name', '', true);
                    $kh_email  = $request->variable('kh_external_email', '');
                    $kh_gender = $request->variable('kh_external_gender', 'male');
                    if (!in_array($kh_gender, ['male', 'female'], true))
                    {
                        $kh_gender = 'male';
                    }

                    if ($kh_name === '')
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_KH_NAME_REQUIRED'], E_USER_WARNING);
                    }
                    if ($kh_email !== '' && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $kh_email))
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_KH_EMAIL_INVALID'], E_USER_WARNING);
                    }

                    $db->sql_query('UPDATE ' . $contracts_table . " SET
                        kh_external_name = '" . $db->sql_escape($kh_name) . "',
                        kh_external_email = '" . $db->sql_escape($kh_email) . "',
                        kh_external_gender = '" . $db->sql_escape($kh_gender) . "',
                        updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id);

                    // NB : les articles en attente ne sont PLUS auto-validés
                    // ici — ils restent visiblement "en attente" pendant tout
                    // le brouillon, même avec une KH externe. La résolution
                    // automatique (nécessaire puisqu'une KH externe n'a pas
                    // d'interface pour valider un par un) n'intervient qu'au
                    // moment de la soumission finale (submit_for_validation).

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Soumettre le contrat pour validation finale ──
                // Le contrat passe en attente : il est figé (plus d'ajout
                // d'article possible), et un code/lien de validation est
                // envoyé à la Keyholder (inscrite : MP + email avec un code
                // à communiquer à l'encagé ; externe : email avec un lien
                // unique qu'elle clique elle-même).
                // ── Supprimer complètement un contrat en BROUILLON (jamais
                // soumis) : aucun engagement avec l'autre partie n'a encore
                // eu lieu, donc suppression pure sans trace dans
                // l'historique, contrairement à un contrat "arrêté" qui lui
                // reste archivé. Réservé à l'encagé, et uniquement tant que
                // le contrat est encore un simple brouillon.
                if ($request->is_set_post('delete_draft_contract') && $cv['status'] === 'draft' && $user_id === (int) $cv['encage_user_id'])
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }

                    // Si ce brouillon avait été créé via "Repartir de ce
                    // contrat", son contrat source est passé à "replaced".
                    // Le remettre en "ended" avant de le supprimer, pour ne
                    // pas laisser un contrat affiché "Remplacé" pointant vers
                    // un contrat qui n'existera plus.
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'ended', replaced_by = 0, updated_time = " . time() . '
                        WHERE replaced_by = ' . $view_id . " AND status = 'replaced'");

                    $db->sql_query('DELETE FROM ' . $links_table . '
                        WHERE contract_id = ' . $view_id);
                    $db->sql_query('DELETE FROM ' . $contracts_table . "
                        WHERE contract_id = " . $view_id . "
                          AND encage_user_id = " . $user_id . "
                          AND status = 'draft'");
                    trigger_error($user->lang['CHASTITY_CONTRACT_DRAFT_DELETED']);
                }

                if ($request->is_set_post('submit_for_validation') && $cv['status'] === 'draft')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }

                    $has_kh_inscrite = ((int) $cv['kh_user_id'] > 0);
                    $has_kh_externe  = (!$has_kh_inscrite && $cv['kh_external_name'] !== '' && $cv['kh_external_email'] !== '');

                    if (!$has_kh_inscrite && !$has_kh_externe)
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_NEEDS_KH_TO_SUBMIT'], E_USER_WARNING);
                    }

                    // Le mot de sécurité est OBLIGATOIRE pour la validation
                    // définitive : sans lui, aucune des deux parties ne peut
                    // suspendre ou arrêter le contrat par elles-mêmes (seule
                    // la fin de la relation Keyholder ou une intervention
                    // admin le permettrait), ce qui n'est pas acceptable pour
                    // la sécurité des deux parties.
                    if ($cv['safeword_hash'] === '')
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_SAFEWORD_REQUIRED_TO_SUBMIT'], E_USER_WARNING);
                    }

                    // Une KH externe (ou l'absence de KH inscrite) n'a pas
                    // d'interface pour valider les articles un par un : la
                    // résolution automatique des articles encore en attente,
                    // proposés par l'encagé, se fait donc ICI, au moment de
                    // la soumission finale — pas dès leur ajout — afin que le
                    // statut affiché pendant tout le brouillon reste honnête
                    // ("en attente") jusqu'à cet instant précis. L'accord
                    // global de la KH externe est ensuite acté via le code
                    // reçu par email.
                    if (!$has_kh_inscrite)
                    {
                        $db->sql_query('UPDATE ' . $links_table . "
                            SET proposal_status = 'approved', updated_time = " . time() . '
                            WHERE contract_id = ' . $view_id . '
                              AND proposed_by = ' . $user_id . "
                              AND proposal_status = 'pending'");
                    }

                    // Tous les articles doivent être VALIDÉS (aucun pending
                    // ni rejected restant) avant de pouvoir soumettre le
                    // contrat pour validation finale.
                    $pending_check_res = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $links_table . "
                        WHERE contract_id = " . $view_id . " AND proposal_status IN ('pending', 'rejected')");
                    $pending_check_count = (int) $db->sql_fetchfield('cnt');
                    $db->sql_freeresult($pending_check_res);
                    if ($pending_check_count > 0)
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_ARTICLES_NOT_ALL_APPROVED'], E_USER_WARNING);
                    }

                    $validation_code  = (string) random_int(100000, 999999);
                    $validation_token = substr(bin2hex(random_bytes(32)), 0, 48);

                    $db->sql_query('UPDATE ' . $contracts_table . " SET
                        status = 'pending_validation',
                        validation_code = '" . $db->sql_escape($validation_code) . "',
                        validation_token = '" . $db->sql_escape($validation_token) . "',
                        sent_time = " . time() . ',
                        updated_time = ' . time() . '
                        WHERE contract_id = ' . $view_id);

                    $encage_username = get_username_string('username', $user_id, $user->data['username']);

                    if ($has_kh_inscrite)
                    {
                        $this->send_contract_validation_notice(
                            $db, $config, $user, (int) $cv['kh_user_id'], null,
                            $encage_username, $validation_code, $view_id, false
                        );
                    }
                    else
                    {
                        $this->send_contract_validation_notice(
                            $db, $config, $user, 0, ['name' => $cv['kh_external_name'], 'email' => $cv['kh_external_email']],
                            $encage_username, $validation_token, $view_id, true
                        );
                    }

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Renvoyer l'email/MP de validation (contrat déjà en
                // attente) : génère un NOUVEAU code/token, ce qui invalide
                // automatiquement l'ancien lien envoyé précédemment — un
                // vieux lien oublié dans une boîte mail ne doit plus
                // fonctionner après un renvoi.
                if ($request->is_set_post('resend_validation') && $cv['status'] === 'pending_validation')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }

                    $has_kh_inscrite = ((int) $cv['kh_user_id'] > 0);

                    $validation_code  = (string) random_int(100000, 999999);
                    $validation_token = substr(bin2hex(random_bytes(32)), 0, 48);

                    $db->sql_query('UPDATE ' . $contracts_table . " SET
                        validation_code = '" . $db->sql_escape($validation_code) . "',
                        validation_token = '" . $db->sql_escape($validation_token) . "',
                        sent_time = " . time() . ',
                        updated_time = ' . time() . '
                        WHERE contract_id = ' . $view_id);

                    $encage_username = get_username_string('username', $user_id, $user->data['username']);

                    if ($has_kh_inscrite)
                    {
                        $this->send_contract_validation_notice(
                            $db, $config, $user, (int) $cv['kh_user_id'], null,
                            $encage_username, $validation_code, $view_id, false
                        );
                    }
                    else
                    {
                        $this->send_contract_validation_notice(
                            $db, $config, $user, 0, ['name' => $cv['kh_external_name'], 'email' => $cv['kh_external_email']],
                            $encage_username, $validation_token, $view_id, true
                        );
                    }

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── L'encagé saisit le code reçu de sa Keyholder inscrite ──
                if ($request->is_set_post('validate_with_code') && $cv['status'] === 'pending_validation')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $code_try = $request->variable('validation_code_input', '');
                    if ($code_try !== '' && $code_try === $cv['validation_code'])
                    {
                        $db->sql_query('UPDATE ' . $contracts_table . " SET
                            status = 'active',
                            validated_time = " . time() . ',
                            updated_time = ' . time() . '
                            WHERE contract_id = ' . $view_id);
                        redirect($this->u_action . '&view_contract=' . $view_id);
                    }
                    trigger_error($user->lang['CHASTITY_CONTRACT_CODE_WRONG'], E_USER_WARNING);
                }

                // ── Refuser le contrat en attente : retour au brouillon ──
                if ($request->is_set_post('reject_pending_contract') && $cv['status'] === 'pending_validation')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $rejection_reason = $request->variable('rejection_reason', '', true);

                    $db->sql_query('UPDATE ' . $contracts_table . " SET
                        status = 'draft',
                        validation_code = '',
                        validation_token = '',
                        last_rejection_reason = '" . $db->sql_escape($rejection_reason) . "',
                        updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id);

                    // Notifier l'encagé par MP si c'est la KH qui refuse
                    // (l'encagé n'a pas besoin d'être notifié de son propre refus).
                    if ($user_id !== (int) $cv['encage_user_id'] && $rejection_reason !== '')
                    {
                        $rejecter_username = get_username_string('username', $user_id, $user->data['username']);
                        $subject = $user->lang['CHASTITY_CONTRACT_REJECTED_SUBJECT'];
                        $message = sprintf($user->lang['CHASTITY_CONTRACT_REJECTED_PM_BODY'], $rejecter_username, $rejection_reason);
                        $this->send_simple_pm($db, $config, $user_id, $rejecter_username, (int) $cv['encage_user_id'], $subject, $message);
                    }

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Mot de sécurité : définir / modifier ──
                // Chaque partie peut définir le mot de sécurité tant que le
                // contrat n'est pas encore actif (sécurité : une fois actif,
                // le mot ne doit plus pouvoir être changé unilatéralement).
                if ($request->is_set_post('set_safeword') && $cv['status'] !== 'active')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $safeword = $request->variable('safeword', '', true);
                    if ($safeword === '')
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_SAFEWORD_REQUIRED'], E_USER_WARNING);
                    }
                    $db->sql_query('UPDATE ' . $contracts_table . " SET
                        safeword_hash = '" . $db->sql_escape(password_hash($safeword, PASSWORD_DEFAULT)) . "',
                        safeword_plain = '" . $db->sql_escape($safeword) . "',
                        updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id);
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Mot de sécurité : invoquer (suspension immédiate) ──
                if ($request->is_set_post('trigger_safeword') && $cv['status'] === 'active')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $safeword_try = $request->variable('safeword_confirm', '', true);
                    if ($cv['safeword_hash'] === '' || !password_verify($safeword_try, $cv['safeword_hash']))
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_SAFEWORD_WRONG'], E_USER_WARNING);
                    }
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'suspended',
                            safeword_suspended_by = $user_id,
                            safeword_suspended_time = " . time() . ',
                            updated_time = ' . time() . '
                        WHERE contract_id = ' . $view_id);

                    // La suspension est déjà EFFECTIVE immédiatement (ligne
                    // ci-dessus) — cette notification est purement informative,
                    // elle ne conditionne jamais la suspension elle-même.
                    $invoker_username = get_username_string('username', $user_id, $user->data['username']);
                    $other_party_id = ($user_id === (int) $cv['encage_user_id']) ? (int) $cv['kh_user_id'] : (int) $cv['encage_user_id'];
                    if ($other_party_id > 0)
                    {
                        $subject = $user->lang['CHASTITY_CONTRACT_SAFEWORD_TRIGGERED_SUBJECT'];
                        $message = sprintf($user->lang['CHASTITY_CONTRACT_SAFEWORD_TRIGGERED_PM_BODY'], $invoker_username);
                        $this->send_simple_pm($db, $config, $user_id, $invoker_username, $other_party_id, $subject, $message);
                    }
                    elseif ($cv['kh_external_email'] !== '')
                    {
                        if (!function_exists('generate_board_url')) {
                            include_once($phpbb_root_path . 'includes/functions.' . $phpEx);
                        }
                        if (!class_exists('messenger')) {
                            include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
                        }
                        try {
                            $ext_lang_sw = in_array($config['default_lang'] ?? '', ['fr', 'en'], true) ? $config['default_lang'] : 'fr';
                            $sw_template_path = $phpbb_root_path . 'ext/verturin/chastitytracker/language/' . $ext_lang_sw . '/email';
                            $messenger = new \messenger(false);
                            $messenger->template('chastity_safeword_triggered', $ext_lang_sw, $sw_template_path);
                            $messenger->to($cv['kh_external_email'], $cv['kh_external_name']);
                            $messenger->assign_vars([
                                'KH_NAME'    => $cv['kh_external_name'],
                                'USERNAME'   => $invoker_username,
                                'SUBJECT'    => $user->lang['CHASTITY_CONTRACT_SAFEWORD_TRIGGERED_SUBJECT'],
                            ]);
                            $messenger->send(NOTIFY_EMAIL);
                        } catch (\Throwable $e) {
                            if (function_exists('add_log')) {
                                add_log('admin', 'LOG_CHASTITY_CONTRACT_EMAIL_FAILED', $e->getMessage());
                            }
                        }
                    }

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Mot de sécurité : lever la suspension ──
                // Seule la personne qui N'A PAS invoqué le mot peut lever la
                // suspension (frein de secours asymétrique).
                if ($request->is_set_post('lift_safeword') && (int) $cv['safeword_suspended_by'] > 0)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    if ((int) $cv['safeword_suspended_by'] === $user_id)
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_SAFEWORD_CANNOT_LIFT_OWN'], E_USER_WARNING);
                    }
                    // La reprise après suspension repasse par le circuit
                    // complet de validation (comme une nouvelle soumission) :
                    // le contrat retourne en brouillon, l'encagé devra le
                    // re-soumettre pour obtenir un nouveau code/lien.
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'draft',
                            safeword_suspended_by = 0,
                            safeword_suspended_time = 0,
                            validation_code = '',
                            validation_token = '',
                            updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id);
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Arrêter DÉFINITIVEMENT un contrat suspendu par mot de
                // sécurité (au lieu de le reprendre). Contrairement à la
                // reprise, l'arrêt est accessible aux DEUX parties, y compris
                // celle qui a invoqué le mot — mettre fin à la relation ne
                // doit pas être bloqué par l'asymétrie du frein de secours.
                // Arrêter définitivement un contrat suspendu — que ce soit
                // par mot de sécurité OU par fin de relation Keyholder.
                if ($request->is_set_post('end_suspended_contract') && $cv['status'] === 'suspended')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'ended',
                            ended_time = " . time() . ',
                            updated_time = ' . time() . '
                        WHERE contract_id = ' . $view_id);
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // Reprendre un contrat suspendu suite à la fin d'une relation
                // Keyholder (pas de mot de sécurité impliqué ici, donc pas
                // d'asymétrie à respecter) : repasse en brouillon pour que
                // l'encagé puisse désigner une nouvelle Keyholder et
                // re-soumettre le contrat via le circuit complet.
                if ($request->is_set_post('restart_after_kh_end') && (int) $cv['suspended_kh_relation_end'] === 1 && $cv['status'] === 'suspended')
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $db->sql_query('UPDATE ' . $contracts_table . "
                        SET status = 'draft',
                            suspended_kh_relation_end = 0,
                            validation_code = '',
                            validation_token = '',
                            updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id);
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // Rafraîchir $cv après une éventuelle modification ci-dessus
                // (les blocs suivants doivent lire l'état à jour).
                $cv_res2 = $db->sql_query('SELECT * FROM ' . $contracts_table . ' WHERE contract_id = ' . $view_id);
                $cv = $db->sql_fetchrow($cv_res2);
                $db->sql_freeresult($cv_res2);
                $is_frozen = in_array($cv['status'], ['pending_validation', 'active', 'ended', 'replaced'], true);
                // Les articles PROPOSÉS peuvent être validés/refusés par
                // l'AUTRE partie à tous les stades du contrat SAUF quand il
                // est terminé/remplacé, ou suspendu par mot de sécurité :
                // - draft            : c'est LE moment de valider mutuellement
                //                      (bug corrigé — auparavant 'draft' était
                //                      exclu par erreur, bloquant la KH
                //                      inscrite qui recevait le contrat).
                // - pending_validation, active : un contrat déjà soumis (ou
                //                      même actif) peut encore avoir des
                //                      articles en attente de validation
                //                      mutuelle, à traiter un par un.
                $can_validate_articles = !in_array($cv['status'], ['ended', 'replaced'], true) && (int) $cv['safeword_suspended_by'] === 0;

                // Proposer un nouvel article (uniquement si le contrat n'est
                // pas figé — un contrat actif/terminé ne se modifie plus).
                // ── Bouton "Mettre à jour" : sélection multiple bibliothèque ──
                if ($request->is_set_post('update_library_selection') && !$is_frozen)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }

                    $lib_ids = $request->variable('library_article_ids', [0]);
                    $lib_ids = array_filter(array_map('intval', $lib_ids));

                    $max_res = $db->sql_query('SELECT MAX(sort_order) AS m FROM ' . $links_table . '
                        WHERE contract_id = ' . $view_id);
                    $next_sort = ((int) $db->sql_fetchfield('m'));
                    $db->sql_freeresult($max_res);

                    if (!empty($lib_ids))
                    {
                        // Ne pas re-proposer un article déjà présent dans ce contrat.
                        $existing_res = $db->sql_query('SELECT article_id FROM ' . $links_table . '
                            WHERE contract_id = ' . $view_id . ' AND article_id > 0');
                        $existing_ids = [];
                        while ($ex_row = $db->sql_fetchrow($existing_res)) { $existing_ids[] = (int) $ex_row['article_id']; }
                        $db->sql_freeresult($existing_res);
                        $lib_ids = array_diff($lib_ids, $existing_ids);
                    }

                    // Un article nouvellement ajouté doit TOUJOURS repasser
                    // par la validation mutuelle, même sur un contrat avec
                    // KH externe : il apparaît donc en 'pending' ici. Pour
                    // une KH externe (qui n'a pas d'interface pour valider
                    // un par un), la résolution se fait automatiquement au
                    // moment de la soumission finale (cf. submit_for_validation
                    // plus bas), pas dès l'ajout — afin que le statut affiché
                    // reflète honnêtement "en attente" pendant la négociation.
                    if (!empty($lib_ids))
                    {
                        $lib_res = $db->sql_query('SELECT article_id, title, body, category FROM ' . $articles_table . '
                            WHERE (is_global = 1 OR user_id = ' . $user_id . ') AND ' . $db->sql_in_set('article_id', $lib_ids));
                        while ($lib_row = $db->sql_fetchrow($lib_res))
                        {
                            $next_sort++;
                            $db->sql_query('INSERT INTO ' . $links_table . ' ' . $db->sql_build_array('INSERT', [
                                'contract_id'     => $view_id,
                                'article_id'      => (int) $lib_row['article_id'],
                                'article_title'   => $lib_row['title'],
                                'article_body'    => $lib_row['body'],
                                'category'        => $lib_row['category'],
                                'sort_order'      => $next_sort,
                                'proposal_status' => 'pending',
                                'proposed_by'     => $user_id,
                                'created_time'    => time(),
                                'updated_time'    => time(),
                            ]));
                        }
                        $db->sql_freeresult($lib_res);
                    }

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Bouton "Proposer" : article personnalisé en texte libre ──
                if ($request->is_set_post('propose_article') && !$is_frozen)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }

                    $art_title = $request->variable('article_title', '', true);
                    $art_body  = $request->variable('article_body', '', true);

                    if ($art_title === '')
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_ARTICLE_TITLE_REQUIRED'], E_USER_WARNING);
                    }

                    $max_res = $db->sql_query('SELECT MAX(sort_order) AS m FROM ' . $links_table . '
                        WHERE contract_id = ' . $view_id);
                    $next_sort = ((int) $db->sql_fetchfield('m')) + 1;
                    $db->sql_freeresult($max_res);

                    // Un article nouvellement proposé doit toujours repasser
                    // par la validation mutuelle (cf. commentaire ci-dessus).
                    $custom_cat = $request->variable('article_category', 'personnalise');
                    $db->sql_query('INSERT INTO ' . $links_table . ' ' . $db->sql_build_array('INSERT', [
                        'contract_id'     => $view_id,
                        'article_id'      => 0,
                        'article_title'   => $art_title,
                        'article_body'    => $art_body,
                        'category'        => $custom_cat,
                        'sort_order'      => $next_sort,
                        'proposal_status' => 'pending',
                        'admin_review_status' => 'pending',
                        'proposed_by'     => $user_id,
                        'created_time'    => time(),
                        'updated_time'    => time(),
                    ]));

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // Valider/refuser une proposition faite par l'AUTRE partie
                // (on ne peut pas valider sa propre proposition).
                if (($request->is_set_post('approve_article') || $request->is_set_post('reject_article')) && $can_validate_articles)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $link_id = $request->variable('link_id', 0);
                    $new_status = $request->is_set_post('approve_article') ? 'approved' : 'rejected';

                    $db->sql_query('UPDATE ' . $links_table . "
                        SET proposal_status = '" . $db->sql_escape($new_status) . "', updated_time = " . time() . '
                        WHERE link_id = ' . $link_id . '
                          AND contract_id = ' . $view_id . '
                          AND proposed_by != ' . $user_id . "
                          AND proposal_status = 'pending'");

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Tout valider / Tout refuser en un clic ──
                // Applique le nouveau statut à TOUS les articles PENDING
                // proposés par l'AUTRE partie (jamais à ses propres
                // propositions). Utile pour une KH qui reçoit un contrat
                // volumineux et souhaite tout entériner d'un coup, ou pour
                // rejeter globalement un contrat qui ne convient pas.
                if (($request->is_set_post('approve_all_articles') || $request->is_set_post('reject_all_articles')) && $can_validate_articles)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $new_status_bulk = $request->is_set_post('approve_all_articles') ? 'approved' : 'rejected';

                    $db->sql_query('UPDATE ' . $links_table . "
                        SET proposal_status = '" . $db->sql_escape($new_status_bulk) . "', updated_time = " . time() . '
                        WHERE contract_id = ' . $view_id . '
                          AND proposed_by != ' . $user_id . "
                          AND proposal_status = 'pending'");

                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // Retirer un article déjà accepté ou une proposition en attente
                // (uniquement par celui qui l'a proposée).
                if ($request->is_set_post('remove_article') && !$is_frozen)
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $link_id = $request->variable('link_id', 0);
                    $db->sql_query('DELETE FROM ' . $links_table . '
                        WHERE link_id = ' . $link_id . '
                          AND contract_id = ' . $view_id . '
                          AND proposed_by = ' . $user_id);
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // ── Demander la modification d'un article déjà VALIDÉ ──
                // Réservé à celui qui l'a proposé à l'origine. L'article
                // repasse en "pending" : il doit être revalidé par l'autre
                // partie avec son nouveau contenu, exactement comme un
                // article neuf. Possible même si le contrat est actif,
                // puisque l'objectif est justement d'ajuster un contrat déjà
                // signé sans devoir le remplacer entièrement.
                if ($request->is_set_post('request_article_edit') && !in_array($cv['status'], ['active', 'ended', 'replaced'], true))
                {
                    if (!check_form_key('ucp_chastity'))
                    {
                        trigger_error($user->lang['FORM_INVALID']);
                    }
                    $edit_link_id = $request->variable('link_id', 0);
                    $edit_title   = $request->variable('article_title_edit', '', true);
                    $edit_body    = $request->variable('article_body_edit', '', true);

                    if ($edit_title === '')
                    {
                        trigger_error($user->lang['CHASTITY_CONTRACT_ARTICLE_TITLE_REQUIRED'], E_USER_WARNING);
                    }

                    $db->sql_query('UPDATE ' . $links_table . " SET
                        article_title = '" . $db->sql_escape($edit_title) . "',
                        article_body = '" . $db->sql_escape($edit_body) . "',
                        proposal_status = 'pending',
                        updated_time = " . time() . '
                        WHERE link_id = ' . $edit_link_id . '
                          AND contract_id = ' . $view_id . "
                          AND proposed_by = " . $user_id . "
                          AND proposal_status = 'approved'");
                    redirect($this->u_action . '&view_contract=' . $view_id);
                }

                // Nom de "l'AUTRE partie" du contrat consulté, adapté selon
                // le rôle du viewer : si je suis la Keyholder, je dois voir
                // le nom de l'ENCAGÉ, pas le mien ; si je suis l'encagé, je
                // vois le nom de ma Keyholder (inscrite ou externe).
                $cv_is_viewer_kh = ((int) $cv['kh_user_id'] === $user_id);
                $cv_kh_name = '';
                if ($cv_is_viewer_kh)
                {
                    $cv_encage_res = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                        WHERE user_id = ' . (int) $cv['encage_user_id']);
                    $cv_encage_row = $db->sql_fetchrow($cv_encage_res);
                    $db->sql_freeresult($cv_encage_res);
                    if ($cv_encage_row)
                    {
                        $cv_kh_name = get_username_string('username', (int) $cv['encage_user_id'], $cv_encage_row['username'], $cv_encage_row['user_colour']);
                    }
                }
                elseif ((int) $cv['kh_user_id'] > 0)
                {
                    $cv_kh_res = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                        WHERE user_id = ' . (int) $cv['kh_user_id']);
                    $cv_kh_row = $db->sql_fetchrow($cv_kh_res);
                    $db->sql_freeresult($cv_kh_res);
                    if ($cv_kh_row)
                    {
                        $cv_kh_name = get_username_string('username', (int) $cv['kh_user_id'], $cv_kh_row['username'], $cv_kh_row['user_colour']);
                    }
                }
                elseif ($cv['kh_external_name'] !== '')
                {
                    $cv_kh_name = $cv['kh_external_name'];
                }

                // Table de correspondance clé -> libellé des catégories
                // (chargée ICI, avant tout usage, y compris l'affichage des
                // articles du contrat juste après).
                $cat_labels = [];
                $cat_order  = [];
                $cat_list_res = $db->sql_query('SELECT category_key, label FROM ' . $categories_table . ' ORDER BY sort_order ASC');
                while ($cat_list_row = $db->sql_fetchrow($cat_list_res))
                {
                    $cat_labels[$cat_list_row['category_key']] = $cat_list_row['label'];
                    $cat_order[] = $cat_list_row['category_key'];
                    $template->assign_block_vars('all_categories', [
                        'KEY'   => $cat_list_row['category_key'],
                        'LABEL' => $cat_list_row['label'],
                    ]);
                }
                $db->sql_freeresult($cat_list_res);

                // Liste des articles du contrat, triée par ordre
                $art_res = $db->sql_query('SELECT * FROM ' . $links_table . '
                    WHERE contract_id = ' . $view_id . '
                    ORDER BY category ASC, sort_order ASC, created_time ASC');
                $articles_by_cat = [];
                while ($art_row = $db->sql_fetchrow($art_res))
                {
                    $cat_key = ($art_row['category'] !== '') ? $art_row['category'] : 'personnalise';
                    $articles_by_cat[$cat_key][] = $art_row;
                }
                $db->sql_freeresult($art_res);

                // Nombre d'articles pas encore VALIDÉS qui bloquent RÉELLEMENT
                // la soumission. Avec une KH INSCRITE, "pending" ET "rejected"
                // bloquent (elle doit se prononcer). SANS KH inscrite (externe
                // ou absente), les articles "pending" proposés par l'encagé
                // seront auto-résolus au moment même de la soumission (cf.
                // submit_for_validation) — ils ne bloquent donc pas l'affichage
                // du bouton ; seul un "rejected" résiduel (ex. après un
                // changement de KH en cours de route) bloque encore.
                $has_kh_inscrite_view = ((int) $cv['kh_user_id'] > 0);
                if ($has_kh_inscrite_view)
                {
                    $pending_check2 = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $links_table . "
                        WHERE contract_id = " . $view_id . " AND proposal_status IN ('pending', 'rejected')");
                }
                else
                {
                    $pending_check2 = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $links_table . "
                        WHERE contract_id = " . $view_id . " AND proposal_status = 'rejected'");
                }
                $pending_or_rejected_count = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($pending_check2);

                // Nombre d'articles encore "pending" (indépendamment du
                // blocage ci-dessus) — utilisé pour afficher une note
                // explicative sous le bouton Soumettre quand la KH est
                // externe : ces articles seront auto-résolus à la soumission.
                $still_pending_res = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $links_table . "
                    WHERE contract_id = " . $view_id . " AND proposal_status = 'pending'");
                $still_pending_count = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($still_pending_res);

                // Nombre d'articles PENDING proposés par l'AUTRE partie —
                // conditionne l'affichage des boutons "Tout valider / Tout
                // refuser" (inutile si aucun article à traiter par le
                // membre courant).
                $to_validate_res = $db->sql_query("SELECT COUNT(*) AS cnt FROM " . $links_table . "
                    WHERE contract_id = " . $view_id . "
                      AND proposed_by != " . $user_id . "
                      AND proposal_status = 'pending'");
                $pending_to_validate_by_me = (int) $db->sql_fetchfield('cnt');
                $db->sql_freeresult($to_validate_res);

                // Regrouper les catégories dans l'ordre réel défini en ACP
                // (sort_order), "personnalisé" toujours en dernier quel que
                // soit son sort_order propre.
                $ordered_cat_keys = $cat_order;
                if (($idx = array_search('personnalise', $ordered_cat_keys, true)) !== false)
                {
                    unset($ordered_cat_keys[$idx]);
                }
                $ordered_cat_keys[] = 'personnalise';

                $cat_number = 0;
                foreach ($ordered_cat_keys as $cat_key)
                {
                    if (!isset($articles_by_cat[$cat_key])) { continue; }
                    $cat_rows = $articles_by_cat[$cat_key];
                    $cat_number++;
                    $template->assign_block_vars('contract_categories', [
                        'NUMBER'         => $cat_number,
                        'CATEGORY_LABEL' => $cat_labels[$cat_key] ?? $cat_key,
                    ]);

                    $art_number = 0;
                    foreach ($cat_rows as $art_row)
                    {
                        $art_number++;
                        $proposed_by_me = ((int) $art_row['proposed_by'] === $user_id);
                        $template->assign_block_vars('contract_categories.articles', [
                            'NUMBER'          => $cat_number . '.' . $art_number,
                            'LINK_ID'         => (int) $art_row['link_id'],
                            'TITLE'           => $art_row['article_title'],
                            'BODY'            => $art_row['article_body'],
                            'STATUS'          => $art_row['proposal_status'],
                            'STATUS_LABEL'    => $user->lang['CHASTITY_CONTRACT_ARTICLE_STATUS_' . strtoupper($art_row['proposal_status'])],
                            'IS_PENDING'      => ($art_row['proposal_status'] === 'pending'),
                            'IS_APPROVED'     => ($art_row['proposal_status'] === 'approved'),
                            'IS_REJECTED'     => ($art_row['proposal_status'] === 'rejected'),
                            'PROPOSED_BY_ME'  => $proposed_by_me,
                            'CAN_VALIDATE'    => (!$proposed_by_me && $art_row['proposal_status'] === 'pending' && $can_validate_articles),
                            'CAN_REMOVE'      => ($proposed_by_me && $art_row['proposal_status'] !== 'approved' && !$is_frozen),
                            // La demande de modification n'est plus proposée
                            // une fois le contrat ACTIF (signé) : toute
                            // modification doit désormais passer par l'arrêt
                            // du contrat en cours, suivi d'un nouveau contrat
                            // qui nécessite une revalidation complète de la
                            // KH. Elle reste possible pendant la négociation
                            // (draft / pending_validation).
                            'CAN_REQUEST_EDIT'=> ($proposed_by_me && $art_row['proposal_status'] === 'approved' && !in_array($cv['status'], ['active', 'ended', 'replaced'], true)),
                        ]);
                    }
                }

                // (table de correspondance des catégories déplacée plus haut,
                // avant l'affichage des articles du contrat qui en a besoin)

                // Bibliothèque d'articles modèles disponibles à piocher,
                // groupée par catégorie (seulement si le contrat n'est pas
                // figé) : articles globaux + articles personnels de ce membre,
                // en excluant ceux déjà liés au contrat avec un statut
                // approved/pending (mais un article REFUSÉ redevient
                // disponible pour être proposé à nouveau).
                if (!$is_frozen)
                {
                    $already_linked_res = $db->sql_query("SELECT article_id FROM " . $links_table . "
                        WHERE contract_id = " . $view_id . " AND article_id > 0 AND proposal_status IN ('approved', 'pending')");
                    $already_linked_ids = [0];
                    while ($al_row = $db->sql_fetchrow($already_linked_res)) { $already_linked_ids[] = (int) $al_row['article_id']; }
                    $db->sql_freeresult($already_linked_res);

                    $lib_res = $db->sql_query('SELECT * FROM ' . $articles_table . '
                        WHERE (is_global = 1 OR user_id = ' . $user_id . ')
                          AND ' . $db->sql_in_set('article_id', $already_linked_ids, true) . '
                        ORDER BY category ASC, title ASC');
                    $lib_by_cat = [];
                    while ($lib_row = $db->sql_fetchrow($lib_res))
                    {
                        $lib_by_cat[$lib_row['category']][] = $lib_row;
                    }
                    $db->sql_freeresult($lib_res);

                    // Ordonner selon l'ordre réel des catégories (sort_order)
                    foreach ($cat_order as $cat)
                    {
                        if (!isset($lib_by_cat[$cat])) { continue; }
                        $cat_articles = $lib_by_cat[$cat];
                        $template->assign_block_vars('library_categories', [
                            'CATEGORY_LABEL' => $cat_labels[$cat] ?? $cat,
                        ]);
                        foreach ($cat_articles as $lib_row)
                        {
                            $template->assign_block_vars('library_categories.articles', [
                                'ID'    => (int) $lib_row['article_id'],
                                'TITLE' => $lib_row['title'],
                                'BODY'  => $lib_row['body'],
                            ]);
                        }
                    }
                }

                $suspended_by_me = ((int) $cv['safeword_suspended_by'] === $user_id);
                $is_suspended_by_safeword = ((int) $cv['safeword_suspended_by'] > 0);

                // ── Genre des deux parties, pour l'accord grammatical du
                // préambule (Madame/Monsieur, LA/LE KEYHOLDER, dit/dite...).
                // KH inscrite : valeur déjà définie dans ses préférences
                // d'extension (chastity_user_prefs.gender). KH externe :
                // valeur saisie par l'encagé (kh_external_gender).
                $view_kh_gender = 'male';
                if ((int) $cv['kh_user_id'] > 0)
                {
                    $vkh_g_res = $db->sql_query('SELECT gender FROM ' . $prefs_table . '
                        WHERE user_id = ' . (int) $cv['kh_user_id']);
                    $vkh_g_row = $db->sql_fetchrow($vkh_g_res);
                    $db->sql_freeresult($vkh_g_res);
                    if ($vkh_g_row && $vkh_g_row['gender'] === 'female') { $view_kh_gender = 'female'; }
                }
                elseif (isset($cv['kh_external_gender']) && $cv['kh_external_gender'] === 'female')
                {
                    $view_kh_gender = 'female';
                }

                $view_encage_gender = 'male';
                $veg_res = $db->sql_query('SELECT gender FROM ' . $prefs_table . '
                    WHERE user_id = ' . (int) $cv['encage_user_id']);
                $veg_row = $db->sql_fetchrow($veg_res);
                $db->sql_freeresult($veg_res);
                if ($veg_row && $veg_row['gender'] === 'female') { $view_encage_gender = 'female'; }

                $view_kh_terms = $this->civility_terms($view_kh_gender, 'kh');
                $view_encage_terms = $this->civility_terms($view_encage_gender, 'encage');

                // Noms réels des deux parties (indépendants du rôle du
                // viewer, contrairement à $cv_kh_name qui désigne "l'autre
                // partie") : le préambule liste toujours la Keyholder puis
                // l'encagé, dans cet ordre, quel que soit qui consulte.
                $preamble_encage_res = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                    WHERE user_id = ' . (int) $cv['encage_user_id']);
                $preamble_encage_row = $db->sql_fetchrow($preamble_encage_res);
                $db->sql_freeresult($preamble_encage_res);
                $preamble_encage_name = $preamble_encage_row
                    ? get_username_string('username', (int) $cv['encage_user_id'], $preamble_encage_row['username'], $preamble_encage_row['user_colour'])
                    : '';

                $preamble_kh_name = '';
                if ((int) $cv['kh_user_id'] > 0)
                {
                    $preamble_kh_res = $db->sql_query('SELECT username, user_colour FROM ' . USERS_TABLE . '
                        WHERE user_id = ' . (int) $cv['kh_user_id']);
                    $preamble_kh_row = $db->sql_fetchrow($preamble_kh_res);
                    $db->sql_freeresult($preamble_kh_res);
                    if ($preamble_kh_row)
                    {
                        $preamble_kh_name = get_username_string('username', (int) $cv['kh_user_id'], $preamble_kh_row['username'], $preamble_kh_row['user_colour']);
                    }
                }
                elseif ($cv['kh_external_name'] !== '')
                {
                    $preamble_kh_name = $cv['kh_external_name'];
                }

                $nature_html = '<div style="background:#E8F4FD;border-radius:8px;padding:14px 18px;margin-bottom:20px;">'
                    . '<strong style="color:#2E4057;">ℹ️ ' . $user->lang['CHASTITY_CONTRACT_NATURE_TITLE'] . '</strong>'
                    . '<p style="margin:6px 0 0;">' . $user->lang['CHASTITY_CONTRACT_NATURE_TEXT']
                    . (!empty($cv['safeword_plain']) ? ' ' . $user->lang['CHASTITY_CONTRACT_NATURE_SAFEWORD_SUFFIX'] : '') . '</p></div>';

                $preamble_html = '<div style="margin-bottom:25px;">'
                    . '<h3 style="color:#2E4057;">' . $user->lang['CHASTITY_CONTRACT_PREAMBLE_TITLE'] . '</h3>'
                    . '<p>' . $user->lang['CHASTITY_CONTRACT_PREAMBLE_INTRO'] . '</p><ul>'
                    . '<li>' . $view_kh_terms['civility'] . ', <strong>' . htmlspecialchars($preamble_kh_name) . '</strong>, ' . $view_kh_terms['dit'] . ' « ' . $view_kh_terms['label'] . ' » (ou « ' . $view_kh_terms['alt_label'] . ' »), ' . $view_kh_terms['possessive'] . ' de la clé et de l\'autorité définie ci-après ;</li>'
                    . '<li>' . $view_encage_terms['civility'] . ', <strong>' . htmlspecialchars($preamble_encage_name) . '</strong>, ' . $view_encage_terms['dit'] . ' « ' . $view_encage_terms['label'] . ' », ' . $user->lang['CHASTITY_CONTRACT_PREAMBLE_CEDE'] . ' ' . $view_kh_terms['label'] . '.</li>'
                    . '</ul>'
                    . '<p>' . $user->lang['CHASTITY_CONTRACT_PREAMBLE_MAJEURES'] . '</p>'
                    . '<p>' . $user->lang['CHASTITY_CONTRACT_PREAMBLE_DUREE'] . '</p>'
                    . '</div>';

                $template->assign_vars([
                    'S_VIEW_CONTRACT'      => true,
                    'VIEW_CONTRACT_ID'     => $view_id,
                    'VIEW_CONTRACT_STATUS' => $cv['status'],
                    'VIEW_CONTRACT_STATUS_LABEL' => $user->lang['CHASTITY_CONTRACT_STATUS_' . strtoupper($cv['status'])],
                    'VIEW_CONTRACT_KH_NAME' => $cv_kh_name,
                    'VIEW_CONTRACT_NATURE_HTML'   => $nature_html,
                    'VIEW_CONTRACT_PREAMBLE_HTML' => $preamble_html,
                    'S_VIEW_IS_KH'         => $cv_is_viewer_kh,
                    'VIEW_CONTRACT_FROZEN' => $is_frozen,
                    // Statut définitif = aucun brouillon possible (le contrat
                    // est signé ou clos) : l'aperçu doit s'intituler simplement
                    // "Aperçu" plutôt que "Aperçu (avec brouillon)", qui n'a de
                    // sens que pendant la négociation (draft/pending_validation).
                    'VIEW_CONTRACT_IS_DEFINITIVE' => in_array($cv['status'], ['active', 'ended', 'replaced'], true),
                    'S_NO_KH_YET'          => ((int) $cv['kh_user_id'] === 0 && $cv['kh_external_name'] === ''),
                    'S_HAS_KH_INSCRITE'    => ((int) $cv['kh_user_id'] > 0),
                    'S_CAN_EDIT_KH_EXTERNAL' => ((int) $cv['kh_user_id'] === 0 && $cv['status'] !== 'active'),
                    'KH_EXTERNAL_NAME'     => $cv['kh_external_name'],
                    'KH_EXTERNAL_EMAIL'    => $cv['kh_external_email'],
                    'KH_EXTERNAL_GENDER'   => isset($cv['kh_external_gender']) && $cv['kh_external_gender'] !== '' ? $cv['kh_external_gender'] : 'male',
                    'S_HAS_SAFEWORD'          => ($cv['safeword_hash'] !== ''),
                    'SAFEWORD_PLAIN'          => $cv['safeword_plain'],
                    'S_CAN_SET_SAFEWORD'      => ($cv['status'] !== 'active'),
                    'S_SAFEWORD_SUSPENDED'    => $is_suspended_by_safeword,
                    'S_SUSPENDED_KH_ENDED'    => ($cv['status'] === 'suspended' && (int) $cv['suspended_kh_relation_end'] === 1),
                    'S_SUSPENDED_BY_ME'       => $suspended_by_me,
                    'S_CAN_LIFT_SAFEWORD'     => ($is_suspended_by_safeword && !$suspended_by_me),
                    'S_CAN_TRIGGER_SAFEWORD'  => ($cv['status'] === 'active' && $cv['safeword_hash'] !== '' && !$is_suspended_by_safeword),
                    'S_CAN_SUBMIT'            => ($cv['status'] === 'draft' && $user_id === (int) $cv['encage_user_id'] && $pending_or_rejected_count === 0 && $cv['safeword_hash'] !== ''),
                    'S_HAS_UNAPPROVED_ARTICLES' => ($cv['status'] === 'draft' && $pending_or_rejected_count > 0),
                    // Le bouton Soumettre reste cliquable même sans mot de
                    // sécurité tant qu'il reste des articles non validés :
                    // dans ce cas le message S_HAS_UNAPPROVED_ARTICLES prime.
                    // Une fois les articles tous validés, ce message prend le
                    // relais pour expliquer le blocage restant.
                    'S_NEEDS_SAFEWORD_TO_SUBMIT' => ($cv['status'] === 'draft' && $user_id === (int) $cv['encage_user_id'] && $pending_or_rejected_count === 0 && $cv['safeword_hash'] === ''),
                    // Note explicative affichée sous le bouton Soumettre :
                    // des articles restent "en attente" mais ne bloquent pas
                    // la soumission car la KH est externe (résolution
                    // automatique au clic sur Soumettre).
                    'S_HAS_AUTO_RESOLVE_PENDING' => ($cv['status'] === 'draft' && !$has_kh_inscrite_view && $still_pending_count > 0),
                    'PENDING_AUTO_RESOLVE_COUNT' => $still_pending_count,
                    'S_IS_PENDING_VALIDATION' => ($cv['status'] === 'pending_validation'),
                    'S_CAN_ENTER_CODE'        => ($cv['status'] === 'pending_validation' && $user_id === (int) $cv['encage_user_id']),
                    // Distingue le message d'attente pour l'encagé selon que
                    // la KH est INSCRITE (elle reçoit code par MP + email) ou
                    // EXTERNE (elle reçoit un lien par email uniquement) —
                    // dans les deux cas, l'encagé saisit le code communiqué.
                    'S_WAITING_EXTERNAL_KH'   => ($cv['status'] === 'pending_validation' && $user_id === (int) $cv['encage_user_id'] && (int) $cv['kh_user_id'] === 0),
                    'S_CAN_SUBMIT_ACTIONS'    => ($cv['status'] === 'pending_validation' && $user_id === (int) $cv['encage_user_id']),
                    // Affichage des boutons de validation en masse.
                    'S_HAS_PENDING_TO_VALIDATE' => ($can_validate_articles && $pending_to_validate_by_me > 0),
                    'PENDING_TO_VALIDATE_COUNT' => $pending_to_validate_by_me,
                ]);
            }
        }

        $template->assign_var('U_ACTION', $this->u_action);
    }

    private function api_access_mode($user, $template, $request, $db, $prefs_table, $config)
    {
        global $phpEx;
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
        if ($request->is_set_post('save_tagline')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $tagline = trim($request->variable('badge_tagline', '', true));
            if (mb_strlen($tagline) > 150) { $tagline = mb_substr($tagline, 0, 150); }
            $alias = trim($request->variable('badge_alias', '', true));
            if (mb_strlen($alias) > 50) { $alias = mb_substr($alias, 0, 50); }
            $hide_status = (int) $request->variable('badge_hide_status', 0);
            $r = $db->sql_query('SELECT user_id FROM '.$prefs_table.' WHERE user_id='.$user_id);
            $ex = $db->sql_fetchrow($r); $db->sql_freeresult($r);

            // Filtrer dynamiquement selon les colonnes qui existent réellement
            // (rétro-compatibilité si migrations partiellement jouées)
            $ad = ['badge_tagline' => $tagline, 'updated_time' => time()];
            try {
                $check = $db->sql_query_limit('SELECT * FROM '.$prefs_table, 1);
                $row_check = $db->sql_fetchrow($check);
                $db->sql_freeresult($check);
                if ($row_check === false) {
                    // Table vide : tester via DESCRIBE
                    $cols_exist = [];
                    try {
                        $rd = $db->sql_query('SHOW COLUMNS FROM '.$prefs_table);
                        while ($rc = $db->sql_fetchrow($rd)) { $cols_exist[$rc['Field']] = true; }
                        $db->sql_freeresult($rd);
                    } catch (\Throwable $e2) {}
                    if (isset($cols_exist['badge_alias']))       { $ad['badge_alias']       = $alias; }
                    if (isset($cols_exist['badge_hide_status'])) { $ad['badge_hide_status'] = $hide_status; }
                } else {
                    if (array_key_exists('badge_alias', $row_check))       { $ad['badge_alias']       = $alias; }
                    if (array_key_exists('badge_hide_status', $row_check)) { $ad['badge_hide_status'] = $hide_status; }
                }
            } catch (\Throwable $e) {}

            try {
                if ($ex) { $db->sql_query('UPDATE '.$prefs_table.' SET '.$db->sql_build_array('UPDATE',$ad).' WHERE user_id='.$user_id); }
                else { $db->sql_query('INSERT INTO '.$prefs_table.' '.$db->sql_build_array('INSERT',array_merge(['user_id'=>$user_id],$ad))); }
            } catch (\Throwable $e) {
                trigger_error('Erreur lors de la sauvegarde : ' . $e->getMessage(), E_USER_WARNING);
            }
            trigger_error($user->lang['CHASTITY_PREFS_SAVED']);
        }
        $r = $db->sql_query('SELECT * FROM '.$prefs_table.' WHERE user_id='.$user_id);
        $prefs = $db->sql_fetchrow($r); $db->sql_freeresult($r);
        $template->assign_vars([
            'U_ACTION'         => $this->u_action,
            'API_ENABLED'      => $prefs?(bool)$prefs['api_enabled']:false,
            'API_TOKEN'        => ($prefs&&$prefs['api_enabled'])?$prefs['api_token']:'',
            'S_USER_ID'        => $user_id,
            'BADGE_TAGLINE'    => $prefs && isset($prefs['badge_tagline']) ? $prefs['badge_tagline'] : '',
            'BADGE_ALIAS'      => $prefs && isset($prefs['badge_alias']) ? $prefs['badge_alias'] : '',
            'BADGE_HIDE_STATUS' => $prefs && isset($prefs['badge_hide_status']) ? (bool) $prefs['badge_hide_status'] : false,
            // Le badge cliquable (BBCode url=CHASTITY_DISCOVER_URL) renvoie vers
            // la page de presentation publique /decouvrir/ plutot que vers
            // l accueil du forum. ATTENTION : ne pas nommer cette variable
            // BOARD_URL, car BOARD_URL est une variable NATIVE du coeur phpBB
            // (assignee automatiquement dans page_header()) qui ecraserait
            // silencieusement la notre sur l'affichage final.
            'CHASTITY_DISCOVER_URL' => rtrim(generate_board_url(), '/') . '/decouvrir/',
            'API_URL_JSON'     => ($prefs&&$prefs['api_enabled']) ? rtrim(generate_board_url(), '/') . '/app.' . $phpEx . '/chastity/api?token=' . $prefs['api_token'] : '',
            'API_URL_BADGE'    => ($prefs&&$prefs['api_enabled']) ? rtrim(generate_board_url(), '/') . '/app.' . $phpEx . '/chastity/badge.png?token=' . $prefs['api_token'] : '',
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

    private function yearview_mode($user, $template, $request, $db, $periods_table, $cageexits_table = '', $activities_table = '')
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

        // CageExits et activités de l'année + tooltips détaillés
        $cageexit_days = [];
        $activity_days = [];
        $day_tooltips  = []; // date => array de lignes
        $ce_reason_labels  = [];
        $act_reason_labels = [];

        // Récupérer les libellés des motifs
        $ce_reasons_table  = str_replace('chastity_periods', 'chastity_cageexit_reasons',  $periods_table);
        $act_reasons_table = str_replace('chastity_periods', 'chastity_activity_reasons', $periods_table);
        try {
            $rrr = $db->sql_query('SELECT reason_id, label FROM ' . $ce_reasons_table);
            while ($r = $db->sql_fetchrow($rrr)) { $ce_reason_labels[(int)$r['reason_id']] = $r['label']; }
            $db->sql_freeresult($rrr);
        } catch (\Exception $e) {}
        try {
            $rrr = $db->sql_query('SELECT reason_id, label FROM ' . $act_reasons_table);
            while ($r = $db->sql_fetchrow($rrr)) { $act_reason_labels[(int)$r['reason_id']] = $r['label']; }
            $db->sql_freeresult($rrr);
        } catch (\Exception $e) {}

        if (!empty($cageexits_table))
        {
            $res = $db->sql_query('SELECT cageexit_date, duration_min, reason_id, notes FROM ' . $cageexits_table . ' WHERE user_id=' . $user_id . ' AND cageexit_date>=' . $year_start . ' AND cageexit_date<=' . $year_end . ' ORDER BY cageexit_date ASC');
            while ($r = $db->sql_fetchrow($res)) {
                $date = date('Y-m-d', (int)$r['cageexit_date']);
                $cageexit_days[$date] = true;
                $duration = (int) $r['duration_min'];
                $reason = isset($ce_reason_labels[(int)$r['reason_id']]) ? $ce_reason_labels[(int)$r['reason_id']] : '';
                $hours = floor($duration / 60); $mins = $duration % 60;
                $dur_text = $duration > 0 ? ($hours > 0 ? ($hours . 'h' . ($mins > 0 ? sprintf('%02d', $mins) : '')) : ($mins . ' min')) : '';
                $line = '🚪 ' . (isset($user->lang['CHASTITY_CE_TOOLTIP']) ? $user->lang['CHASTITY_CE_TOOLTIP'] : 'Sortie');
                if ($reason !== '')   { $line .= ' : ' . $reason; }
                if ($dur_text !== '') { $line .= ' (' . $dur_text . ')'; }
                if (!empty($r['notes'])) {
                    $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                    if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                    $line .= ' — ' . $note;
                }
                if (!isset($day_tooltips[$date])) { $day_tooltips[$date] = []; }
                $day_tooltips[$date][] = $line;
            }
            $db->sql_freeresult($res);
        }
        if (!empty($activities_table))
        {
            $res = $db->sql_query('SELECT activity_date, reason_id, intensity, notes FROM ' . $activities_table . ' WHERE user_id=' . $user_id . ' AND activity_date>=' . $year_start . ' AND activity_date<=' . $year_end . ' ORDER BY activity_date ASC');
            while ($r = $db->sql_fetchrow($res)) {
                $date = date('Y-m-d', (int)$r['activity_date']);
                $activity_days[$date] = true;
                $reason = isset($act_reason_labels[(int)$r['reason_id']]) ? $act_reason_labels[(int)$r['reason_id']] : '';
                $intensity = (string) $r['intensity'];
                $intensity_lang_key = 'CHASTITY_INTENSITY_' . strtoupper($intensity);
                $intensity_label = isset($user->lang[$intensity_lang_key]) ? $user->lang[$intensity_lang_key] : $intensity;
                $line = '🔥 ' . (isset($user->lang['CHASTITY_ACT_TOOLTIP']) ? $user->lang['CHASTITY_ACT_TOOLTIP'] : 'Activité');
                if ($reason !== '')   { $line .= ' : ' . $reason; }
                if ($intensity !== '' && $intensity_label !== '') { $line .= ' [' . $intensity_label . ']'; }
                if (!empty($r['notes'])) {
                    $note = preg_replace('/\s+/', ' ', (string) $r['notes']);
                    if (mb_strlen($note) > 60) { $note = mb_substr($note, 0, 60) . '…'; }
                    $line .= ' — ' . $note;
                }
                if (!isset($day_tooltips[$date])) { $day_tooltips[$date] = []; }
                $day_tooltips[$date][] = $line;
            }
            $db->sql_freeresult($res);
        }

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
                $tooltip = isset($day_tooltips[$ds]) ? implode("\n", $day_tooltips[$ds]) : '';
                $template->assign_block_vars('yearly_months.month_days', [
                    'DAY'         => $d,
                    'LOCKED'      => $locked,
                    'TODAY'       => ($ds === $today_str),
                    'EMPTY'       => false,
                    'CAGEEXIT'    => isset($cageexit_days[$ds]),
                    'ACTIVITY'    => isset($activity_days[$ds]),
                    'TOOLTIP'     => $tooltip,
                ]);
            }
        }

        $template->assign_vars([
            'VIEW_YEAR'           => $view_year,
            'PREV_YEAR'           => $view_year - 1,
            'COLOR_CAGEEXIT'      => $config['chastity_color_cageexit'] ?? 'FFF3CD',
            'COLOR_ACTIVITY'      => $config['chastity_color_activity'] ?? 'EDE0F7',
            'COLOR_MIXED'         => $config['chastity_color_mixed'] ?? 'F5E6D3',
            'NEXT_YEAR'           => $view_year + 1,
            'TOTAL_LOCKED_YEAR'   => $total_locked_year,
            'TOTAL_YEAR_HOURS'    => $total_year_hours,
            'TOTAL_YEAR_MINUTES'  => $total_year_minutes,
            'S_IS_CURRENT_YEAR'   => ($view_year === $current_year),
            'U_ACTION'            => $this->u_action,
        ]);
    }

    private function cageexits_mode($user, $template, $request, $db, $periods_table, $cageexits_table, $reasons_table, $config)
    {
        $user_id   = (int) $user->data['user_id'];
        $threshold = (int) $config['chastity_cageexit_threshold'];

        $sql = 'SELECT * FROM ' . $periods_table . " WHERE user_id = $user_id AND status = 'active' LIMIT 1";
        $result = $db->sql_query($sql);
        $active_period = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        $has_active = (bool) $active_period;

        if ($request->is_set_post('add_cageexit'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $reason_id    = (int) $request->variable('cageexit_reason_id', 0);
            $duration_min = (int) $request->variable('cageexit_duration_min', 0);
            $notes        = $db->sql_escape($request->variable('cageexit_notes', '', true));
            $confirm_long = $request->variable('confirm_long_cageexit', 0);
            if ($duration_min <= 0) { trigger_error($user->lang['CHASTITY_CAGEEXIT_INVALID_DURATION']); }
            $sql_r = 'SELECT reason_id FROM ' . $reasons_table . ' WHERE reason_id=' . $reason_id . ' AND is_approved=1 AND (is_global=1 OR user_id=' . $user_id . ')';
            $res_r = $db->sql_query($sql_r);
            if (!$db->sql_fetchrow($res_r)) { $db->sql_freeresult($res_r); trigger_error($user->lang['CHASTITY_CAGEEXIT_INVALID_REASON']); }
            $db->sql_freeresult($res_r);
            $over = ($duration_min > $threshold);
            if ($over && !$confirm_long)
            {
                $template->assign_vars(['S_CONFIRM_LONG' => true, 'PREFILL_REASON_ID' => $reason_id, 'PREFILL_DURATION' => $duration_min, 'PREFILL_NOTES' => $request->variable('cageexit_notes', '', true), 'CHASTITY_THRESHOLD_H' => round($threshold / 60, 1)]);
            }
            else
            {
                $cageexit_date_str = $request->variable('cageexit_date', '');
                if ($cageexit_date_str) {
                    $cageexit_date_str = str_replace('T', ' ', $cageexit_date_str);
                    if (strlen($cageexit_date_str) === 16) { $cageexit_date_str .= ':00'; }
                    $now = (int) strtotime($cageexit_date_str);
                }
                if (empty($now) || $now <= 0) { $now = time(); }

                // Vérifier que l'utilisateur était bien verrouillé à la date choisie
                // Rejeter les dates futures
                if ($now > time())
                {
                    $template->assign_vars([
                        'ERROR_NOT_LOCKED'       => true,
                        'ERROR_NOT_LOCKED_DATE'  => date('d/m/Y H:i', $now),
                        'PREFILL_CE_DATE'        => $request->variable('cageexit_date', ''),
                        'PREFILL_CE_REASON'      => $reason_id,
                        'PREFILL_CE_DURATION'    => $duration_min,
                        'PREFILL_CE_NOTES'       => $request->variable('cageexit_notes', '', true),
                    ]);
                    goto ce_display;
                }
                $sql_lock = 'SELECT period_id FROM ' . $periods_table
                    . ' WHERE user_id=' . $user_id
                    . ' AND start_date <= ' . $now
                    . ' AND (end_date >= ' . $now . " OR (status = 'active' AND " . $now . ' <= ' . time() . '))'
                    . ' LIMIT 1';
                $res_lock = $db->sql_query($sql_lock);
                $locked_period = $db->sql_fetchrow($res_lock);
                $db->sql_freeresult($res_lock);
                $was_locked = (bool) $locked_period;
                // Utiliser le period_id de la période qui couvrait la date
                if ($locked_period) { $active_period = $locked_period; }
                if (!$was_locked)
                {
                    $template->assign_vars([
                        'ERROR_NOT_LOCKED'       => true,
                        'ERROR_NOT_LOCKED_DATE'  => date('d/m/Y H:i', $now),
                        'PREFILL_CE_DATE'        => $request->variable('cageexit_date', ''),
                        'PREFILL_CE_REASON'      => $reason_id,
                        'PREFILL_CE_DURATION'    => $duration_min,
                        'PREFILL_CE_NOTES'       => $request->variable('cageexit_notes', '', true),
                    ]);
                    // Sortir du bloc d'enregistrement sans perdre les valeurs
                    goto ce_display;
                }
                $db->sql_query('INSERT INTO ' . $cageexits_table . ' (user_id,period_id,cageexit_date,duration_min,reason_id,notes,auto_closed,created_time) VALUES (' . $user_id . ',' . (int)$active_period['period_id'] . ',' . $now . ',' . $duration_min . ',' . $reason_id . ",'" . $notes . "',0," . time() . ')');
                if ($over && $confirm_long)
                {
                    $end_date = $now + ($duration_min * 60);
                    $days_count = (int) floor(($end_date - (int) $active_period['start_date']) / 86400);
                    $db->sql_query('UPDATE ' . $periods_table . " SET end_date=$end_date,status='completed',days_count=$days_count,updated_time=$now WHERE period_id=" . (int)$active_period['period_id']);
                    $this->recalc_user_totals($db, $periods_table, $user_id);
                    trigger_error($user->lang['CHASTITY_CAGEEXIT_SAVED_CLOSED']);
                }
                else { trigger_error($user->lang['CHASTITY_CAGEEXIT_SAVED']); }
            }
        }

        if ($request->is_set_post('add_personal_reason'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $label = trim($request->variable('new_reason_label', '', true));
            if (mb_strlen($label) < 2 || mb_strlen($label) > 100) { trigger_error($user->lang['CHASTITY_CAGEEXIT_REASON_INVALID_LABEL']); }
            $share_global = $request->variable('reason_share_global', 0);
            $db->sql_query('INSERT INTO ' . $reasons_table . " (label,is_global,user_id,is_approved,created_time) VALUES ('" . $db->sql_escape($label) . "'," . (int)$share_global . ",$user_id,0," . time() . ')');

            // Notification MP à l'admin : nouveau motif de sortie à valider
            $this->send_reason_notification($db, $config, $user, 'cageexit', $label);

            trigger_error($user->lang['CHASTITY_CAGEEXIT_REASON_PENDING']);
        }

        if ($request->is_set_post('delete_cageexit'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $del_id = (int) $request->variable('cageexit_id', 0);
            if ($del_id > 0)
            {
                $db->sql_query('DELETE FROM ' . $cageexits_table . ' WHERE cageexit_id=' . $del_id . ' AND user_id=' . $user_id);
            }
            trigger_error($user->lang['CHASTITY_DELETED']);
        }

        ce_display:
        $res_r = $db->sql_query('SELECT reason_id,label,is_approved FROM ' . $reasons_table . ' WHERE (is_global=1 AND is_approved=1) OR (is_global=0 AND user_id=' . $user_id . ') ORDER BY is_global DESC, label ASC');
        while ($row = $db->sql_fetchrow($res_r))
        { $template->assign_block_vars('cageexit_reasons', ['REASON_ID' => $row['reason_id'], 'LABEL' => $row['label'], 'IS_PENDING' => !(bool)$row['is_approved']]); }
        $db->sql_freeresult($res_r);

        $res_h = $db->sql_query('SELECT r.cageexit_id,r.cageexit_date,r.duration_min,r.notes,r.auto_closed,rr.label as reason_label FROM ' . $cageexits_table . ' r LEFT JOIN ' . $reasons_table . ' rr ON rr.reason_id=r.reason_id WHERE r.user_id=' . $user_id . ' ORDER BY r.cageexit_date DESC LIMIT 20');
        while ($row = $db->sql_fetchrow($res_h))
        {
            $dh = (int)floor($row['duration_min']/60); $dm = $row['duration_min']%60;
            $ds = ($dh > 0 ? $dh . 'h' : '') . ($dm > 0 ? $dm . 'min' : ''); if (!$ds) { $ds = $row['duration_min'] . 'min'; }
            $template->assign_block_vars('cageexits_history', ['CAGEEXIT_ID' => $row['cageexit_id'], 'DATE' => $user->format_date((int)$row['cageexit_date'], 'd/m/Y H:i'), 'DURATION' => $ds, 'REASON' => $row['reason_label'] ?? '—', 'NOTES' => $row['notes'], 'AUTO_CLOSED' => (bool)$row['auto_closed']]);
        }
        $db->sql_freeresult($res_h);

        add_form_key('ucp_chastity');
        $template->assign_vars([
            'U_ACTION'                => $this->u_action,
            'S_HAS_ACTIVE'            => $has_active,
            'CHASTITY_THRESHOLD_H'    => round($threshold / 60, 1),
            'CAGEEXIT_DATE_DEFAULT'   => date('Y-m-d\TH:i', time()),
            'CAGEEXIT_THRESHOLD_HINT' => sprintf($user->lang['CHASTITY_CAGEEXIT_THRESHOLD_HINT_UCP'], round($threshold / 60, 1)),
        ]);
    }

    private function activities_mode($user, $template, $request, $db, $periods_table, $activities_table, $reasons_table, $config = null)
    {
        $user_id = (int) $user->data['user_id'];

        $sql = 'SELECT period_id FROM ' . $periods_table . " WHERE user_id=$user_id AND status='active' LIMIT 1";
        $result = $db->sql_query($sql);
        $active = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        $period_id = $active ? (int)$active['period_id'] : 0;

        if ($request->is_set_post('add_activity'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $reason_id = (int) $request->variable('activity_reason_id', 0);
            $intensity = $request->variable('activity_intensity', 'medium');
            if (!in_array($intensity, ['light', 'medium', 'strong'])) { $intensity = 'medium'; }
            $notes = $db->sql_escape($request->variable('activity_notes', '', true));
            $sql_r = 'SELECT reason_id FROM ' . $reasons_table . ' WHERE reason_id=' . $reason_id . ' AND is_approved=1 AND (is_global=1 OR user_id=' . $user_id . ')';
            $res_r = $db->sql_query($sql_r);
            if (!$db->sql_fetchrow($res_r)) { $db->sql_freeresult($res_r); trigger_error($user->lang['CHASTITY_ACTIVITY_INVALID_REASON']); }
            $db->sql_freeresult($res_r);
            $activity_date_str = $request->variable('activity_date', '');
            if ($activity_date_str) {
                // Normaliser "2026-04-28T14:30" → "2026-04-28 14:30:00"
                $activity_date_str = str_replace('T', ' ', $activity_date_str);
                if (strlen($activity_date_str) === 16) { $activity_date_str .= ':00'; }
                $act_ts = (int) strtotime($activity_date_str);
            }
            if (empty($act_ts) || $act_ts <= 0) { $act_ts = time(); }

            // Vérifier que l'utilisateur était bien verrouillé à la date choisie
            // Rejeter les dates futures
            if ($act_ts > time())
            {
                $template->assign_vars([
                    'ERROR_NOT_LOCKED'       => true,
                    'ERROR_NOT_LOCKED_DATE'  => date('d/m/Y H:i', $act_ts),
                    'PREFILL_ACT_DATE'       => $request->variable('activity_date', ''),
                    'PREFILL_ACT_REASON'     => $reason_id,
                    'PREFILL_ACT_INTENSITY'  => $request->variable('activity_intensity', 'medium'),
                    'PREFILL_ACT_NOTES'      => $request->variable('activity_notes', '', true),
                ]);
                goto act_display;
            }
            $sql_lock_act = 'SELECT period_id FROM ' . $periods_table
                . ' WHERE user_id=' . $user_id
                . ' AND start_date <= ' . $act_ts
                . ' AND (end_date >= ' . $act_ts . " OR (status = 'active' AND " . $act_ts . ' <= ' . time() . '))'
                . ' LIMIT 1';
            $res_lock_act = $db->sql_query($sql_lock_act);
            $locked_period_act = $db->sql_fetchrow($res_lock_act);
            $db->sql_freeresult($res_lock_act);
            $was_locked_act = (bool) $locked_period_act;
            // Utiliser le period_id correct pour cet enregistrement
            if ($locked_period_act) { $period_id = (int)$locked_period_act['period_id']; }
            if (!$was_locked_act)
            {
                $template->assign_vars([
                    'ERROR_NOT_LOCKED'       => true,
                    'ERROR_NOT_LOCKED_DATE'  => date('d/m/Y H:i', $act_ts),
                    'PREFILL_ACT_DATE'       => $request->variable('activity_date', ''),
                    'PREFILL_ACT_REASON'     => $reason_id,
                    'PREFILL_ACT_INTENSITY'  => $request->variable('activity_intensity', 'medium'),
                    'PREFILL_ACT_NOTES'      => $request->variable('activity_notes', '', true),
                ]);
                goto act_display;
            }
            $db->sql_query('INSERT INTO ' . $activities_table . " (user_id,period_id,activity_date,reason_id,intensity,notes,created_time) VALUES ($user_id,$period_id,$act_ts,$reason_id,'" . $db->sql_escape($intensity) . "','$notes'," . time() . ")");
            trigger_error($user->lang['CHASTITY_ACTIVITY_SAVED']);
        }

        if ($request->is_set_post('add_personal_activity_reason'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $label = trim($request->variable('new_activity_reason_label', '', true));
            if (mb_strlen($label) < 2 || mb_strlen($label) > 100) { trigger_error($user->lang['CHASTITY_ACTIVITY_REASON_INVALID_LABEL']); }
            $share_global = $request->variable('reason_share_global', 0);
            $db->sql_query('INSERT INTO ' . $reasons_table . " (label,is_global,user_id,is_approved,created_time) VALUES ('" . $db->sql_escape($label) . "'," . (int)$share_global . ",$user_id,0," . time() . ')');

            // Notification MP à l'admin : nouveau motif d'activité à valider
            $this->send_reason_notification($db, $config, $user, 'activity', $label);

            trigger_error($user->lang['CHASTITY_ACTIVITY_REASON_PENDING']);
        }

        if ($request->is_set_post('delete_activity'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $del_id = (int) $request->variable('activity_id', 0);
            if ($del_id > 0)
            {
                $db->sql_query('DELETE FROM ' . $activities_table . ' WHERE activity_id=' . $del_id . ' AND user_id=' . $user_id);
            }
            trigger_error($user->lang['CHASTITY_DELETED']);
        }

        act_display:
        $res_r = $db->sql_query('SELECT reason_id,label,is_approved FROM ' . $reasons_table . ' WHERE (is_global=1 AND is_approved=1) OR (is_global=0 AND user_id=' . $user_id . ') ORDER BY is_global DESC, label ASC');
        while ($row = $db->sql_fetchrow($res_r))
        { $template->assign_block_vars('activity_reasons', ['REASON_ID' => $row['reason_id'], 'LABEL' => $row['label'], 'IS_PENDING' => !(bool)$row['is_approved']]); }
        $db->sql_freeresult($res_r);

        $res_h = $db->sql_query('SELECT a.activity_id,a.activity_date,a.intensity,a.notes,ar.label as reason_label FROM ' . $activities_table . ' a LEFT JOIN ' . $reasons_table . ' ar ON ar.reason_id=a.reason_id WHERE a.user_id=' . $user_id . ' ORDER BY a.activity_date DESC LIMIT 20');
        while ($row = $db->sql_fetchrow($res_h))
        {
            $intl = strtoupper($row['intensity']);
            $template->assign_block_vars('activities_history', ['ACTIVITY_ID' => $row['activity_id'], 'DATE' => $user->format_date((int)$row['activity_date'], 'd/m/Y H:i'), 'INTENSITY' => $user->lang['CHASTITY_INTENSITY_' . $intl] ?? $row['intensity'], 'REASON' => $row['reason_label'] ?? '—', 'NOTES' => $row['notes']]);
        }
        $db->sql_freeresult($res_h);

        add_form_key('ucp_chastity');
        $template->assign_vars([
            'U_ACTION'              => $this->u_action,
            'ACTIVITY_DATE_DEFAULT' => date('Y-m-d\TH:i', time()),
        ]);
    }


    /**
     * Envoie un MP à l'admin quand un utilisateur propose un nouveau motif
     * @param string $type 'cageexit' ou 'activity'
     * @param string $label Le libellé du motif proposé
     */
    private function send_reason_notification($db, $config, $user, $type, $label)
    {
        $admin_id = (int) ($config['chastity_notify_admin_id'] ?? 0);
        if ($admin_id <= 0) { return; }

        // Vérifier que l'admin existe
        $sql = 'SELECT user_id, username FROM ' . USERS_TABLE . ' WHERE user_id = ' . $admin_id;
        $result = $db->sql_query($sql);
        $admin = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        if (!$admin) { return; }

        if (!function_exists('submit_pm'))
        {
            global $phpbb_root_path, $phpEx;
            include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
        }

        global $phpEx;
        $acp_mode = ($type === 'cageexit') ? 'cageexit_reasons' : 'activity_reasons';
        $acp_url = rtrim(generate_board_url(), '/') . '/adm/index.' . $phpEx . '?i=-verturin-chastitytracker-acp-main_module&mode=' . $acp_mode;

        $type_key = ($type === 'cageexit') ? 'CHASTITY_NOTIFY_REASON_CAGEEXIT' : 'CHASTITY_NOTIFY_REASON_ACTIVITY';
        $subject = sprintf($user->lang['CHASTITY_NOTIFY_REASON_SUBJECT'], $user->data['username']);
        $message_text = sprintf(
            $user->lang['CHASTITY_NOTIFY_REASON_MESSAGE'],
            $user->data['username'],
            $user->lang[$type_key],
            $label,
            $acp_url
        );

        $uid = $bitfield = $options = '';
        generate_text_for_storage($message_text, $uid, $bitfield, $options, true, true, true);

        $pm_data = [
            'from_user_id'     => $user->data['user_id'],
            'from_user_ip'     => $user->ip,
            'from_username'    => $user->data['username'],
            'enable_sig'       => false,
            'enable_bbcode'    => true,
            'enable_smilies'   => true,
            'enable_urls'      => true,
            'icon_id'          => 0,
            'bbcode_bitfield'  => $bitfield,
            'bbcode_uid'       => $uid,
            'message'          => $message_text,
            'address_list'     => ['u' => [$admin_id => 'to']],
        ];

        submit_pm('post', $subject, $pm_data, false);
    }

    /**
     * Envoie un MP à l'admin pour une cage proposée ou un commentaire à valider.
     * @param string $type 'cage' ou 'comment'
     */
    private function send_cage_admin_notification($db, $config, $user, $type, $cage_name, $comment_text = '')
    {
        $admin_id = (int) ($config['chastity_notify_admin_id'] ?? 0);
        if ($admin_id <= 0) { return; }

        $sql = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_id = ' . $admin_id;
        $r = $db->sql_query($sql);
        $admin = $db->sql_fetchrow($r);
        $db->sql_freeresult($r);
        if (!$admin) { return; }

        if (!function_exists('submit_pm'))
        {
            global $phpbb_root_path, $phpEx;
            include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
        }

        // URL directe vers l'ACP catalogue de cages
        global $phpEx;
        $acp_url = rtrim(generate_board_url(), '/') . '/adm/index.' . $phpEx . '?i=-verturin-chastitytracker-acp-main_module&mode=cage_catalog';

        if ($type === 'cage')
        {
            $subject = sprintf(
                isset($user->lang['CHASTITY_NOTIFY_CAGE_SUBJECT']) ? $user->lang['CHASTITY_NOTIFY_CAGE_SUBJECT'] : '[Chastity] Nouvelle cage proposée — %s',
                $user->data['username']
            );
            $message_text = sprintf(
                isset($user->lang['CHASTITY_NOTIFY_CAGE_MESSAGE']) ? $user->lang['CHASTITY_NOTIFY_CAGE_MESSAGE'] : "L'utilisateur [b]%s[/b] a proposé une nouvelle cage : [b]%s[/b].\n\nMerci de la valider depuis l'ACP → Catalogue de cages.\n\n[url=%s]Lien direct vers l'ACP[/url]",
                $user->data['username'],
                $cage_name,
                $acp_url
            );
        }
        else // 'comment'
        {
            $subject = sprintf(
                isset($user->lang['CHASTITY_NOTIFY_COMMENT_SUBJECT']) ? $user->lang['CHASTITY_NOTIFY_COMMENT_SUBJECT'] : '[Chastity] Nouveau commentaire à valider — %s',
                $user->data['username']
            );
            $message_text = sprintf(
                isset($user->lang['CHASTITY_NOTIFY_COMMENT_MESSAGE']) ? $user->lang['CHASTITY_NOTIFY_COMMENT_MESSAGE'] : "L'utilisateur [b]%s[/b] a posté un commentaire sur la cage [b]%s[/b] :\n\n[quote]%s[/quote]\n\nMerci de le valider depuis l'ACP → Catalogue de cages.\n\n[url=%s]Lien direct vers l'ACP[/url]",
                $user->data['username'],
                $cage_name,
                $comment_text,
                $acp_url
            );
        }

        $uid = $bitfield = $options = '';
        generate_text_for_storage($message_text, $uid, $bitfield, $options, true, true, true);

        $pm_data = [
            'from_user_id'     => $user->data['user_id'],
            'from_user_ip'     => $user->ip,
            'from_username'    => $user->data['username'],
            'enable_sig'       => false,
            'enable_bbcode'    => true,
            'enable_smilies'   => true,
            'enable_urls'      => true,
            'icon_id'          => 0,
            'bbcode_bitfield'  => $bitfield,
            'bbcode_uid'       => $uid,
            'message'          => $message_text,
            'address_list'     => ['u' => [$admin_id => 'to']],
        ];
        submit_pm('post', $subject, $pm_data, false);
    }

    // ════════════════════════════════════════════════════════════
    // UCP — Ma collection de cages
    // ════════════════════════════════════════════════════════════
    private function ucp_cage_collection_mode($template, $request, $db, $user, $tables)
    {
        $catalog_table = $tables['catalog'];
        $cages_table   = $tables['cages'];
        $photos_table  = $tables['photos'];
        $usage_table   = $tables['usage'];
        $uid           = (int) $user->data['user_id'];

        // Retirer de la collection
        if ($request->is_set_post('remove_cage'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cage_id = (int) $request->variable('cage_id', 0);
            $db->sql_query('DELETE FROM ' . $cages_table . ' WHERE cage_id = ' . $cage_id . ' AND user_id = ' . $uid);
            $db->sql_query('UPDATE ' . $catalog_table . ' c SET usage_count = (SELECT COUNT(*) FROM ' . $cages_table . ' uc WHERE uc.catalog_id = c.catalog_id)');
            trigger_error($user->lang['CONFIG_UPDATED']);
        }

        // Archiver / désarchiver
        if ($request->is_set_post('toggle_archive'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cage_id = (int) $request->variable('cage_id', 0);
            $res = $db->sql_query('SELECT is_active FROM ' . $cages_table . ' WHERE cage_id = ' . $cage_id . ' AND user_id = ' . $uid);
            $row = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if ($row)
            {
                $new = ((int) $row['is_active'] === 1) ? 0 : 1;
                $db->sql_query('UPDATE ' . $cages_table . ' SET is_active = ' . $new . ' WHERE cage_id = ' . $cage_id . ' AND user_id = ' . $uid);
            }
            trigger_error($user->lang['CONFIG_UPDATED']);
        }

        // Modifier notes
        if ($request->is_set_post('save_cage_notes'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $cage_id = (int) $request->variable('cage_id', 0);
            $notes   = $request->variable('cage_notes', '', true);
            $db->sql_query('UPDATE ' . $cages_table . " SET cage_notes = '" . $db->sql_escape($notes) . "' WHERE cage_id = " . $cage_id . ' AND user_id = ' . $uid);
            trigger_error($user->lang['CONFIG_UPDATED']);
        }

        // Liste collection
        $sql = 'SELECT uc.cage_id, uc.cage_notes, uc.is_active, uc.added_at, uc.catalog_id,
                       cc.cage_name, cc.cage_brand, cc.cage_material, cc.cage_type,
                       cc.avg_rating, cc.rating_count
                FROM ' . $cages_table . ' uc
                JOIN ' . $catalog_table . ' cc ON cc.catalog_id = uc.catalog_id
                WHERE uc.user_id = ' . $uid . '
                ORDER BY uc.is_active DESC, cc.cage_name ASC';
        $res = $db->sql_query($sql);
        $rows = [];
        while ($r = $db->sql_fetchrow($res)) { $rows[] = $r; }
        $db->sql_freeresult($res);

        // Map matériau key → nom
        $materials_map = $this->load_materials_map($db, isset($tables['materials']) ? $tables['materials'] : '');

        // Photos principales en lot + toutes les photos par cage pour lightbox
        $main_photos = [];
        $all_photos = [];
        if (!empty($rows))
        {
            $catalog_ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
            $sql = 'SELECT catalog_id, filename, is_main FROM ' . $photos_table . '
                    WHERE ' . $db->sql_in_set('catalog_id', $catalog_ids) . '
                    ORDER BY is_main DESC, photo_id ASC';
            $res = $db->sql_query($sql);
            while ($p = $db->sql_fetchrow($res)) {
                $cid = (int) $p['catalog_id'];
                if ((int) $p['is_main'] === 1 && !isset($main_photos[$cid])) {
                    $main_photos[$cid] = $p['filename'];
                }
                if (!isset($all_photos[$cid])) { $all_photos[$cid] = []; }
                $all_photos[$cid][] = $p['filename'];
            }
            $db->sql_freeresult($res);
        }

        // Jours d'utilisation par cage
        $usage_days = [];
        $now = time();
        $sql = 'SELECT cage_id, SUM(CASE WHEN end_date > 0 THEN end_date - start_date ELSE ' . $now . ' - start_date END) AS total_sec
                FROM ' . $usage_table . ' WHERE user_id = ' . $uid . ' GROUP BY cage_id';
        $res = $db->sql_query($sql);
        while ($u = $db->sql_fetchrow($res))
        {
            $usage_days[(int) $u['cage_id']] = (int) floor((int) $u['total_sec'] / 86400);
        }
        $db->sql_freeresult($res);

        // Auto-cleanup : clôturer les cage_usage liés à des périodes qui ne sont plus actives
        // (cas où une période a été terminée avant l'intégration cages, ou bug)
        $periods_table_clean = $this->container->getParameter('verturin.chastitytracker.tables.chastity_periods');
        $db->sql_query("UPDATE " . $usage_table . " cu
            INNER JOIN " . $periods_table_clean . " p ON p.period_id = cu.period_id
            SET cu.end_date = COALESCE(NULLIF(p.end_date, 0), UNIX_TIMESTAMP())
            WHERE cu.user_id = " . $uid . " AND cu.end_date = 0 AND p.status <> 'active'");

        // Détecter quelle cage est utilisée actuellement (période active, end_date = 0)
        // ON join avec la table periods pour ne retourner que les usages liés à une période active
        $current_cage_id = 0;
        $sql = 'SELECT cu.cage_id FROM ' . $usage_table . ' cu
                JOIN ' . $periods_table_clean . " p ON p.period_id = cu.period_id
                WHERE cu.user_id = " . $uid . " AND cu.end_date = 0 AND p.status = 'active'
                ORDER BY cu.start_date DESC";
        $res = $db->sql_query($sql);
        $cur = $db->sql_fetchrow($res);
        if ($cur) { $current_cage_id = (int) $cur['cage_id']; }
        $db->sql_freeresult($res);

        foreach ($rows as $row)
        {
            $cid = (int) $row['cage_id'];
            $catid = (int) $row['catalog_id'];
            $mkey = $row['cage_material'];
            $template->assign_block_vars('my_cages', [
                'CAGE_ID'    => $cid,
                'NAME'       => $row['cage_name'],
                'BRAND'      => $row['cage_brand'],
                'MATERIAL'   => isset($materials_map[$mkey]) ? $materials_map[$mkey] : $mkey,
                'TYPE'       => $row['cage_type'],
                'NOTES'      => $row['cage_notes'],
                'IS_ACTIVE'  => (int) $row['is_active'],
                'IS_CURRENT' => ($cid === $current_cage_id) ? 1 : 0,
                'MAIN_PHOTO' => isset($main_photos[$catid]) ? $main_photos[$catid] : '',
                'TOTAL_DAYS' => isset($usage_days[$cid]) ? $usage_days[$cid] : 0,
                'AVG_RATING'   => isset($row['avg_rating']) ? (float) $row['avg_rating'] : 0,
                'RATING_COUNT' => isset($row['rating_count']) ? (int) $row['rating_count'] : 0,
                'PHOTO_COUNT'  => isset($all_photos[$catid]) ? count($all_photos[$catid]) : 0,
            ]);

            // Sous-bloc photos pour le lightbox
            if (isset($all_photos[$catid])) {
                foreach ($all_photos[$catid] as $filename) {
                    $template->assign_block_vars('my_cages.photos', [
                        'FILENAME' => $filename,
                    ]);
                }
            }
        }

        $template->assign_vars([
            'U_ACTION'  => $this->u_action,
            'BOARD_URL' => generate_board_url() . '/',
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // UCP — Catalogue (parcourir + proposer)
    // ════════════════════════════════════════════════════════════
    private function ucp_cage_catalog_mode($template, $request, $db, $user, $tables, $config)
    {
        $catalog_table = $tables['catalog'];
        $cages_table   = $tables['cages'];
        $photos_table  = $tables['photos'];
        $mfr_table     = $tables['manufacturers'];
        $uid           = (int) $user->data['user_id'];

        // Ajouter à ma collection
        if ($request->is_set_post('add_to_collection'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $catalog_id = (int) $request->variable('catalog_id', 0);
            $res = $db->sql_query('SELECT cage_id FROM ' . $cages_table . ' WHERE user_id = ' . $uid . ' AND catalog_id = ' . $catalog_id);
            $exists = $db->sql_fetchrow($res);
            $db->sql_freeresult($res);
            if (!$exists)
            {
                $db->sql_query('INSERT INTO ' . $cages_table . ' ' . $db->sql_build_array('INSERT', [
                    'user_id'    => $uid,
                    'catalog_id' => $catalog_id,
                    'cage_notes' => '',
                    'is_active'  => 1,
                    'added_at'   => time(),
                ]));
                $db->sql_query('UPDATE ' . $catalog_table . ' c SET usage_count = (SELECT COUNT(*) FROM ' . $cages_table . ' uc WHERE uc.catalog_id = c.catalog_id)');
            }
            trigger_error($user->lang['CHASTITY_CAGE_ADDED_TO_COLLECTION']);
        }

        // Noter une cage (1-5 étoiles) + commentaire optionnel modéré
        if ($request->is_set_post('rate_cage'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $catalog_id = (int) $request->variable('catalog_id', 0);
            $rating     = max(1, min(5, (int) $request->variable('rating', 0)));
            $comment    = trim($request->variable('rating_comment', '', true));
            if (mb_strlen($comment) > 500) { $comment = mb_substr($comment, 0, 500); }
            $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';

            if ($ratings_table && $rating > 0 && $catalog_id > 0)
            {
                $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($ratings_table) . "'");
                if ($db->sql_fetchrow($check))
                {
                    $db->sql_freeresult($check);

                    // La note est toujours validée d'office, le commentaire nécessite validation s'il existe
                    $is_validated = empty($comment) ? 1 : 0;

                    $res = $db->sql_query('SELECT rating_id, comment AS old_comment FROM ' . $ratings_table . ' WHERE catalog_id = ' . $catalog_id . ' AND user_id = ' . $uid);
                    $existing = $db->sql_fetchrow($res);
                    $db->sql_freeresult($res);

                    if ($existing)
                    {
                        // Si le commentaire change, repasse en validation
                        $needs_validation = (!empty($comment) && $existing['old_comment'] !== $comment);
                        $is_val = $needs_validation ? 0 : 1;
                        $db->sql_query('UPDATE ' . $ratings_table . " SET rating = " . $rating . ", comment = '" . $db->sql_escape($comment) . "', is_validated = " . $is_val . " WHERE rating_id = " . (int) $existing['rating_id']);
                        $new_comment_to_notify = $needs_validation;
                    }
                    else
                    {
                        // Vérifier que la colonne is_validated existe (rétro-compat)
                        try {
                            $db->sql_query('INSERT INTO ' . $ratings_table . ' ' . $db->sql_build_array('INSERT', [
                                'catalog_id'   => $catalog_id,
                                'user_id'      => $uid,
                                'rating'       => $rating,
                                'comment'      => $comment,
                                'is_validated' => $is_validated,
                                'created_at'   => time(),
                            ]));
                        } catch (\Exception $e) {
                            // Si colonne is_validated pas encore migrée
                            $db->sql_query('INSERT INTO ' . $ratings_table . ' ' . $db->sql_build_array('INSERT', [
                                'catalog_id' => $catalog_id,
                                'user_id'    => $uid,
                                'rating'     => $rating,
                                'comment'    => $comment,
                                'created_at' => time(),
                            ]));
                        }
                        $new_comment_to_notify = !empty($comment);
                    }

                    // Recalculer la moyenne (toutes les notes comptent, validées ou non)
                    $res = $db->sql_query('SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM ' . $ratings_table . ' WHERE catalog_id = ' . $catalog_id);
                    $r = $db->sql_fetchrow($res);
                    $avg = $r ? (float) $r['avg_r'] : 0;
                    $cnt = $r ? (int) $r['cnt'] : 0;
                    $db->sql_freeresult($res);
                    $db->sql_query('UPDATE ' . $catalog_table . ' SET avg_rating = ' . round($avg, 2) . ', rating_count = ' . $cnt . ' WHERE catalog_id = ' . $catalog_id);

                    // Notification admin si nouveau commentaire à valider
                    if ($new_comment_to_notify)
                    {
                        // Récupérer le nom de la cage
                        $res = $db->sql_query('SELECT cage_name FROM ' . $catalog_table . ' WHERE catalog_id = ' . $catalog_id);
                        $cage = $db->sql_fetchrow($res);
                        $db->sql_freeresult($res);
                        $cage_name = $cage ? $cage['cage_name'] : '#' . $catalog_id;
                        $this->send_cage_admin_notification($db, $config, $user, 'comment', $cage_name, $comment);
                    }
                }
                else { $db->sql_freeresult($check); }
            }
            trigger_error($user->lang['CHASTITY_CAGE_RATED']);
        }

        // Proposer une cage (avec fabricant existant/nouveau + photo optionnelle)
        if ($request->is_set_post('propose_cage'))
        {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }

            global $phpbb_root_path;

            $cage_name = $request->variable('propose_name', '', true);
            if (empty($cage_name)) {
                trigger_error($user->lang['CHASTITY_CAGE_NAME_REQUIRED']);
            }

            // Gestion du fabricant : existant ou nouveau
            $manufacturer_id = (int) $request->variable('propose_mfr_id', 0);
            $new_mfr_name    = $request->variable('propose_new_mfr_name', '', true);
            if (!empty($new_mfr_name))
            {
                // Créer un nouveau fabricant (non partenaire par défaut)
                $db->sql_query('INSERT INTO ' . $mfr_table . ' ' . $db->sql_build_array('INSERT', [
                    'name'          => $new_mfr_name,
                    'address'       => '',
                    'phone'         => '',
                    'email'         => '',
                    'website'       => $request->variable('propose_new_mfr_website', '', true),
                    'is_partner'    => 0,
                    'partner_notes' => 'Proposé par ' . $user->data['username'],
                    'created_at'    => time(),
                    'updated_at'    => time(),
                ]));
                $manufacturer_id = (int) $db->sql_nextid();
            }

            // Créer la cage en attente de validation
            // Matériau : existant ou nouveau proposé
            $material_key = $request->variable('propose_material', '', true);
            $new_material = $request->variable('propose_new_material', '', true);
            if (!empty($new_material)) {
                // Créer le matériau en attente de validation
                $materials_table = isset($tables['materials']) ? $tables['materials'] : '';
                if ($materials_table) {
                    $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($materials_table) . "'");
                    if ($db->sql_fetchrow($check)) {
                        $db->sql_freeresult($check);
                        $auto_key = strtolower(preg_replace('/[^a-z0-9]/i', '', $new_material));
                        if (empty($auto_key)) { $auto_key = 'mat_' . time(); }
                        $db->sql_query('INSERT INTO ' . $materials_table . ' ' . $db->sql_build_array('INSERT', [
                            'material_key'  => $auto_key,
                            'material_name' => $new_material,
                            'is_validated'  => 0,
                            'created_at'    => time(),
                        ]));
                        $material_key = $auto_key;
                    } else { $db->sql_freeresult($check); }
                }
            }

            $sql_ary = [
                'cage_name'        => $cage_name,
                'cage_brand'       => $request->variable('propose_brand', '', true),
                'cage_material'    => $material_key,
                'cage_type'        => $request->variable('propose_type', '', true),
                'cage_description' => $request->variable('propose_description', '', true),
                'manufacturer_id'  => $manufacturer_id,
                'added_by_user_id' => $uid,
                'is_validated'     => 0,
                'usage_count'      => 0,
                'created_at'       => time(),
                'updated_at'       => time(),
            ];
            $db->sql_query('INSERT INTO ' . $catalog_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
            $new_catalog_id = (int) $db->sql_nextid();

            // Photo optionnelle (en attente de validation admin)
            $file_data = $request->file('propose_photo');
            if (!empty($file_data['tmp_name']) && is_uploaded_file($file_data['tmp_name']))
            {
                $ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png']) && $file_data['size'] <= 1024 * 1024)
                {
                    $filename = 'cage_' . $new_catalog_id . '_' . time() . '.' . $ext;
                    $upload_dir = $phpbb_root_path . 'ext/verturin/chastitytracker/images/cages/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                        @chmod($upload_dir, 0777);
                    }
                    if (move_uploaded_file($file_data['tmp_name'], $upload_dir . $filename))
                    {
                        // Redimensionner si l'extension expose la méthode (sinon, l'admin redimensionnera)
                        $this->resize_user_image($upload_dir . $filename, $ext, 800, 600);
                        $db->sql_query('INSERT INTO ' . $photos_table . ' ' . $db->sql_build_array('INSERT', [
                            'catalog_id'   => $new_catalog_id,
                            'user_id'      => $uid,
                            'filename'     => $filename,
                            'is_main'      => 1,
                            'is_validated' => 0,
                            'uploaded_at'  => time(),
                        ]));
                    }
                }
            }

            // Notifier l'admin (MP)
            $this->send_cage_admin_notification($db, $config, $user, 'cage', $cage_name, '');

            trigger_error($user->lang['CHASTITY_CAGE_PROPOSED']);
        }

        // IDs des cages déjà dans ma collection
        $my_catalog_ids = [];
        $res = $db->sql_query('SELECT catalog_id FROM ' . $cages_table . ' WHERE user_id = ' . $uid);
        while ($r = $db->sql_fetchrow($res)) { $my_catalog_ids[] = (int) $r['catalog_id']; }
        $db->sql_freeresult($res);

        // Filtres
        $filter_brand    = $request->variable('filter_brand', '', true);
        $filter_material = $request->variable('filter_material', '', true);
        $where = ' WHERE c.is_validated = 1';
        if (!empty($filter_brand))    { $where .= " AND c.cage_brand = '" . $db->sql_escape($filter_brand) . "'"; }
        if (!empty($filter_material)) { $where .= " AND c.cage_material = '" . $db->sql_escape($filter_material) . "'"; }

        // Liste catalogue
        $sql = 'SELECT c.catalog_id, c.cage_name, c.cage_brand, c.cage_material, c.cage_type,
                       c.cage_description, c.usage_count, c.avg_rating, c.rating_count,
                       c.added_by_user_id, m.name AS manufacturer_name,
                       u.username AS added_by_username, u.user_colour AS added_by_colour
                FROM ' . $catalog_table . ' c
                LEFT JOIN ' . $mfr_table . ' m ON m.manufacturer_id = c.manufacturer_id
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = c.added_by_user_id'
                . $where . ' ORDER BY c.cage_name ASC';
        $res = $db->sql_query($sql);
        $rows = [];
        while ($r = $db->sql_fetchrow($res)) { $rows[] = $r; }
        $db->sql_freeresult($res);

        // Récupérer les statuts de verrouillage des proposeurs (pour le cadenas)
        $proposer_locked = [];
        if (!empty($rows))
        {
            $proposer_ids = array_filter(array_map(function($r){ return (int) $r['added_by_user_id']; }, $rows));
            if (!empty($proposer_ids))
            {
                $proposer_ids = array_unique($proposer_ids);
                try {
                    $users_ct_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_users');
                    $sql_l = 'SELECT user_id FROM ' . $users_ct_table . " WHERE chastity_status = 'locked' AND " . $db->sql_in_set('user_id', $proposer_ids);
                    $res = $db->sql_query($sql_l);
                    while ($lrow = $db->sql_fetchrow($res)) { $proposer_locked[(int) $lrow['user_id']] = true; }
                    $db->sql_freeresult($res);
                } catch (\Exception $e) {}
            }
        }

        // Map matériau key → name
        $materials_map = $this->load_materials_map($db, isset($tables['materials']) ? $tables['materials'] : '');

        // Photos principales en lot + toutes les photos pour lightbox
        $main_photos = [];
        $all_photos = [];
        if (!empty($rows))
        {
            $ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
            $sql = 'SELECT catalog_id, filename, is_main FROM ' . $photos_table . '
                    WHERE ' . $db->sql_in_set('catalog_id', $ids) . '
                    ORDER BY is_main DESC, photo_id ASC';
            $res = $db->sql_query($sql);
            while ($p = $db->sql_fetchrow($res)) {
                $cid = (int) $p['catalog_id'];
                if ((int) $p['is_main'] === 1 && !isset($main_photos[$cid])) {
                    $main_photos[$cid] = $p['filename'];
                }
                if (!isset($all_photos[$cid])) { $all_photos[$cid] = []; }
                $all_photos[$cid][] = $p['filename'];
            }
            $db->sql_freeresult($res);
        }

        // Mes notations (si table existe)
        $my_ratings = [];
        $ratings_table = isset($tables['ratings']) ? $tables['ratings'] : '';
        if ($ratings_table && !empty($rows))
        {
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($ratings_table) . "'");
            if ($db->sql_fetchrow($check))
            {
                $db->sql_freeresult($check);
                $ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
                $sql = 'SELECT catalog_id, rating FROM ' . $ratings_table . ' WHERE user_id = ' . $uid . ' AND ' . $db->sql_in_set('catalog_id', $ids);
                $res = $db->sql_query($sql);
                while ($r = $db->sql_fetchrow($res)) { $my_ratings[(int) $r['catalog_id']] = (int) $r['rating']; }
                $db->sql_freeresult($res);
            }
            else { $db->sql_freeresult($check); }
        }

        // Charger les commentaires validés et leurs auteurs
        $comments_by_cage = [];
        if ($ratings_table && !empty($rows))
        {
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($ratings_table) . "'");
            if ($db->sql_fetchrow($check))
            {
                $db->sql_freeresult($check);
                $where_validated = '';
                try {
                    $t = $db->sql_query('SELECT is_validated FROM ' . $ratings_table . ' LIMIT 1');
                    $db->sql_freeresult($t);
                    $where_validated = ' AND r.is_validated = 1';
                } catch (\Exception $e) {}

                $ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
                $sql = 'SELECT r.catalog_id, r.rating, r.comment, r.user_id, r.created_at,
                               u.username, u.user_colour
                        FROM ' . $ratings_table . ' r
                        LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = r.user_id
                        WHERE r.comment <> ''" . $where_validated . '
                          AND ' . $db->sql_in_set('r.catalog_id', $ids) . '
                        ORDER BY r.created_at DESC';
                $res = $db->sql_query($sql);
                $commenter_ids = [];
                while ($cr = $db->sql_fetchrow($res)) {
                    $catid = (int) $cr['catalog_id'];
                    if (!isset($comments_by_cage[$catid])) { $comments_by_cage[$catid] = []; }
                    $comments_by_cage[$catid][] = $cr;
                    if ((int) $cr['user_id'] > 0) { $commenter_ids[(int) $cr['user_id']] = true; }
                }
                $db->sql_freeresult($res);

                // Compléter $proposer_locked avec les commentateurs verrouillés
                if (!empty($commenter_ids))
                {
                    try {
                        $users_ct_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_users');
                        $sql_l = 'SELECT user_id FROM ' . $users_ct_table . " WHERE chastity_status = 'locked' AND " . $db->sql_in_set('user_id', array_keys($commenter_ids));
                        $res = $db->sql_query($sql_l);
                        while ($lrow = $db->sql_fetchrow($res)) { $proposer_locked[(int) $lrow['user_id']] = true; }
                        $db->sql_freeresult($res);
                    } catch (\Exception $e) {}
                }
            }
            else { $db->sql_freeresult($check); }
        }

        foreach ($rows as $row)
        {
            $cid = (int) $row['catalog_id'];
            $mkey = $row['cage_material'];
            $template->assign_block_vars('catalog', [
                'ID'            => $cid,
                'NAME'          => $row['cage_name'],
                'BRAND'         => $row['cage_brand'],
                'MATERIAL'      => isset($materials_map[$mkey]) ? $materials_map[$mkey] : $mkey,
                'TYPE'          => $row['cage_type'],
                'DESCRIPTION'   => $row['cage_description'],
                'MANUFACTURER'  => $row['manufacturer_name'] ?: '',
                'USAGE_COUNT'   => (int) $row['usage_count'],
                'AVG_RATING'    => isset($row['avg_rating']) ? (float) $row['avg_rating'] : 0,
                'RATING_COUNT'  => isset($row['rating_count']) ? (int) $row['rating_count'] : 0,
                'MY_RATING'     => isset($my_ratings[$cid]) ? $my_ratings[$cid] : 0,
                'MAIN_PHOTO'    => isset($main_photos[$cid]) ? $main_photos[$cid] : '',
                'PHOTO_COUNT'   => isset($all_photos[$cid]) ? count($all_photos[$cid]) : 0,
                'IN_COLLECTION' => in_array($cid, $my_catalog_ids),
                'ADDED_BY'         => $row['added_by_user_id'] ? get_username_string('full', (int) $row['added_by_user_id'], $row['added_by_username'], $row['added_by_colour']) : '',
                'ADDED_BY_LOCKED'  => isset($proposer_locked[(int) $row['added_by_user_id']]),
                'COMMENT_COUNT' => isset($comments_by_cage[$cid]) ? count($comments_by_cage[$cid]) : 0,
            ]);

            // Sous-bloc photos pour lightbox
            if (isset($all_photos[$cid])) {
                foreach ($all_photos[$cid] as $filename) {
                    $template->assign_block_vars('catalog.photos', [
                        'FILENAME' => $filename,
                    ]);
                }
            }
            // Sous-bloc commentaires
            if (isset($comments_by_cage[$cid])) {
                foreach ($comments_by_cage[$cid] as $cm) {
                    $template->assign_block_vars('catalog.comments', [
                        'AUTHOR'        => get_username_string('full', (int) $cm['user_id'], $cm['username'] ?: '?', $cm['user_colour'] ?: ''),
                        'AUTHOR_LOCKED' => isset($proposer_locked[(int) $cm['user_id']]),
                        'RATING'        => (int) $cm['rating'],
                        'COMMENT'       => $cm['comment'],
                        'DATE'          => date('d/m/Y', (int) $cm['created_at']),
                    ]);
                }
            }
        }

        // Listes pour filtres
        $res = $db->sql_query('SELECT DISTINCT cage_brand FROM ' . $catalog_table . " WHERE cage_brand <> '' ORDER BY cage_brand ASC");
        while ($r = $db->sql_fetchrow($res)) { $template->assign_block_vars('brands', ['NAME' => $r['cage_brand']]); }
        $db->sql_freeresult($res);

        $res = $db->sql_query('SELECT DISTINCT cage_material FROM ' . $catalog_table . " WHERE cage_material <> '' ORDER BY cage_material ASC");
        while ($r = $db->sql_fetchrow($res)) { $template->assign_block_vars('materials', ['NAME' => $r['cage_material']]); }
        $db->sql_freeresult($res);

        // Liste des fabricants pour le formulaire de proposition
        $res = $db->sql_query('SELECT manufacturer_id, name FROM ' . $mfr_table . ' ORDER BY name ASC');
        while ($m = $db->sql_fetchrow($res)) {
            $template->assign_block_vars('propose_mfrs', [
                'ID'   => (int) $m['manufacturer_id'],
                'NAME' => $m['name'],
            ]);
        }
        $db->sql_freeresult($res);

        // Liste des matériaux (depuis BDD si table existe)
        $materials_table = isset($tables['materials']) ? $tables['materials'] : '';
        if ($materials_table) {
            $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($materials_table) . "'");
            if ($db->sql_fetchrow($check)) {
                $db->sql_freeresult($check);
                $res = $db->sql_query('SELECT material_key, material_name FROM ' . $materials_table . ' WHERE is_validated = 1 ORDER BY material_name ASC');
                while ($m = $db->sql_fetchrow($res)) {
                    $template->assign_block_vars('propose_materials', [
                        'KEY'  => $m['material_key'],
                        'NAME' => $m['material_name'],
                    ]);
                }
                $db->sql_freeresult($res);
            } else { $db->sql_freeresult($check); }
        }

        $template->assign_vars([
            'U_ACTION'        => $this->u_action,
            'BOARD_URL'       => generate_board_url() . '/',
            'FILTER_BRAND'    => $filter_brand,
            'FILTER_MATERIAL' => $filter_material,
        ]);
    }

    /**
     * Charge la map material_key => material_name pour résoudre l'affichage.
     */
    private function load_materials_map($db, $materials_table)
    {
        $map = [];
        if (!$materials_table) { return $map; }
        $check = $db->sql_query("SHOW TABLES LIKE '" . $db->sql_escape($materials_table) . "'");
        if ($db->sql_fetchrow($check)) {
            $db->sql_freeresult($check);
            $res = $db->sql_query('SELECT material_key, material_name FROM ' . $materials_table);
            while ($r = $db->sql_fetchrow($res)) {
                $map[$r['material_key']] = $r['material_name'];
            }
            $db->sql_freeresult($res);
        } else {
            $db->sql_freeresult($check);
        }
        return $map;
    }

    /**
     * Redimensionne une image utilisateur (proposition de cage).
     */
    private function resize_user_image($filepath, $ext, $max_w, $max_h)
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
        } else { return; }
        if (!$src) { return; }
        $dst = imagecreatetruecolor($new_w, $new_h);
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transp = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transp);
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
     * Page UCP « Mon Keyholder » — côté sub : désigner / changer / retirer son KH
     */
    private function my_keyholder_mode($user, $template, $request, $db, $kh_table, $config)
    {
        $user_id = (int) $user->data['user_id'];

        // ─── Actions ────────────────────────────────────────────────
        if ($request->is_set_post('request_kh')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_username = trim($request->variable('kh_username', '', true));
            if ($kh_username === '') { trigger_error($user->lang['CHASTITY_KH_USER_NOT_FOUND'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>'); }

            // Trouver le user_id du KH
            $sql = 'SELECT user_id, username FROM ' . USERS_TABLE . " WHERE username_clean = '" . $db->sql_escape(utf8_clean_string($kh_username)) . "' AND user_type <> " . USER_IGNORE;
            $r = $db->sql_query($sql);
            $kh_row = $db->sql_fetchrow($r);
            $db->sql_freeresult($r);
            if (!$kh_row) { trigger_error($user->lang['CHASTITY_KH_USER_NOT_FOUND'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>'); }
            $kh_uid = (int) $kh_row['user_id'];
            if ($kh_uid === $user_id) { trigger_error($user->lang['CHASTITY_KH_CANNOT_SELF'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>'); }

            // Vérifier qu'il n'y a pas déjà une demande pending ou une relation active
            $sql = 'SELECT kh_id FROM ' . $kh_table . " WHERE sub_user_id = $user_id AND status IN ('pending', 'active')";
            $r = $db->sql_query($sql);
            if ($db->sql_fetchrow($r)) {
                $db->sql_freeresult($r);
                trigger_error($user->lang['CHASTITY_KH_ALREADY_HAS_REQUEST'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
            }
            $db->sql_freeresult($r);

            // Créer la demande — toutes les colonnes pour éviter MySQL strict mode
            $sql_ary = [
                'sub_user_id' => $user_id,
                'kh_user_id'  => $kh_uid,
                'status'      => 'pending',
                'created_at'  => time(),
                'accepted_at' => 0,
                'ended_at'    => 0,
                'ended_by'    => 0,
                'end_reason'  => '',
                'notes'       => '',
            ];
            $db->sql_query('INSERT INTO ' . $kh_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));

            // Envoyer un MP au KH
            $this->send_kh_request_pm($db, $config, $user, $kh_uid, $kh_row['username']);

            trigger_error($user->lang['CHASTITY_KH_REQUEST_SENT'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // Le sub rompt sa relation (active ou pending)
        if ($request->is_set_post('end_kh')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                // Vérifier que c'est bien sa relation
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND sub_user_id = $user_id AND status IN ('pending', 'active')";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'ended', ended_at = " . time() . ", ended_by = $user_id WHERE kh_id = $kh_id");
                    // Suspendre automatiquement tout contrat CTR actif avec
                    // cette Keyholder, la relation étant rompue.
                    $this->suspend_contracts_on_kh_end($db, $user_id, (int) $row['kh_user_id']);
                    // Notif MP au KH
                    $this->send_kh_ended_pm($db, $config, $user, (int) $row['kh_user_id'], true);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_RELATION_ENDED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // Le sub accepte une invitation venant du KH
        if ($request->is_set_post('accept_kh_invite')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND sub_user_id = $user_id AND status = 'pending' AND end_reason = 'invited_by_kh'";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'active', accepted_at = " . time() . ", end_reason = '' WHERE kh_id = $kh_id");
                    // Notif MP au KH : son invitation a été acceptée
                    $this->send_kh_response_pm($db, $config, $user, (int) $row['kh_user_id'], true);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_REQUEST_ACCEPTED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // Le sub refuse une invitation venant du KH
        if ($request->is_set_post('refuse_kh_invite')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND sub_user_id = $user_id AND status = 'pending' AND end_reason = 'invited_by_kh'";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'refused', ended_at = " . time() . ", ended_by = $user_id WHERE kh_id = $kh_id");
                    $this->send_kh_response_pm($db, $config, $user, (int) $row['kh_user_id'], false);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_REQUEST_REFUSED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // ─── Affichage ──────────────────────────────────────────────
        // Relation active ou pending
        $sql = 'SELECT k.*, u.username, u.user_colour FROM ' . $kh_table . ' k LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.kh_user_id WHERE k.sub_user_id = $user_id AND k.status IN ('pending', 'active') ORDER BY k.created_at DESC LIMIT 1";
        $r = $db->sql_query($sql);
        $current = $db->sql_fetchrow($r);
        $db->sql_freeresult($r);

        if ($current) {
            $invited_by_kh = ($current['status'] === 'pending' && $current['end_reason'] === 'invited_by_kh');
            $template->assign_vars([
                'KH_CURRENT'        => true,
                'KH_USERNAME'       => $current['username'],
                'KH_USER_COLOUR'    => $current['user_colour'],
                'KH_USER_ID'        => (int) $current['kh_user_id'],
                'KH_ID'             => (int) $current['kh_id'],
                'KH_STATUS'         => $current['status'],
                'KH_IS_PENDING'     => ($current['status'] === 'pending'),
                'KH_IS_ACTIVE'      => ($current['status'] === 'active'),
                'KH_INVITED_BY_KH'  => $invited_by_kh,
                'KH_CREATED_AT'     => date('d/m/Y H:i', (int) $current['created_at']),
                'KH_ACCEPTED_AT'    => $current['accepted_at'] ? date('d/m/Y H:i', (int) $current['accepted_at']) : '',
            ]);
        } else {
            $template->assign_var('KH_CURRENT', false);
        }

        // Historique des KH passés (ended ou refused)
        $sql = 'SELECT k.*, u.username, u.user_colour FROM ' . $kh_table . ' k LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.kh_user_id WHERE k.sub_user_id = $user_id AND k.status IN ('ended', 'refused') ORDER BY k.created_at DESC";
        $r = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($r)) {
            $template->assign_block_vars('kh_history', [
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
                'USER_ID'     => (int) $row['kh_user_id'],
                'STATUS'      => $row['status'],
                'IS_REFUSED'  => ($row['status'] === 'refused'),
                'CREATED_AT'  => date('d/m/Y', (int) $row['created_at']),
                'ACCEPTED_AT' => $row['accepted_at'] ? date('d/m/Y', (int) $row['accepted_at']) : '—',
                'ENDED_AT'    => $row['ended_at']    ? date('d/m/Y', (int) $row['ended_at'])    : '—',
                'END_BY_SUB'  => ((int) $row['ended_by'] === $user_id),
            ]);
        }
        $db->sql_freeresult($r);

        // ─── Liste des membres pour la liste déroulante ──────────────
        // Tous les utilisateurs actifs sauf soi-même
        $sql = 'SELECT user_id, username, user_colour FROM ' . USERS_TABLE
            . ' WHERE user_type <> ' . USER_IGNORE
            . ' AND user_id <> ' . $user_id
            . ' AND user_id > 1'
            . " ORDER BY username_clean ASC";
        $r = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($r)) {
            $template->assign_block_vars('member_list', [
                'USER_ID'     => (int) $row['user_id'],
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
            ]);
        }
        $db->sql_freeresult($r);

        $template->assign_vars([
            'U_ACTION' => $this->u_action,
        ]);
    }

    /**
     * Page UCP « Mes soumis » — côté KH : voir/accepter/refuser/rompre
     */
    private function my_subs_mode($user, $template, $request, $db, $kh_table, $cu_table, $cache_table, $config)
    {
        $user_id = (int) $user->data['user_id'];

        // ─── Actions ────────────────────────────────────────────────
        if ($request->is_set_post('accept_sub')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND kh_user_id = $user_id AND status = 'pending'";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'active', accepted_at = " . time() . " WHERE kh_id = $kh_id");
                    $this->send_kh_response_pm($db, $config, $user, (int) $row['sub_user_id'], true);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_REQUEST_ACCEPTED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        if ($request->is_set_post('refuse_sub')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND kh_user_id = $user_id AND status = 'pending'";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'refused', ended_at = " . time() . ", ended_by = $user_id WHERE kh_id = $kh_id");
                    $this->send_kh_response_pm($db, $config, $user, (int) $row['sub_user_id'], false);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_REQUEST_REFUSED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        if ($request->is_set_post('end_sub')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $kh_id = (int) $request->variable('kh_id', 0);
            if ($kh_id > 0) {
                $sql = 'SELECT * FROM ' . $kh_table . " WHERE kh_id = $kh_id AND kh_user_id = $user_id AND status = 'active'";
                $r = $db->sql_query($sql);
                $row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($row) {
                    $db->sql_query('UPDATE ' . $kh_table . " SET status = 'ended', ended_at = " . time() . ", ended_by = $user_id WHERE kh_id = $kh_id");
                    // Suspendre automatiquement tout contrat CTR actif avec
                    // cet encagé, la relation étant rompue.
                    $this->suspend_contracts_on_kh_end($db, (int) $row['sub_user_id'], $user_id);
                    $this->send_kh_ended_pm($db, $config, $user, (int) $row['sub_user_id'], false);
                }
            }
            trigger_error($user->lang['CHASTITY_KH_RELATION_ENDED'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // Le KH invite un ou plusieurs subs
        if ($request->is_set_post('invite_subs')) {
            if (!check_form_key('ucp_chastity')) { trigger_error($user->lang['FORM_INVALID']); }
            $sub_ids = $request->variable('sub_user_ids', [0]);
            $sub_ids = array_unique(array_filter(array_map('intval', $sub_ids)));

            if (empty($sub_ids)) {
                trigger_error($user->lang['CHASTITY_KH_INVITE_NO_SELECTION'] . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
            }

            $invited_count = 0;
            $skipped = [];
            foreach ($sub_ids as $sid) {
                if ($sid === $user_id) { continue; }

                // Vérifier que le sub n'a pas déjà un KH actif ou une demande en cours
                $sql = 'SELECT k.kh_id, k.status FROM ' . $kh_table . " k
                        WHERE k.sub_user_id = $sid AND k.status IN ('pending', 'active') LIMIT 1";
                $r = $db->sql_query($sql);
                $existing = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if ($existing) {
                    // Récupérer le pseudo pour info
                    $sql = 'SELECT username FROM ' . USERS_TABLE . " WHERE user_id = $sid";
                    $r = $db->sql_query($sql);
                    $sub_row = $db->sql_fetchrow($r);
                    $db->sql_freeresult($r);
                    if ($sub_row) { $skipped[] = $sub_row['username']; }
                    continue;
                }

                // Récupérer le pseudo du sub
                $sql = 'SELECT username FROM ' . USERS_TABLE . " WHERE user_id = $sid AND user_type <> " . USER_IGNORE;
                $r = $db->sql_query($sql);
                $sub_row = $db->sql_fetchrow($r);
                $db->sql_freeresult($r);
                if (!$sub_row) { continue; }

                // Créer la demande (sens KH → sub)
                $sql_ary = [
                    'sub_user_id'  => $sid,
                    'kh_user_id'   => $user_id,
                    'status'       => 'pending',
                    'created_at'   => time(),
                    'accepted_at'  => 0,
                    'ended_at'     => 0,
                    'ended_by'     => 0,
                    'end_reason'   => 'invited_by_kh', // marqueur du sens d'invitation
                    'notes'        => '',
                ];
                $db->sql_query('INSERT INTO ' . $kh_table . ' ' . $db->sql_build_array('INSERT', $sql_ary));

                // Envoyer un MP au sub
                $this->send_kh_invite_pm($db, $config, $user, $sid, $sub_row['username']);
                $invited_count++;
            }

            $msg = sprintf($user->lang['CHASTITY_KH_INVITES_SENT'], $invited_count);
            if (!empty($skipped)) {
                $msg .= '<br><br>' . sprintf($user->lang['CHASTITY_KH_INVITES_SKIPPED'], implode(', ', $skipped));
            }
            trigger_error($msg . '<br><br><a href="' . $this->u_action . '">' . $user->lang['BACK_TO_PREV'] . '</a>');
        }

        // ─── Affichage ──────────────────────────────────────────────
        // Demandes en attente reçues du sub (pas mes propres invitations)
        $sql = 'SELECT k.*, u.username, u.user_colour FROM ' . $kh_table . ' k LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.sub_user_id WHERE k.kh_user_id = $user_id AND k.status = 'pending' AND k.end_reason <> 'invited_by_kh' ORDER BY k.created_at ASC";
        $r = $db->sql_query($sql);
        $pending_count = 0;
        while ($row = $db->sql_fetchrow($r)) {
            $pending_count++;
            $template->assign_block_vars('pending_subs', [
                'KH_ID'       => (int) $row['kh_id'],
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
                'USER_ID'     => (int) $row['sub_user_id'],
                'CREATED_AT'  => date('d/m/Y H:i', (int) $row['created_at']),
            ]);
        }
        $db->sql_freeresult($r);

        // Soumis actifs avec leur statut chasteté
        $sql = 'SELECT k.*, u.username, u.user_colour, cu.chastity_status, cc.days_current_period
                FROM ' . $kh_table . ' k
                LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.sub_user_id
                LEFT JOIN " . $cu_table . " cu ON cu.user_id = k.sub_user_id
                LEFT JOIN " . $cache_table . " cc ON cc.user_id = k.sub_user_id
                WHERE k.kh_user_id = $user_id AND k.status = 'active'
                ORDER BY k.accepted_at DESC";
        $r = $db->sql_query($sql);
        $active_count = 0;
        $locked_count = 0;
        while ($row = $db->sql_fetchrow($r)) {
            $active_count++;
            $is_locked = ($row['chastity_status'] === 'locked');
            if ($is_locked) { $locked_count++; }
            $template->assign_block_vars('active_subs', [
                'KH_ID'        => (int) $row['kh_id'],
                'USERNAME'     => $row['username'],
                'USER_COLOUR'  => $row['user_colour'],
                'USER_ID'      => (int) $row['sub_user_id'],
                'ACCEPTED_AT'  => date('d/m/Y', (int) $row['accepted_at']),
                'IS_LOCKED'    => $is_locked,
                'DAYS_CURRENT' => (int) ($row['days_current_period'] ?? 0),
            ]);
        }
        $db->sql_freeresult($r);

        // Historique
        $sql = 'SELECT k.*, u.username, u.user_colour FROM ' . $kh_table . ' k LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.sub_user_id WHERE k.kh_user_id = $user_id AND k.status IN ('ended', 'refused') ORDER BY k.created_at DESC";
        $r = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($r)) {
            $template->assign_block_vars('subs_history', [
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
                'USER_ID'     => (int) $row['sub_user_id'],
                'STATUS'      => $row['status'],
                'IS_REFUSED'  => ($row['status'] === 'refused'),
                'CREATED_AT'  => date('d/m/Y', (int) $row['created_at']),
                'ACCEPTED_AT' => $row['accepted_at'] ? date('d/m/Y', (int) $row['accepted_at']) : '—',
                'ENDED_AT'    => $row['ended_at']    ? date('d/m/Y', (int) $row['ended_at'])    : '—',
                'END_BY_KH'   => ((int) $row['ended_by'] === $user_id),
            ]);
        }
        $db->sql_freeresult($r);

        // Mes invitations envoyées en attente (KH → sub, le sub n'a pas encore répondu)
        $sql = 'SELECT k.*, u.username, u.user_colour FROM ' . $kh_table . ' k LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = k.sub_user_id WHERE k.kh_user_id = $user_id AND k.status = 'pending' AND k.end_reason = 'invited_by_kh' ORDER BY k.created_at ASC";
        $r = $db->sql_query($sql);
        $invites_sent_count = 0;
        while ($row = $db->sql_fetchrow($r)) {
            $invites_sent_count++;
            $template->assign_block_vars('invites_sent', [
                'KH_ID'       => (int) $row['kh_id'],
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
                'USER_ID'     => (int) $row['sub_user_id'],
                'CREATED_AT'  => date('d/m/Y H:i', (int) $row['created_at']),
            ]);
        }
        $db->sql_freeresult($r);

        // Liste des membres invitables (pas moi, pas ceux qui ont déjà un KH actif ou pending)
        $sql = 'SELECT DISTINCT sub_user_id FROM ' . $kh_table . " WHERE status IN ('pending', 'active')";
        $r = $db->sql_query($sql);
        $unavailable = [];
        while ($row = $db->sql_fetchrow($r)) { $unavailable[(int) $row['sub_user_id']] = true; }
        $db->sql_freeresult($r);

        $sql = 'SELECT user_id, username, user_colour FROM ' . USERS_TABLE
            . ' WHERE user_type <> ' . USER_IGNORE
            . ' AND user_id <> ' . $user_id
            . ' AND user_id > 1'
            . " ORDER BY username_clean ASC";
        $r = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($r)) {
            $uid = (int) $row['user_id'];
            if (isset($unavailable[$uid])) { continue; }
            $template->assign_block_vars('invitable_members', [
                'USER_ID'     => $uid,
                'USERNAME'    => $row['username'],
                'USER_COLOUR' => $row['user_colour'],
            ]);
        }
        $db->sql_freeresult($r);

        $template->assign_vars([
            'U_ACTION'            => $this->u_action,
            'PENDING_COUNT'       => $pending_count,
            'ACTIVE_COUNT'        => $active_count,
            'LOCKED_COUNT'        => $locked_count,
            'INVITES_SENT_COUNT'  => $invites_sent_count,
        ]);
    }

    /**
     * Envoie un MP au KH quand un sub fait une demande
     */
    private function send_kh_request_pm($db, $config, $sender_user, $kh_uid, $kh_username)
    {
        if (!class_exists('\messenger')) {
            global $phpbb_root_path, $phpEx;
            include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
        }

        $sender_id   = (int) $sender_user->data['user_id'];
        $sender_name = $sender_user->data['username'];

        $subject = '[Chastity Tracker] Demande Keyholder';
        $message = "Bonjour $kh_username,\n\n"
            . "Le membre $sender_name vous a désigné comme son Keyholder.\n\n"
            . "Vous pouvez accepter ou refuser cette demande depuis votre Panneau utilisateur :\n"
            . "→ Suivi de Chasteté → Mes soumis\n\n"
            . "Cette demande reste en attente tant que vous n'avez pas répondu.";

        $this->send_simple_pm($db, $config, $sender_id, $sender_name, $kh_uid, $subject, $message);
    }

    /**
     * Envoie un MP au sub quand le KH l'invite
     */
    private function send_kh_invite_pm($db, $config, $kh_user, $sub_uid, $sub_username)
    {
        $kh_id   = (int) $kh_user->data['user_id'];
        $kh_name = $kh_user->data['username'];

        $subject = '[Chastity Tracker] Invitation Keyholder';
        $message = "Bonjour $sub_username,\n\n"
            . "Le membre $kh_name souhaite devenir votre Keyholder.\n\n"
            . "Vous pouvez accepter ou refuser cette invitation depuis votre Panneau utilisateur :\n"
            . "→ Suivi de Chasteté → Mon Keyholder\n\n"
            . "Cette invitation reste en attente tant que vous n'avez pas répondu.";

        $this->send_simple_pm($db, $config, $kh_id, $kh_name, $sub_uid, $subject, $message);
    }

    /**
     * Envoie un MP au sub quand le KH a répondu (accepté ou refusé)
     */
    private function send_kh_response_pm($db, $config, $kh_user, $sub_uid, $accepted)
    {
        $kh_id   = (int) $kh_user->data['user_id'];
        $kh_name = $kh_user->data['username'];

        if ($accepted) {
            $subject = '[Chastity Tracker] Votre Keyholder a accepté';
            $message = "Votre demande a été acceptée par $kh_name.\n\n"
                . "$kh_name est désormais officiellement votre Keyholder.\n\n"
                . "Vous pouvez gérer cette relation depuis :\n"
                . "→ Suivi de Chasteté → Mon Keyholder";
        } else {
            $subject = '[Chastity Tracker] Votre Keyholder a refusé';
            $message = "Votre demande de Keyholder n'a pas été acceptée par $kh_name.\n\n"
                . "Vous pouvez faire une nouvelle demande à un autre membre depuis :\n"
                . "→ Suivi de Chasteté → Mon Keyholder";
        }

        $this->send_simple_pm($db, $config, $kh_id, $kh_name, $sub_uid, $subject, $message);
    }

    /**
     * Envoie un MP quand une relation est rompue
     * $ended_by_sub : true si c'est le sub qui rompt, false si c'est le KH
     */
    private function send_kh_ended_pm($db, $config, $sender_user, $recipient_uid, $ended_by_sub)
    {
        $sender_id   = (int) $sender_user->data['user_id'];
        $sender_name = $sender_user->data['username'];

        if ($ended_by_sub) {
            $subject = '[Chastity Tracker] Fin de relation Keyholder';
            $message = "$sender_name a mis fin à votre relation Keyholder.\n\n"
                . "Vous n'êtes plus son Keyholder.\n\n"
                . "L'historique reste consultable dans votre Panneau utilisateur.";
        } else {
            $subject = '[Chastity Tracker] Votre Keyholder a mis fin à la relation';
            $message = "$sender_name a mis fin à votre relation Keyholder.\n\n"
                . "$sender_name n'est plus votre Keyholder.\n\n"
                . "Vous pouvez désigner un nouveau Keyholder depuis :\n"
                . "→ Suivi de Chasteté → Mon Keyholder";
        }

        $this->send_simple_pm($db, $config, $sender_id, $sender_name, $recipient_uid, $subject, $message);
    }

    /**
     * Quand une relation Keyholder se termine (par l'encagé ou par la
     * Keyholder), tout contrat CTR ACTIF entre ces deux mêmes personnes doit
     * être suspendu automatiquement — pas archivé, car la relation pourrait
     * reprendre : c'est un frein réversible, symétrique au mot de sécurité,
     * pas un arrêt définitif que seul l'encagé peut décider explicitement.
     */
    private function suspend_contracts_on_kh_end($db, $sub_user_id, $kh_user_id)
    {
        $contracts_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_contracts');
        $res = $db->sql_query('SELECT contract_id FROM ' . $contracts_table . "
            WHERE encage_user_id = " . (int) $sub_user_id . '
              AND kh_user_id = ' . (int) $kh_user_id . "
              AND status = 'active'");
        while ($row = $db->sql_fetchrow($res))
        {
            $db->sql_query('UPDATE ' . $contracts_table . "
                SET status = 'suspended',
                    safeword_suspended_by = 0,
                    safeword_suspended_time = " . time() . ",
                    suspended_kh_relation_end = 1,
                    updated_time = " . time() . '
                WHERE contract_id = ' . (int) $row['contract_id']);
        }
        $db->sql_freeresult($res);
    }

    /**
     * Helper interne pour envoyer un MP simple
     */
    private function send_simple_pm($db, $config, $from_id, $from_name, $to_id, $subject, $message_text)
    {
        global $phpbb_root_path, $phpEx;
        if (!function_exists('submit_pm')) {
            include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
        }

        $uid = $bitfield = $options = '';
        $bbcode_uid = substr(md5(uniqid()), 0, 8);
        $bbcode_bitfield = 'AQ==';

        $pm_data = [
            'from_user_id'       => $from_id,
            'from_user_ip'       => '127.0.0.1',
            'from_username'      => $from_name,
            'enable_sig'         => false,
            'enable_bbcode'      => true,
            'enable_smilies'     => true,
            'enable_urls'        => true,
            'icon_id'            => 0,
            'bbcode_bitfield'    => $bbcode_bitfield,
            'bbcode_uid'         => $bbcode_uid,
            'message'            => $message_text,
            'address_list'       => ['u' => [$to_id => 'to']],
        ];

        // submit_pm() est une fonction native phpBB non encadrée par défaut :
        // une erreur interne (quota de MP, destinataire invalide, etc.) peut
        // provoquer un trigger_error bloquant qui casserait toute la page.
        // On l'encadre pour que l'échec du MP n'empêche jamais le reste du
        // flux (email, redirection) de continuer.
        try {
            submit_pm('post', $subject, $pm_data, false);
        } catch (\Throwable $e) {
            if (function_exists('add_log')) {
                add_log('admin', 'LOG_CHASTITY_CONTRACT_EMAIL_FAILED', $e->getMessage());
            }
        }
    }

    /**
     * Envoie la notification de demande de validation du contrat à la
     * Keyholder : MP + email si elle est inscrite (avec un CODE à
     * communiquer à l'encagé), ou email seul si elle est externe (avec un
     * LIEN unique qu'elle clique elle-même pour valider directement).
     *
     * @param int|null   $kh_user_id    user_id de la KH inscrite, ou null
     * @param array|null $kh_external   ['name'=>.., 'email'=>..] si externe
     * @param string     $code_or_token le code (KH inscrite) ou le token (externe)
     * @param bool       $is_external
     */
    private function send_contract_validation_notice($db, $config, $encage_user, $kh_user_id, $kh_external, $encage_username, $code_or_token, $contract_id, $is_external)
    {
        global $phpbb_root_path, $phpEx;

        // generate_board_url() est utilisée dès la ligne suivante : s'assurer
        // qu'elle est chargée AVANT tout appel, pas seulement dans les blocs
        // plus bas qui s'exécutent après (source d'une erreur fatale sinon).
        if (!function_exists('generate_board_url')) {
            include_once($phpbb_root_path . 'includes/functions.' . $phpEx);
        }
        if (!class_exists('messenger')) {
            include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
        }

        $preview_url = generate_board_url() . '/ucp.' . $phpEx . '?i=-verturin-chastitytracker-ucp-main_module&mode=contract&view_contract=' . $contract_id . '&preview_contract=' . $contract_id;

        // Chemin du dossier language/xx/email/ DE L'EXTENSION elle-même.
        // IMPORTANT : quand $template_path est fourni à messenger::template(),
        // il est utilisé TEL QUEL (pas de "/xx/email" ajouté automatiquement,
        // contrairement au comportement par défaut) — le chemin doit donc
        // déjà être complet, langue et sous-dossier "email" inclus.
        // Sans ce chemin explicite, messenger ne cherche QUE dans le dossier
        // de langue du cœur phpBB, jamais dans les extensions (bug connu et
        // documenté du core : https://tracker.phpbb.com/browse/PHPBB3-13448).
        $ext_lang_base = $phpbb_root_path . 'ext/verturin/chastitytracker/language/';

        if (!$is_external)
        {
            // ── Keyholder inscrite : MP + email, avec le CODE ──
            $kh_res = $db->sql_query('SELECT username, user_email, user_lang FROM ' . USERS_TABLE . '
                WHERE user_id = ' . (int) $kh_user_id);
            $kh_row = $db->sql_fetchrow($kh_res);
            $db->sql_freeresult($kh_res);
            if (!$kh_row) { return; }

            $subject = sprintf($encage_user->lang['CHASTITY_CONTRACT_VALIDATION_SUBJECT'], $encage_username);
            $message = sprintf(
                $encage_user->lang['CHASTITY_CONTRACT_VALIDATION_PM_BODY'],
                $kh_row['username'], $encage_username, $preview_url, $encage_username, $code_or_token
            );
            $this->send_simple_pm($db, $config, (int) $encage_user->data['user_id'], $encage_username, (int) $kh_user_id, $subject, $message);

            if ($kh_row['user_email'] !== '')
            {
                try {
                    // Repli sur 'fr' si la langue de la KH n'est pas fournie
                    // par l'extension (seuls fr/ et en/ existent) — évite de
                    // pointer vers un dossier qui n'existe pas.
                    $kh_lang = in_array($kh_row['user_lang'], ['fr', 'en'], true) ? $kh_row['user_lang'] : 'fr';
                    $kh_template_path = $ext_lang_base . $kh_lang . '/email';
                    $messenger = new \messenger(false);
                    $messenger->template('chastity_contract_validation', $kh_lang, $kh_template_path);
                    $messenger->to($kh_row['user_email'], $kh_row['username']);
                    $messenger->assign_vars([
                        'KH_NAME'                  => $kh_row['username'],
                        'USERNAME'                 => $encage_username,
                        'SUBJECT'                  => $subject,
                        'U_CONTRACT_PREVIEW'       => $preview_url,
                        'VALIDATION_INSTRUCTIONS'  => sprintf($encage_user->lang['CHASTITY_CONTRACT_VALIDATION_EMAIL_CODE_INSTRUCTIONS'], $code_or_token),
                    ]);
                    $messenger->send(NOTIFY_EMAIL);
                } catch (\Throwable $e) {
                    if (function_exists('add_log')) {
                        add_log('admin', 'LOG_CHASTITY_CONTRACT_EMAIL_FAILED', $e->getMessage());
                    }
                }
            }
        }
        else
        {
            // ── Keyholder externe : email seul, avec un LIEN de validation ──
            if (!function_exists('generate_board_url')) {
                include_once($phpbb_root_path . 'includes/functions.' . $phpEx);
            }
            if (!class_exists('messenger')) {
                include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
            }

            $validate_url = generate_board_url() . '/app.' . $phpEx . '/chastity/contract-validate?token=' . $code_or_token;
            $subject = sprintf($encage_user->lang['CHASTITY_CONTRACT_VALIDATION_SUBJECT'], $encage_username);

            try {
                // Repli sur 'fr' si la langue par défaut du forum n'est pas
                // fournie par l'extension (seuls fr/ et en/ existent).
                $ext_lang = in_array($config['default_lang'] ?? '', ['fr', 'en'], true) ? $config['default_lang'] : 'fr';
                $ext_template_path = $ext_lang_base . $ext_lang . '/email';
                $messenger = new \messenger(false);
                $messenger->template('chastity_contract_validation', $ext_lang, $ext_template_path);
                $messenger->to($kh_external['email'], $kh_external['name']);
                $messenger->assign_vars([
                    'KH_NAME'                  => $kh_external['name'],
                    'USERNAME'                 => $encage_username,
                    'SUBJECT'                  => $subject,
                    'U_CONTRACT_PREVIEW'       => $preview_url,
                    'VALIDATION_INSTRUCTIONS'  => sprintf($encage_user->lang['CHASTITY_CONTRACT_VALIDATION_EMAIL_LINK_INSTRUCTIONS'], $validate_url),
                ]);
                $messenger->send(NOTIFY_EMAIL);
            } catch (\Throwable $e) {
                if (function_exists('add_log')) {
                    add_log('admin', 'LOG_CHASTITY_CONTRACT_EMAIL_FAILED', $e->getMessage());
                }
            }
        }
    }

}
