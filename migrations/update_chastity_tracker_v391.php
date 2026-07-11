<?php
/**
 * Chastity Tracker — Migration v3.9.1
 * Félicitations record : MP automatique quand un membre bat son record de
 * jours consécutifs.
 *  - config chastity_record_pm_enabled : activation globale (ACP)
 *  - colonne record_pm_optout : le membre peut refuser (préférences UCP)
 *  - colonne record_notified  : dernier record déjà notifié (anti-doublon)
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v391 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_user_prefs', 'record_notified');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v390'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => [
                    'record_pm_optout' => ['BOOL', 0],
                    'record_notified'  => ['UINT', 0],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_record_pm_enabled', 1]],
            ['custom', [[$this, 'init_record_notified']]],
        ];
    }

    /**
     * Initialise record_notified au record actuel de chaque membre, pour ne PAS
     * déclencher une vague de MP au premier passage du cron : seuls les records
     * BATTUS APRÈS l'installation seront notifiés.
     */
    public function init_record_notified()
    {
        $periods_table = $this->table_prefix . 'chastity_periods';
        $prefs_table   = $this->table_prefix . 'chastity_user_prefs';
        $now = time();

        // Plus longue période (en jours) par membre
        $records = [];
        $sql = 'SELECT user_id, start_date, end_date FROM ' . $periods_table . ' WHERE start_date > 0';
        $res = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($res)) {
            $uid = (int) $row['user_id'];
            $start = (int) $row['start_date'];
            $end = ((int) $row['end_date'] > 0) ? (int) $row['end_date'] : $now;
            $days = (int) floor(($end - $start) / 86400);
            if (!isset($records[$uid]) || $days > $records[$uid]) {
                $records[$uid] = $days;
            }
        }
        $this->db->sql_freeresult($res);

        foreach ($records as $uid => $days) {
            if ($days < 1) { continue; }
            // Ne mettre à jour que la ligne de préférences existante
            $r = $this->db->sql_query('SELECT user_id FROM ' . $prefs_table . ' WHERE user_id = ' . (int) $uid);
            $exists = (bool) $this->db->sql_fetchrow($r);
            $this->db->sql_freeresult($r);
            if ($exists) {
                $this->db->sql_query('UPDATE ' . $prefs_table . ' SET record_notified = ' . (int) $days . ' WHERE user_id = ' . (int) $uid);
            } else {
                $this->db->sql_query('INSERT INTO ' . $prefs_table . ' ' . $this->db->sql_build_array('INSERT', [
                    'user_id'         => (int) $uid,
                    'record_notified' => (int) $days,
                ]));
            }
        }
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_user_prefs' => ['record_pm_optout', 'record_notified'],
            ],
        ];
    }
}
