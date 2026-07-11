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
                'locktober'  => ['title' => 'ACP_CHASTITY_LOCKTOBER',   'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'rewards'    => ['title' => 'ACP_CHASTITY_REWARDS',     'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'statistics' => ['title' => 'ACP_CHASTITY_STATISTICS', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'rebuild' => ['title' => 'ACP_CHASTITY_REBUILD', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'delperiod' => ['title' => 'ACP_CHASTITY_DELPERIOD', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'cageexits' => ['title' => 'ACP_CHASTITY_CAGEEXITS', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'activities'   => ['title' => 'ACP_CHASTITY_ACTIVITIES',   'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'cage_catalog'     => ['title' => 'ACP_CHASTITY_CAGE_CATALOG',     'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'cage_comments'    => ['title' => 'ACP_CHASTITY_CAGE_COMMENTS',    'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'cage_manufacturers' => ['title' => 'ACP_CHASTITY_CAGE_MANUFACTURERS', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'cage_materials'   => ['title' => 'ACP_CHASTITY_CAGE_MATERIALS',   'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'keyholders'       => ['title' => 'ACP_CHASTITY_KEYHOLDERS',       'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'contract' => ['title' => 'ACP_CHASTITY_CONTRACT', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
                'backup' => ['title' => 'ACP_CHASTITY_BACKUP', 'auth' => 'ext_verturin/chastitytracker && acl_a_', 'cat' => ['ACP_CHASTITY_TRACKER']],
			],

        ];
    }
}
