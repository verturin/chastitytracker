<?php
/**
 * Chastity Tracker - Controller
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\controller;

class main
{
    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\controller\helper */
    protected $helper;
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\user */
    protected $user;
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\auth\auth */
    protected $auth;
    /** @var string */
    protected $root_path;
    /** @var string */
    protected $php_ext;
    /** @var string */
    protected $periods_table;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\controller\helper $helper,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request $request,
        \phpbb\auth\auth $auth,
        string $root_path,
        string $php_ext,
        string $periods_table
    )
    {
        $this->config        = $config;
        $this->helper        = $helper;
        $this->template      = $template;
        $this->user          = $user;
        $this->db            = $db;
        $this->request       = $request;
        $this->auth          = $auth;
        $this->root_path     = $root_path;
        $this->php_ext       = $php_ext;
        $this->periods_table = $periods_table;
    }

    public function handle(): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->auth->acl_get('u_chastity_view'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        $this->user->add_lang_ext('verturin/chastitytracker', 'common');

        $sql = 'SELECT * FROM ' . $this->periods_table . '
                WHERE user_id = ' . (int) $this->user->data['user_id'] . '
                ORDER BY start_date DESC';
        $result  = $this->db->sql_query($sql);
        $periods = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);

        foreach ($periods as $period)
        {
            $is_active  = ($period['status'] === 'active');
            $days_count = $is_active
                ? (int) floor((time() - (int) $period['start_date']) / 86400)
                : (int) $period['days_count'];

            $this->template->assign_block_vars('periods', [
                'PERIOD_ID'  => $period['period_id'],
                'START_DATE' => $this->user->format_date((int) $period['start_date'], 'd/m/Y'),
                'END_DATE'   => ((int) $period['end_date'] > 0) ? $this->user->format_date((int) $period['end_date'], 'd/m/Y') : '-',
                'DAYS_COUNT' => $days_count,
                'STATUS'     => $this->user->lang('CHASTITY_STATUS_' . strtoupper($period['status'])),
                'NOTES'      => $period['notes'],
                'IS_ACTIVE'  => $is_active,
            ]);
        }

        $this->template->assign_vars([
            'U_ACTION' => $this->helper->route('verturin_chastitytracker_controller'),
        ]);

        return $this->helper->render('chastity_tracker.html', $this->user->lang('CHASTITY_TRACKER'));
    }
}
