<?php
if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'ACL_CAT_CHASTITY' => 'Chastity Tracker',
    
    'ACL_U_CHASTITY_VIEW'    => 'Can view chastity status',
    'ACL_U_CHASTITY_MANAGE'  => 'Can manage own chastity',
    'ACL_U_CHASTITY_REFRESH'  => 'Can force refresh of their own cache and history',
    'ACL_M_CHASTITY_MODERATE' => 'Can moderate chastity periods',
    'ACL_U_CHASTITY_PREFS'  => 'Can manage own privacy preferences',
	
));
