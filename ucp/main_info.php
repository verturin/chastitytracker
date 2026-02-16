<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\ucp;

class main_info
{
    function module()
    {
        return array(
            'filename' => '\verturin\chastitytracker\ucp\main_module',
            'title' => 'UCP_CHASTITY_TRACKER',
            'modes' => array(
                'calendar' => array(
                    'title' => 'UCP_CHASTITY_CALENDAR',
                    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view',
                    'cat' => array('UCP_CHASTITY_TRACKER')
                ),
                'statistics' => array(
                    'title' => 'UCP_CHASTITY_STATISTICS',
                    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view',
                    'cat' => array('UCP_CHASTITY_TRACKER')
                ),
                'locktober' => array(
                    'title' => 'UCP_CHASTITY_LOCKTOBER',
                    'auth' => 'ext_verturin/chastitytracker && acl_u_chastity_view',
                    'cat' => array('UCP_CHASTITY_TRACKER')
                ),
            ),
        );
    }
}
