<?php
namespace verturin\chastitytracker\service;

if (!defined('IN_PHPBB'))
{
    exit;
}

class cache_updater
{
    protected $db;
    protected $table_prefix;

    public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db           = $db;
        $this->table_prefix = $table_prefix;
    }

    public function update_cache()
    {
        $cache_table   = $this->table_prefix . 'chastity_cache';
        $periods_table = $this->table_prefix . 'chastity_periods';
        $users_table   = $this->table_prefix . 'chastity_users';

        $current_year = (int) date('Y');
        $year_start   = mktime(0, 0, 0, 1, 1, $current_year);

        $sql    = 'SELECT DISTINCT user_id FROM ' . $periods_table;
        $result = $this->db->sql_query($sql);
        $count  = 0;

        while ($row = $this->db->sql_fetchrow($result))
        {
            $user_id             = (int) $row['user_id'];
            $days_current_period = 0;
            $days_current_year   = 0;
            $days_since_last_end = 0;

            $sql_active    = 'SELECT start_date FROM ' . $periods_table . "
                              WHERE user_id = $user_id AND status = 'active' LIMIT 1";
            $result_active = $this->db->sql_query($sql_active);
            $active        = $this->db->sql_fetchrow($result_active);
            $this->db->sql_freeresult($result_active);

            if ($active)
            {
				$days_current_period = (int) floor((time() - (int) $active['start_date']) / 86400);

                // Périodes terminées qui touchent l'année (gère chevauchements)
                $year_end = mktime(23, 59, 59, 12, 31, $current_year);
                $sql_year    = 'SELECT start_date, end_date FROM ' . $periods_table . "
                               WHERE user_id = $user_id AND status = 'completed' AND end_date >= $year_start";
                $result_year = $this->db->sql_query($sql_year);
                $year_seconds = 0;
                while ($py = $this->db->sql_fetchrow($result_year))
                {
                    $ps = max((int) $py['start_date'], $year_start);
                    $pe = min((int) $py['end_date'],   $year_end);
                    if ($pe > $ps) { $year_seconds += ($pe - $ps); }
                }
                $this->db->sql_freeresult($result_year);

                $active_start = max((int) $active['start_date'], $year_start);
                $active_end   = min(time(), $year_end);
                if ($active_end > $active_start) { $year_seconds += ($active_end - $active_start); }
                $days_current_year = (int) floor($year_seconds / 86400);

            }
            else
            {
                // Périodes terminées qui touchent l'année (gère chevauchements)
                $year_end = mktime(23, 59, 59, 12, 31, $current_year);
                $sql_year = 'SELECT start_date, end_date FROM ' . $periods_table . "
                             WHERE user_id = $user_id AND status = 'completed' AND end_date >= $year_start";
                $result_year = $this->db->sql_query($sql_year);
                $year_seconds = 0;
                while ($py = $this->db->sql_fetchrow($result_year))
                {
                    $ps = max((int) $py['start_date'], $year_start);
                    $pe = min((int) $py['end_date'],   $year_end);
                    if ($pe > $ps) { $year_seconds += ($pe - $ps); }
                }
                $this->db->sql_freeresult($result_year);
                $days_current_year = (int) floor($year_seconds / 86400);

                $sql_last    = 'SELECT end_date FROM ' . $periods_table . "
                               WHERE user_id = $user_id AND status = 'completed' AND end_date > 0
                               ORDER BY end_date DESC LIMIT 1";
                $result_last = $this->db->sql_query($sql_last);
                $last_period = $this->db->sql_fetchrow($result_last);
                $this->db->sql_freeresult($result_last);

                if ($last_period)
                {
                    $days_since_last_end = (int) floor((time() - (int) $last_period['end_date']) / 86400);
                }
            }

            // UPSERT : INSERT ou UPDATE en une seule requête (PRIMARY KEY = user_id)
            $this->db->sql_query('INSERT INTO ' . $cache_table . '
                (user_id, days_current_period, days_current_year, days_since_last_end, last_update, next_update)
                VALUES (' . $user_id . ', ' . $days_current_period . ', ' . $days_current_year . ', ' . $days_since_last_end . ', ' . time() . ', 0)
                ON DUPLICATE KEY UPDATE
                    days_current_period = ' . $days_current_period . ',
                    days_current_year   = ' . $days_current_year   . ',
                    days_since_last_end = ' . $days_since_last_end . ',
                    last_update         = ' . time()               . ',
                    next_update         = 0');

            // Mettre à jour le total de jours dans chastity_users
            $sql_total = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                          WHERE user_id = $user_id AND status = 'completed'";
            $result_total = $this->db->sql_query($sql_total);
            $total_days   = (int) $this->db->sql_fetchfield('total') + $days_current_period;
            $this->db->sql_freeresult($result_total);
            $this->db->sql_query('UPDATE ' . $users_table . '
                SET chastity_total_days = ' . $total_days . '
                WHERE user_id = ' . $user_id);

            $count++;
        }

        $this->db->sql_freeresult($result);
        return $count;
    }

    public function update_user_cache($user_id)
    {
        $cache_table   = $this->table_prefix . 'chastity_cache';
        $periods_table = $this->table_prefix . 'chastity_periods';
        $user_id       = (int) $user_id;
        $current_year  = (int) date('Y');
        $year_start    = mktime(0, 0, 0, 1, 1, $current_year);
        $days_current_period = 0;
        $days_current_year   = 0;
        $days_since_last_end = 0;

        $result_active = $this->db->sql_query(
            'SELECT start_date FROM ' . $periods_table .
            " WHERE user_id = $user_id AND status = 'active' LIMIT 1"
        );
        $active = $this->db->sql_fetchrow($result_active);
        $this->db->sql_freeresult($result_active);

        if ($active)
        {
			$days_current_period = (int) floor((time() - (int) $active['start_date']) / 86400);
            $year_end = mktime(23, 59, 59, 12, 31, $current_year);
            $result_year = $this->db->sql_query(
                'SELECT start_date, end_date FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed' AND end_date >= $year_start"
            );
            $year_seconds = 0;
            while ($py = $this->db->sql_fetchrow($result_year))
            {
                $ps = max((int) $py['start_date'], $year_start);
                $pe = min((int) $py['end_date'],   $year_end);
                if ($pe > $ps) { $year_seconds += ($pe - $ps); }
            }
            $this->db->sql_freeresult($result_year);
            $active_start = max((int) $active['start_date'], $year_start);
            $active_end   = min(time(), $year_end);
            if ($active_end > $active_start) { $year_seconds += ($active_end - $active_start); }
            $days_current_year = (int) floor($year_seconds / 86400);

        }
        else
        {
            $year_end = mktime(23, 59, 59, 12, 31, $current_year);
            $result_year = $this->db->sql_query(
                'SELECT start_date, end_date FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed' AND end_date >= $year_start"
            );
            $year_seconds = 0;
            while ($py = $this->db->sql_fetchrow($result_year))
            {
                $ps = max((int) $py['start_date'], $year_start);
                $pe = min((int) $py['end_date'],   $year_end);
                if ($pe > $ps) { $year_seconds += ($pe - $ps); }
            }
            $this->db->sql_freeresult($result_year);
            $days_current_year = (int) floor($year_seconds / 86400);
            $result_last = $this->db->sql_query(
                'SELECT end_date FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed' AND end_date > 0" .
                ' ORDER BY end_date DESC LIMIT 1'
            );
            $last = $this->db->sql_fetchrow($result_last);
            $this->db->sql_freeresult($result_last);
            if ($last) { $days_since_last_end = (int) floor((time() - (int) $last['end_date']) / 86400); }
        }

        $this->db->sql_query(
            'INSERT INTO ' . $cache_table . '
            (user_id, days_current_period, days_current_year, days_since_last_end, last_update, next_update)
            VALUES (' . $user_id . ', ' . $days_current_period . ', ' . $days_current_year . ', ' . $days_since_last_end . ', ' . time() . ', 0)
            ON DUPLICATE KEY UPDATE
                days_current_period = ' . $days_current_period . ',
                days_current_year   = ' . $days_current_year   . ',
                days_since_last_end = ' . $days_since_last_end . ',
                last_update         = ' . time()               . ',
                next_update         = 0'
        );

        // Mettre à jour le total de jours dans chastity_users
        $sql_total = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                      WHERE user_id = $user_id AND status = 'completed'";
        $result_total = $this->db->sql_query($sql_total);
        $total_days   = (int) $this->db->sql_fetchfield('total') + $days_current_period;
        $this->db->sql_freeresult($result_total);
        $this->db->sql_query('UPDATE ' . $this->table_prefix . 'chastity_users
            SET chastity_total_days = ' . $total_days . '
            WHERE user_id = ' . $user_id);
    }
}
