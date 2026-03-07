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
    
    'ACL_U_CHASTITY_VIEW'    => 'Peut voir le statut de chasteté',
    'ACL_U_CHASTITY_MANAGE'  => 'Peut gérer sa chasteté',
    'ACL_M_CHASTITY_MODERATE' => 'Peut modérer les périodes de chasteté',
));
