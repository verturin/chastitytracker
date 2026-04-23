<?php
namespace verturin\chastitytracker\service;

if (!defined('IN_PHPBB'))
{
    exit;
}

class history_updater
{
    protected $db;
    protected $table_prefix;

    public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db           = $db;
        $this->table_prefix = $table_prefix;
    }

    public function update_history()
    {
        $history_table = $this->table_prefix . 'chastity_history';
        $periods_table = $this->table_prefix . 'chastity_periods';

        $sql    = 'SELECT DISTINCT user_id FROM ' . $periods_table;
        $result = $this->db->sql_query($sql);
        $count  = 0;

        while ($row = $this->db->sql_fetchrow($result))
        {
            $user_id = (int) $row['user_id'];

            $sql_years    = 'SELECT DISTINCT y as year FROM (
                                SELECT YEAR(FROM_UNIXTIME(start_date)) as y FROM ' . $periods_table . '
                                    WHERE user_id = ' . $user_id . ' AND start_date > 0
                                UNION
                                SELECT YEAR(FROM_UNIXTIME(end_date)) as y FROM ' . $periods_table . '
                                    WHERE user_id = ' . $user_id . ' AND end_date > 0
                             ) years ORDER BY year';
            $result_years = $this->db->sql_query($sql_years);

            while ($year_row = $this->db->sql_fetchrow($result_years))
            {
                $year = (int) $year_row['year'];
                if ($year <= 0) { continue; }

                $year_start = mktime(0, 0, 0, 1, 1, $year);
                $year_end   = mktime(23, 59, 59, 12, 31, $year);
                $total_days = 0;

                // Périodes complétées qui chevauchent cette année (multi-années inclus)
                $sql_completed    = 'SELECT start_date, end_date FROM ' . $periods_table . "
                                    WHERE user_id = $user_id AND status = 'completed'
                                    AND start_date <= $year_end AND end_date >= $year_start";
                $result_completed = $this->db->sql_query($sql_completed);
                while ($cp = $this->db->sql_fetchrow($result_completed))
                {
                    $p_start     = max((int) $cp['start_date'], $year_start);
                    $p_end       = min((int) $cp['end_date'],   $year_end);
                    $total_days += (int) floor(($p_end - $p_start) / 86400);
                }
                $this->db->sql_freeresult($result_completed);

                // Période active qui chevauche cette année
                $sql_active    = 'SELECT start_date FROM ' . $periods_table . "
                                 WHERE user_id = $user_id AND status = 'active'
                                 AND start_date <= $year_end LIMIT 1";
                $result_active = $this->db->sql_query($sql_active);
                $active        = $this->db->sql_fetchrow($result_active);
                $this->db->sql_freeresult($result_active);

                if ($active)
                {
                    $p_start     = max((int) $active['start_date'], $year_start);
                    $p_end       = min(time(), $year_end);
                    $total_days += (int) floor(($p_end - $p_start) / 86400);
                }

                // UPSERT dans history
                $sql_check    = 'SELECT id FROM ' . $history_table . "
                                WHERE user_id = $user_id AND year = $year";
                $result_check = $this->db->sql_query($sql_check);
                $exists       = $this->db->sql_fetchrow($result_check);
                $this->db->sql_freeresult($result_check);

                if ($exists)
                {
                    $this->db->sql_query('UPDATE ' . $history_table . "
                        SET total_days = $total_days, last_update = " . time() . "
                        WHERE user_id = $user_id AND year = $year");
                }
                else
                {
                    $this->db->sql_query('INSERT INTO ' . $history_table . '
                        (user_id, year, total_days, last_update)
                        VALUES (' . $user_id . ', ' . $year . ', ' . $total_days . ', ' . time() . ')');
                }

                $count++;
            }
            $this->db->sql_freeresult($result_years);
        }

        $this->db->sql_freeresult($result);
        return $count;
    }

    public function update_user_history($user_id)
    {
        $history_table = $this->table_prefix . 'chastity_history';
        $periods_table = $this->table_prefix . 'chastity_periods';
        $user_id = (int) $user_id;

        $result_years = $this->db->sql_query(
            'SELECT DISTINCT y as year FROM (
                SELECT YEAR(FROM_UNIXTIME(start_date)) as y FROM ' . $periods_table . '
                    WHERE user_id = ' . $user_id . ' AND start_date > 0
                UNION
                SELECT YEAR(FROM_UNIXTIME(end_date)) as y FROM ' . $periods_table . '
                    WHERE user_id = ' . $user_id . ' AND end_date > 0
             ) years ORDER BY year'
        );

        while ($year_row = $this->db->sql_fetchrow($result_years))
        {
            $year = (int) $year_row['year'];
            if ($year <= 0) { continue; }
            $year_start = mktime(0, 0, 0, 1, 1, $year);
            $year_end   = mktime(23, 59, 59, 12, 31, $year);
            $total_days = 0;

            // Périodes complétées qui chevauchent cette année (multi-années inclus)
            $r = $this->db->sql_query('SELECT start_date, end_date FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'completed'" .
                " AND start_date <= $year_end AND end_date >= $year_start");
            while ($cp = $this->db->sql_fetchrow($r))
            {
                $p_start     = max((int) $cp['start_date'], $year_start);
                $p_end       = min((int) $cp['end_date'],   $year_end);
                $total_days += (int) floor(($p_end - $p_start) / 86400);
            }
            $this->db->sql_freeresult($r);

            // Période active qui chevauche cette année
            $r = $this->db->sql_query('SELECT start_date FROM ' . $periods_table .
                " WHERE user_id = $user_id AND status = 'active' AND start_date <= $year_end LIMIT 1");
            $active = $this->db->sql_fetchrow($r);
            $this->db->sql_freeresult($r);
            if ($active)
            {
                $p_start     = max((int) $active['start_date'], $year_start);
                $p_end       = min(time(), $year_end);
                $total_days += (int) floor(($p_end - $p_start) / 86400);
            }

            $this->db->sql_query('INSERT INTO ' . $history_table .
                ' (user_id, year, total_days, last_update) VALUES (' . $user_id . ', ' . $year . ', ' . $total_days . ', ' . time() . ')
                ON DUPLICATE KEY UPDATE total_days = ' . $total_days . ', last_update = ' . time());
        }
        $this->db->sql_freeresult($result_years);
    }
}