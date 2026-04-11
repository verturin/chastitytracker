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

                $sql_year    = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                               WHERE user_id = $user_id AND status = 'completed' AND start_date >= $year_start";
                $result_year = $this->db->sql_query($sql_year);
                $year_total  = (int) $this->db->sql_fetchfield('total');
                $this->db->sql_freeresult($result_year);

                $active_start    = max((int) $active['start_date'], $year_start);
                $days_in_year    = (int) floor((time() - $active_start) / 86400);
                $days_current_year = $year_total + $days_in_year;

            }
            else
            {
                $sql_year          = 'SELECT SUM(days_count) as total FROM ' . $periods_table . "
                                     WHERE user_id = $user_id AND status = 'completed' AND start_date >= $year_start";
                $result_year       = $this->db->sql_query($sql_year);
                $days_current_year = (int) $this->db->sql_fetchfield('total');
                $this->db->sql_freeresult($result_year);

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
            $result_year = $this->db->sql_query(
                'SELECT SUM(days_count) as total FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed' AND start_date >= $year_start"
            );
            $active_start      = max((int) $active['start_date'], $year_start);
            $days_in_year      = (int) floor((time() - $active_start) / 86400);
            $days_current_year = (int) $this->db->sql_fetchfield('total') + $days_in_year;
            $this->db->sql_freeresult($result_year);

        }
        else
        {
            $result_year = $this->db->sql_query(
                'SELECT SUM(days_count) as total FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed' AND start_date >= $year_start"
            );
            $days_current_year = (int) $this->db->sql_fetchfield('total');
            $this->db->sql_freeresult($result_year);
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
    }
}