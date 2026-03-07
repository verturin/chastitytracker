<?php
/**
 * Chastity Tracker - English language
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(

    // General
    'CHASTITY_TRACKER'          => 'Chastity Tracker',
    'CHASTITY_STATUS'           => 'Chastity Status',
    'CHASTITY_STATUS_FREE'      => 'Free',
    'CHASTITY_STATUS_LOCKED'    => 'Locked',
    'CHASTITY_STATUS_ACTIVE'    => 'Active',
    'CHASTITY_STATUS_COMPLETED' => 'Completed',

    // UCP - Menus
    'UCP_CHASTITY_TRACKER'    => 'Chastity Tracker',
    'UCP_CHASTITY_CALENDAR'   => 'Calendar',
    'UCP_CHASTITY_STATISTICS' => 'Statistics',
    'UCP_CHASTITY_LOCKTOBER'  => 'Locktober',
    'UCP_CHASTITY_ADD_PAST'   => 'Add past period',

    // UCP - Calendar
    'CHASTITY_CALENDAR'          => 'Chastity Calendar',
    'CHASTITY_ADD_PERIOD'        => 'Start a new period',
    'CHASTITY_ADD_PAST_PERIOD'   => 'Past period',
    'CHASTITY_END_PERIOD'        => 'End period',
    'CHASTITY_DELETE_PERIOD'     => 'Delete period',
    'CHASTITY_START_DATE'        => 'Start date',
    'CHASTITY_END_DATE'          => 'End date',
    'CHASTITY_DAYS'              => 'Days',
    'CHASTITY_NOTES'             => 'Notes',
    'CHASTITY_NO_PERIODS'        => 'No periods recorded yet.',
    'CHASTITY_CURRENT_PERIOD'    => 'Current period',
    'CHASTITY_CURRENT_DAYS'      => 'Current days locked',
    'CHASTITY_PERMANENT'         => 'Permanent (no end date)',
    'CHASTITY_PERMANENT_MODE'    => 'Permanent mode',
    'CHASTITY_TEMPORARY_MODE'    => 'Temporary mode',
    'CHASTITY_PERMANENT_EXPLAIN' => 'In permanent mode, there is no planned end date (but you can still stop the period manually)',
    'CHASTITY_VIEW_RULES'        => 'View rules',
    'CHASTITY_HIDE_RULES'        => 'Hide rules',

    // UCP - Add past period
    'UCP_CHASTITY_ADD_PAST_EXPLAIN'  => 'Add a past chastity period to your history. Statistics will be automatically recalculated.',
    'CHASTITY_PAST_PERIOD_ADDED'     => 'Past period added and statistics recalculated.',
    'CHASTITY_INVALID_DATE_RANGE'    => 'End date must be after start date.',

    // UCP - Statistics
    'CHASTITY_TOTAL_DAYS'     => 'Total days',
    'CHASTITY_TOTAL_PERIODS'  => 'Total periods',
    'CHASTITY_YEAR_DAYS'      => 'Days this year',
    'CHASTITY_LONGEST_PERIOD' => 'Longest period',
    'CHASTITY_AVERAGE_PERIOD' => 'Average duration',
    'CHASTITY_STATS_BY_YEAR'  => 'Statistics by year',
    'CHASTITY_STATS_BY_MONTH' => 'Statistics by month (current year)',
    'CHASTITY_YEAR'           => 'Year',
    'CHASTITY_MONTH'          => 'Month',
    'CHASTITY_PERIODS'        => 'Periods',

    // Rules
    'CHASTITY_RULES'                => 'Rules for this period',
    'CHASTITY_RULES_EXPLAIN'        => 'Set the rules for this chastity period',
    'CHASTITY_RULE_MASTURBATION'    => 'May masturbate',
    'CHASTITY_RULE_EJACULATION'     => 'May ejaculate',
    'CHASTITY_RULE_SLEEP_REMOVAL'   => 'May remove cage to sleep',
    'CHASTITY_RULE_PUBLIC_REMOVAL'  => 'May remove cage (locker rooms, nudist beaches...)',
    'CHASTITY_RULE_MEDICAL_REMOVAL' => 'May remove cage for medical emergencies',
    'CHASTITY_YES'                  => 'Allowed',
    'CHASTITY_NO'                   => 'Forbidden',

    // Locktober
    'CHASTITY_LOCKTOBER'                  => 'Locktober',
    'CHASTITY_LOCKTOBER_CHALLENGE'        => 'Locktober Challenge',
    'CHASTITY_LOCKTOBER_PARTICIPATE'      => 'Join Locktober',
    'CHASTITY_LOCKTOBER_EXPLAIN'          => 'Locktober is an annual chastity challenge for the entire month of October (31 days)',
    'CHASTITY_LOCKTOBER_START'            => 'Start Locktober',
    'CHASTITY_LOCKTOBER_ACTIVE'           => 'Locktober in progress',
    'CHASTITY_LOCKTOBER_COMPLETED'        => 'Locktober completed! 🎉',
    'CHASTITY_LOCKTOBER_FAILED'           => 'Locktober abandoned',
    'CHASTITY_LOCKTOBER_PARTICIPANTS'     => 'Locktober participants',
    'CHASTITY_LOCKTOBER_LEADERBOARD'      => 'Locktober Leaderboard',
    'CHASTITY_LOCKTOBER_DAY'              => 'Day %d/31',
    'CHASTITY_LOCKTOBER_WINNERS'          => 'Locktober Winners',
    'CHASTITY_LOCKTOBER_BADGE'            => 'Locktober Badge',
    'CHASTITY_LOCKTOBER_YEAR'             => 'Locktober %d',
    'CHASTITY_LOCKTOBER_JOIN_EXPLAIN'     => 'Join the Locktober challenge and try to stay chaste for all 31 days of October!',
    'CHASTITY_LOCKTOBER_WAIT'             => 'The Locktober challenge is only available in October.',
    'CHASTITY_LOCKTOBER_NEXT_YEAR'        => 'Come back in October to join the next challenge!',
    'CHASTITY_LOCKTOBER_COMPLETE_MESSAGE' => 'Congratulations! You have successfully completed the Locktober challenge!',
    'CHASTITY_LOCKTOBER_STARTED'          => 'You have joined the Locktober challenge! Good luck!',
    'CHASTITY_LOCKTOBER_NOT_OCTOBER'      => 'Locktober can only be started in October.',
    'CHASTITY_LOCKTOBER_DISABLED'         => 'Locktober is currently disabled.',

    // System messages
    'CHASTITY_PERIOD_ADDED'       => 'Chastity period started successfully.',
    'CHASTITY_PERIOD_ENDED'       => 'Chastity period ended successfully.',
    'CHASTITY_PERIOD_DELETED'     => 'Period deleted successfully.',
    'CHASTITY_ALREADY_ACTIVE'     => 'You already have an active chastity period.',
    'CHASTITY_INVALID_DATE'       => 'Invalid date. The date cannot be in the future.',
    'CHASTITY_PERIOD_NOT_FOUND'   => 'Period not found.',
    'CHASTITY_END_PERIOD_CONFIRM' => 'Are you sure you want to end this chastity period?',

    // Profile
    'CHASTITY_PROFILE_STATUS'       => 'Chastity Status',
    'CHASTITY_PROFILE_LOCKED_SINCE' => 'Locked since',
    'CHASTITY_PROFILE_TOTAL_DAYS'   => 'Total days in chastity',

    // ACP - Menus
    'ACP_CHASTITY_TRACKER'    => 'Chastity Tracker',
    'ACP_CHASTITY_SETTINGS'   => 'Settings',
    'ACP_CHASTITY_STATISTICS' => 'Statistics',
    'ACP_CHASTITY_REBUILD'    => 'Rebuild counters',

    // ACP - Settings
    'ACP_CHASTITY_SETTINGS_EXPLAIN'        => 'Configure the Chastity Tracker extension settings.',
    'ACP_CHASTITY_ENABLE'                  => 'Enable chastity tracking',
    'ACP_CHASTITY_PROFILE_DISPLAY'         => 'Show status on profiles and posts',
    'ACP_CHASTITY_MIN_PERIOD_DAYS'         => 'Minimum days per period',
    'ACP_CHASTITY_MIN_PERIOD_DAYS_EXPLAIN' => 'Minimum number of days required to validate a period (0 = no limit)',
    'ACP_CHASTITY_RULES_SETTINGS'          => 'Rules configuration',
    'ACP_CHASTITY_RULES_SETTINGS_EXPLAIN'  => 'Choose which rules will be available to users when creating a period.',
    'ACP_CHASTITY_RULE_ENABLE_EXPLAIN'     => 'Enable this rule to make it available to users',

    // ACP - Locktober
    'ACP_CHASTITY_LOCKTOBER'                     => 'Locktober',
    'ACP_CHASTITY_LOCKTOBER_SETTINGS'            => 'Locktober Settings',
    'ACP_CHASTITY_LOCKTOBER_SETTINGS_EXPLAIN'    => 'Configure settings for the annual Locktober challenge.',
    'ACP_CHASTITY_LOCKTOBER_ENABLED'             => 'Enable Locktober',
    'ACP_CHASTITY_LOCKTOBER_ENABLED_EXPLAIN'     => 'Allow users to participate in the Locktober challenge',
    'ACP_CHASTITY_LOCKTOBER_YEAR'                => 'Active Locktober year',
    'ACP_CHASTITY_LOCKTOBER_YEAR_EXPLAIN'        => 'Current year for the Locktober challenge',
    'ACP_CHASTITY_LOCKTOBER_BADGE_ENABLED'       => 'Show Locktober badges',
    'ACP_CHASTITY_LOCKTOBER_BADGE_EXPLAIN'       => 'Show a special badge for participants and winners',
    'ACP_CHASTITY_LOCKTOBER_LEADERBOARD_ENABLED' => 'Enable Locktober leaderboard',
    'ACP_CHASTITY_LOCKTOBER_LEADERBOARD_EXPLAIN' => 'Show a public leaderboard of participants',

    // ACP - Statistics
    'ACP_CHASTITY_STATISTICS_EXPLAIN' => 'Global view of chastity data across the forum.',
    'ACP_CHASTITY_GLOBAL_STATS'       => 'Global Statistics',
    'ACP_CHASTITY_TOP_USERS'          => 'Top Users',
    'ACP_CHASTITY_ACTIVE_PERIODS'     => 'Active periods',
    'ACP_CHASTITY_TOTAL_USERS'        => 'Participating users',
    'ACP_CHASTITY_AVERAGE_DAYS'       => 'Average days per period',

    // ACP - Rebuild
    'ACP_CHASTITY_REBUILD_EXPLAIN'  => 'Recalculates all day totals and statuses for all users with recorded periods. Use if you detect inconsistencies in statistics.',
    'ACP_CHASTITY_REBUILD_STATUS'   => 'Current state',
    'ACP_CHASTITY_REBUILD_WARNING'  => 'This operation may take a few seconds depending on the number of users.',
    'ACP_CHASTITY_REBUILD_SUBMIT'   => 'Start rebuild',
    'ACP_CHASTITY_REBUILD_CONFIRM'  => 'Confirm rebuild of all counters?',
    'ACP_CHASTITY_REBUILD_DONE'     => 'Rebuild complete: %d user(s) updated.',

    // ACL permissions
    'ACL_U_CHASTITY_VIEW'     => 'Can view chastity tracker',
    'ACL_U_CHASTITY_MANAGE'   => 'Can manage own chastity periods',
    'ACL_M_CHASTITY_MODERATE' => 'Can moderate chastity periods',

));
