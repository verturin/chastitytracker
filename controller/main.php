<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\controller;

class main
{
    protected $config;
    protected $helper;
    protected $template;
    protected $user;
    protected $db;
    protected $request;
    protected $auth;
    protected $phpbb_root_path;
    protected $php_ext;
    protected $periods_table;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\controller\helper $helper,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request $request,
        \phpbb\auth\auth $auth,
        $phpbb_root_path,
        $php_ext,
        $periods_table
    )
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->request = $request;
        $this->auth = $auth;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
        $this->periods_table = $periods_table;
    }

    public function handle($user_id = 0)
    {
        // Vérifier les permissions
        if (!$this->auth->acl_get('u_chastity_view'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        $user_id = ($user_id) ? (int) $user_id : (int) $this->user->data['user_id'];

        // Vérifier que l'utilisateur peut voir ce profil
        if ($user_id != $this->user->data['user_id'] && !$this->auth->acl_get('m_chastity_moderate'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        // Récupérer les périodes de l'utilisateur
        $sql = 'SELECT *
            FROM ' . $this->periods_table . '
            WHERE user_id = ' . (int) $user_id . '
            ORDER BY start_date DESC';
        $result = $this->db->sql_query($sql);
        $periods = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);

        // Calculer les statistiques
        $stats = $this->calculate_statistics($user_id, $periods);

        // Préparer les données pour le template
        foreach ($periods as $period)
        {
            $this->template->assign_block_vars('periods', array(
                'PERIOD_ID' => $period['period_id'],
                'START_DATE' => $this->user->format_date($period['start_date']),
                'END_DATE' => $period['end_date'] ? $this->user->format_date($period['end_date']) : '-',
                'STATUS' => $period['status'],
                'DAYS_COUNT' => $period['days_count'],
                'NOTES' => $period['notes'],
                'IS_ACTIVE' => $period['status'] == 'active',
            ));
        }

        $this->template->assign_vars(array(
            'CHASTITY_TOTAL_DAYS' => $stats['total_days'],
            'CHASTITY_YEAR_DAYS' => $stats['year_days'],
            'CHASTITY_CURRENT_DAYS' => $stats['current_days'],
            'CHASTITY_TOTAL_PERIODS' => $stats['total_periods'],
            'CHASTITY_STATUS' => $stats['status'],
            'U_ADD_PERIOD' => $this->helper->route('vendor_chastitytracker_add_period'),
        ));

        return $this->helper->render('chastity_calendar.html', 'Chastity Tracker');
    }

    public function add_period()
    {
        if (!$this->auth->acl_get('u_chastity_manage'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        add_form_key('chastity_add_period');

        if ($this->request->is_set_post('submit'))
        {
            if (!check_form_key('chastity_add_period'))
            {
                throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
            }

            $start_date = $this->request->variable('start_date', '');
            $notes = $this->request->variable('notes', '', true);

            // Vérifier qu'il n'y a pas déjà une période active
            $sql = 'SELECT COUNT(*) as active_count
                FROM ' . $this->periods_table . '
                WHERE user_id = ' . (int) $this->user->data['user_id'] . '
                    AND status = \'active\'';
            $result = $this->db->sql_query($sql);
            $active_count = (int) $this->db->sql_fetchfield('active_count');
            $this->db->sql_freeresult($result);

            if ($active_count > 0)
            {
                trigger_error('CHASTITY_ALREADY_ACTIVE');
            }

            // Convertir la date
            $start_timestamp = strtotime($start_date);
            if (!$start_timestamp)
            {
                trigger_error('CHASTITY_INVALID_DATE');
            }

            // Insérer la nouvelle période
            $sql_ary = array(
                'user_id' => (int) $this->user->data['user_id'],
                'start_date' => $start_timestamp,
                'end_date' => null,
                'status' => 'active',
                'days_count' => 0,
                'notes' => $notes,
                'created_time' => time(),
                'updated_time' => time(),
            );

            $sql = 'INSERT INTO ' . $this->periods_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
            $this->db->sql_query($sql);
            $period_id = $this->db->sql_nextid();

            // Mettre à jour le profil utilisateur
            $sql = 'UPDATE ' . USERS_TABLE . '
                SET chastity_status = \'locked\',
                    chastity_current_period_id = ' . (int) $period_id . '
                WHERE user_id = ' . (int) $this->user->data['user_id'];
            $this->db->sql_query($sql);

            trigger_error($this->user->lang['CHASTITY_PERIOD_ADDED'] . '<br /><br />' . 
                sprintf($this->user->lang['RETURN_PAGE'], '<a href="' . $this->helper->route('vendor_chastitytracker_controller') . '">', '</a>'));
        }

        $this->template->assign_vars(array(
            'S_ADD_PERIOD' => true,
            'U_ACTION' => $this->helper->route('vendor_chastitytracker_add_period'),
        ));

        return $this->helper->render('chastity_add_period.html', 'Add Chastity Period');
    }

    public function end_period($period_id)
    {
        if (!$this->auth->acl_get('u_chastity_manage'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        $period_id = (int) $period_id;

        // Vérifier que la période appartient à l'utilisateur
        $sql = 'SELECT *
            FROM ' . $this->periods_table . '
            WHERE period_id = ' . $period_id . '
                AND user_id = ' . (int) $this->user->data['user_id'] . '
                AND status = \'active\'';
        $result = $this->db->sql_query($sql);
        $period = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$period)
        {
            throw new \phpbb\exception\http_exception(404, 'CHASTITY_PERIOD_NOT_FOUND');
        }

        add_form_key('chastity_end_period');

        if ($this->request->is_set_post('confirm'))
        {
            if (!check_form_key('chastity_end_period'))
            {
                throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
            }

            $end_date = time();
            $days_count = floor(($end_date - $period['start_date']) / 86400);

            // Mettre à jour la période
            $sql = 'UPDATE ' . $this->periods_table . '
                SET end_date = ' . $end_date . ',
                    status = \'completed\',
                    days_count = ' . (int) $days_count . ',
                    updated_time = ' . time() . '
                WHERE period_id = ' . $period_id;
            $this->db->sql_query($sql);

            // Calculer le total de jours
            $sql = 'SELECT SUM(days_count) as total_days
                FROM ' . $this->periods_table . '
                WHERE user_id = ' . (int) $this->user->data['user_id'];
            $result = $this->db->sql_query($sql);
            $total_days = (int) $this->db->sql_fetchfield('total_days');
            $this->db->sql_freeresult($result);

            // Mettre à jour le profil utilisateur
            $sql = 'UPDATE ' . USERS_TABLE . '
                SET chastity_status = \'free\',
                    chastity_current_period_id = 0,
                    chastity_total_days = ' . $total_days . '
                WHERE user_id = ' . (int) $this->user->data['user_id'];
            $this->db->sql_query($sql);

            trigger_error($this->user->lang['CHASTITY_PERIOD_ENDED'] . '<br /><br />' . 
                sprintf($this->user->lang['RETURN_PAGE'], '<a href="' . $this->helper->route('vendor_chastitytracker_controller') . '">', '</a>'));
        }

        confirm_box(false, 'CHASTITY_END_PERIOD_CONFIRM', '', 'confirm_body.html');

        redirect($this->helper->route('vendor_chastitytracker_controller'));
    }

    protected function calculate_statistics($user_id, $periods)
    {
        $total_days = 0;
        $year_days = 0;
        $current_days = 0;
        $total_periods = count($periods);
        $status = 'free';
        $current_year = date('Y');

        foreach ($periods as $period)
        {
            $total_days += (int) $period['days_count'];

            // Compter les jours de l'année en cours
            if (date('Y', $period['start_date']) == $current_year)
            {
                $year_days += (int) $period['days_count'];
            }

            // Vérifier si c'est la période active
            if ($period['status'] == 'active')
            {
                $status = 'locked';
                $current_days = floor((time() - $period['start_date']) / 86400);
            }
        }

        return array(
            'total_days' => $total_days,
            'year_days' => $year_days,
            'current_days' => $current_days,
            'total_periods' => $total_periods,
            'status' => $status,
        );
    }
}
