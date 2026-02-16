<?php
/**
 *
 * Chastity Tracker Extension
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\acp;

class main_info
{
    function module()
    {
        return array(
            'filename' => '\verturin\chastitytracker\acp\main_module',
            'title' => 'ACP_CHASTITY_TRACKER',
            'modes' => array(
                'settings' => array(
                    'title' => 'ACP_CHASTITY_SETTINGS',
                    'auth' => 'ext_verturin/chastitytracker && acl_a_board',
                    'cat' => array('ACP_CHASTITY_TRACKER')
                ),
                'statistics' => array(
                    'title' => 'ACP_CHASTITY_STATISTICS',
                    'auth' => 'ext_verturin/chastitytracker && acl_a_board',
                    'cat' => array('ACP_CHASTITY_TRACKER')
                ),
            ),
        );
    }
}
