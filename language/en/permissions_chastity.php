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
    'ACL_M_CHASTITY_MODERATE' => 'Can moderate chastity periods',
));
