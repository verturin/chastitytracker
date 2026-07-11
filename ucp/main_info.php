<?php
/**
 * Chastity Tracker - UCP Info
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\ucp;

class main_info
{
    function module()
    {
        return [
            'filename' => '\verturin\chastitytracker\ucp\main_module',
            'title'    => 'UCP_CHASTITY_TRACKER',
            'modes'    => [
                'calendar'   => ['title' => 'UCP_CHASTITY_CALENDAR',   'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'add_past'   => ['title' => 'UCP_CHASTITY_ADD_PAST',   'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_manage', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'statistics' => ['title' => 'UCP_CHASTITY_STATISTICS', 'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'locktober'  => ['title' => 'UCP_CHASTITY_LOCKTOBER',  'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'rewards'    => ['title' => 'UCP_CHASTITY_REWARDS',    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'chastprivacy'    => ['title' => 'UCP_CHASTITY_CHASTPRIVACY',    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_prefs', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'refresh' => ['title' => 'UCP_CHASTITY_REFRESH', 'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_refresh', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'yearview'   => ['title' => 'UCP_CHASTITY_YEARVIEW', 'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],				
                'cageexits' => ['title' => 'UCP_CHASTITY_CAGEEXITS', 'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'activities'   => ['title' => 'UCP_CHASTITY_ACTIVITIES',   'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'cage_collection'  => ['title' => 'UCP_CHASTITY_CAGE_COLLECTION', 'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'cage_catalog'     => ['title' => 'UCP_CHASTITY_CAGE_CATALOG',    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'my_keyholder'     => ['title' => 'UCP_CHASTITY_MY_KEYHOLDER',    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'my_subs'          => ['title' => 'UCP_CHASTITY_MY_SUBS',         'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'api_access'       => ['title' => 'UCP_CHASTITY_API_ACCESS',      'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_prefs', 'cat' => ['UCP_CHASTITY_TRACKER']],
                'contract'         => ['title' => 'UCP_CHASTITY_CONTRACT',        'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_contract', 'cat' => ['UCP_CHASTITY_TRACKER']],
            ],
        ];
    }
}
