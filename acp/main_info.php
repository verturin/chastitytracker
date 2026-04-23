<?php
/**
 * Chastity Tracker - ACP Module Info
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\verturin\chastitytracker\acp\main_module',
            'title'    => 'ACP_CHASTITY_TRACKER',
            'modes'    => [
                'settings'   => ['title' => 'ACP_CHASTITY_SETTINGS',   'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'statistics' => ['title' => 'ACP_CHASTITY_STATISTICS', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'rebuild'    => ['title' => 'ACP_CHASTITY_REBUILD', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
				'backup'     => ['title' => 'ACP_CHASTITY_BACKUP', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
			],
        ];
    }
}
