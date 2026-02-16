<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    protected $config;
    protected $template;
    protected $user;
    protected $db;
    protected $auth;
    protected $periods_table;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\auth\auth $auth,
        $periods_table
    )
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->auth = $auth;
        $this->periods_table = $periods_table;
    }

    static public function getSubscribedEvents()
    {
        return array(
            'core.viewtopic_modify_post_row' => 'display_chastity_status_postrow',
            'core.memberlist_view_profile' => 'display_chastity_status_profile',
            'core.permissions' => 'add_permissions',
        );
    }

    public function display_chastity_status_postrow($event)
    {
        if (!$this->config['chastity_profile_display'])
        {
            return;
        }

        if (!$this->auth->acl_get('u_chastity_view'))
        {
            return;
        }

        $post_row = $event['post_row'];
        $row = $event['row'];

        // Récupérer le statut de chasteté de l'utilisateur
        if (isset($row['user_id']))
        {
            $sql = 'SELECT chastity_status, chastity_current_period_id, chastity_total_days
                FROM ' . USERS_TABLE . '
                WHERE user_id = ' . (int) $row['user_id'];
            $result = $this->db->sql_query($sql);
            $user_data = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);

            if ($user_data && $user_data['chastity_status'] == 'locked')
            {
                // Récupérer les détails de la période active
                $sql = 'SELECT start_date
                    FROM ' . $this->periods_table . '
                    WHERE period_id = ' . (int) $user_data['chastity_current_period_id'];
                $result = $this->db->sql_query($sql);
                $period = $this->db->sql_fetchrow($result);
                $this->db->sql_freeresult($result);

                if ($period)
                {
                    $days_locked = floor((time() - $period['start_date']) / 86400);
                    $post_row['CHASTITY_STATUS'] = $this->user->lang['CHASTITY_STATUS_LOCKED'];
                    $post_row['CHASTITY_DAYS'] = $days_locked;
                    $post_row['S_CHASTITY_LOCKED'] = true;
                }
            }
            else if ($user_data)
            {
                $post_row['CHASTITY_STATUS'] = $this->user->lang['CHASTITY_STATUS_FREE'];
                $post_row['S_CHASTITY_LOCKED'] = false;
            }

            if ($user_data)
            {
                $post_row['CHASTITY_TOTAL_DAYS'] = (int) $user_data['chastity_total_days'];
            }
        }

        $event['post_row'] = $post_row;
    }

    public function display_chastity_status_profile($event)
    {
        if (!$this->config['chastity_profile_display'])
        {
            return;
        }

        if (!$this->auth->acl_get('u_chastity_view'))
        {
            return;
        }

        $member = $event['member'];

        // Récupérer le statut de chasteté
        if ($member['chastity_status'] == 'locked')
        {
            // Récupérer les détails de la période active
            $sql = 'SELECT start_date
                FROM ' . $this->periods_table . '
                WHERE period_id = ' . (int) $member['chastity_current_period_id'];
            $result = $this->db->sql_query($sql);
            $period = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);

            if ($period)
            {
                $days_locked = floor((time() - $period['start_date']) / 86400);
                
                $this->template->assign_vars(array(
                    'CHASTITY_STATUS' => $this->user->lang['CHASTITY_STATUS_LOCKED'],
                    'CHASTITY_LOCKED_SINCE' => $this->user->format_date($period['start_date']),
                    'CHASTITY_CURRENT_DAYS' => $days_locked,
                    'CHASTITY_TOTAL_DAYS' => (int) $member['chastity_total_days'],
                    'S_CHASTITY_LOCKED' => true,
                    'S_DISPLAY_CHASTITY' => true,
                ));
            }
        }
        else
        {
            $this->template->assign_vars(array(
                'CHASTITY_STATUS' => $this->user->lang['CHASTITY_STATUS_FREE'],
                'CHASTITY_TOTAL_DAYS' => (int) $member['chastity_total_days'],
                'S_CHASTITY_LOCKED' => false,
                'S_DISPLAY_CHASTITY' => true,
            ));
        }
    }

    public function add_permissions($event)
    {
        $permissions = $event['permissions'];
        $permissions['u_chastity_view'] = array('lang' => 'ACL_U_CHASTITY_VIEW', 'cat' => 'misc');
        $permissions['u_chastity_manage'] = array('lang' => 'ACL_U_CHASTITY_MANAGE', 'cat' => 'misc');
        $permissions['m_chastity_moderate'] = array('lang' => 'ACL_M_CHASTITY_MODERATE', 'cat' => 'misc');
        $event['permissions'] = $permissions;
    }
}
