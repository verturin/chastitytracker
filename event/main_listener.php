<?php
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
    protected $chastity_users_table;
    protected $cache_table;
    
    public function __construct($config, $template, $user, $db, $auth, $periods_table, $chastity_users_table, $cache_table)
    {
        $this->config = $config;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->auth = $auth;
        $this->periods_table = $periods_table;
        $this->chastity_users_table = $chastity_users_table;
        $this->cache_table = $cache_table;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'              => 'load_language',
            'core.memberlist_view_profile' => 'display_chastity_status_profile',
            'core.permissions'             => 'add_permissions',
            'core.modify_username_string'  => 'add_badge',
            'core.viewtopic_modify_post_row' => 'set_post_row_var',
        ];
    }
    
    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        
        $lang_set_ext[] = [
            'ext_name' => 'verturin/chastitytracker',
            'lang_set' => 'common',
        ];
        
        $event['lang_set_ext'] = $lang_set_ext;
    }
    
    public function display_chastity_status_profile($event)
    {
        if (empty($this->config['chastity_enable']) || empty($this->config['chastity_profile_display']))
        {
            return;
        }
        
        if (!$this->auth->acl_get('u_chastity_view'))
        {
            return;
        }
        
        $member = $event['member'];
        
        if (empty($member['user_id']))
        {
            return;
        }
        
        $user_id = (int) $member['user_id'];
        
        $sql = 'SELECT cu.chastity_status, cu.chastity_current_period, cu.chastity_total_days, p.start_date
                FROM ' . $this->chastity_users_table . ' cu
                LEFT JOIN ' . $this->periods_table . ' p
                    ON p.period_id = cu.chastity_current_period
                WHERE cu.user_id = ' . $user_id;

        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return;
        }

        $locked = ($row['chastity_status'] === 'locked');
        $current_days = 0;

        if ($locked && $row['start_date'])
        {
            $current_days = (int) floor((time() - (int) $row['start_date']) / 86400);
        }

        $this->template->assign_vars([
            'CHASTITY_STATUS'       => $this->user->lang[$locked ? 'CHASTITY_STATUS_LOCKED' : 'CHASTITY_STATUS_FREE'],
            'CHASTITY_CURRENT_DAYS' => $current_days,
            'CHASTITY_TOTAL_DAYS'   => (int) $row['chastity_total_days'],
            'S_CHASTITY_LOCKED'     => $locked,
            'S_DISPLAY_CHASTITY'    => true,
        ]);
    }
    
    public function add_permissions($event)
    {
        $categories = $event['categories'];
        $permissions = $event['permissions'];
        
        // Ajouter catégorie
        $categories['chastity'] = 'ACL_CAT_CHASTITY';
        
        $permissions['u_chastity_view'] = [
            'lang' => 'ACL_U_CHASTITY_VIEW',
            'cat'  => 'chastity'
        ];
        
        $permissions['u_chastity_manage'] = [
            'lang' => 'ACL_U_CHASTITY_MANAGE',
            'cat'  => 'chastity'
        ];
        
        $permissions['m_chastity_moderate'] = [
            'lang' => 'ACL_M_CHASTITY_MODERATE',
            'cat'  => 'chastity'
        ];
        
        $event['categories'] = $categories;
        $event['permissions'] = $permissions;
    }
    
    public function add_badge($event)
    {
        if (empty($this->config['chastity_profile_display']))
        {
            return;
        }

        // Pages autorisées (viewtopic = lecture d'un sujet)

		// Desactivation pour fversion final 
        // $allowed_pages = ['viewtopic.php'];

        // if (!in_array($this->user->page['page_name'], $allowed_pages, true))
        //{
        //    return;
        //}

        //$ignored_modes = ['colour', 'username'];

        //if (in_array($event['mode'], $ignored_modes, true))
        //{
        //    return;
        //}

        //$event['username_string'] .= '<br><span class="okdisplay-badge"><br>OK c est la</span>';
    }
    
    public function set_post_row_var($event)
    {
        if (empty($this->config['chastity_profile_display']))
        {
            return;
        }

        $post_row = $event['post_row'];
        $user_id = (int) $event['row']['user_id'];
        
        // Lire table cache
        $sql = 'SELECT days_current_period, days_since_last_end 
                FROM ' . $this->cache_table . ' 
                WHERE user_id = ' . $user_id;
        $result = $this->db->sql_query($sql);
        $cache = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        
        if ($cache)
        {
            $days_current = (int) $cache['days_current_period'];
            $days_since = (int) $cache['days_since_last_end'];
            
            if ($days_current > 0)
            {
                // Verrouillé
                $post_row['CHASTITY_STATUS'] = 'locked';
                $post_row['CHASTITY_DAYS'] = $days_current;
            }
            else if ($days_since > 0)
            {
                // Libre depuis X jours
                $post_row['CHASTITY_STATUS'] = 'free';
                $post_row['CHASTITY_DAYS'] = $days_since;
            }
            else
            {
                // Pas de période
                $post_row['CHASTITY_STATUS'] = 'none';
            }
            
            $post_row['OKDISPLAY_BADGE'] = true;
        }
        
        $event['post_row'] = $post_row;
    }
}
