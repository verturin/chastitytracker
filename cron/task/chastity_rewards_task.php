<?php
/**
 * Chastity Tracker — Cron task : comptage des périodes "parfaites"
 * Une période est parfaite quand les 3 anneaux (cage, posts, connexions)
 * de l'échelle (jour / mois / année) sont complétés. Le compteur cumulé
 * est incrémenté une seule fois par période grâce à last_period.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\cron\task;

use phpbb\config\config;
use phpbb\cron\task\base;
use phpbb\db\driver\driver_interface;
use verturin\chastitytracker\service\rewards_calculator;

class chastity_rewards_task extends base
{
    /** @var config */
    protected $config;

    /** @var driver_interface */
    protected $db;

    /** @var rewards_calculator */
    protected $rewards_calc;

    /** @var string */
    protected $periods_table;

    /** @var string */
    protected $active_days_table;

    /** @var string */
    protected $perfect_table;

    /** @var \verturin\chastitytracker\service\record_notifier */
    protected $record_notifier;

    public function __construct(config $config, driver_interface $db, rewards_calculator $rewards_calc, $periods_table, $active_days_table, $perfect_table, $record_notifier = null)
    {
        $this->config            = $config;
        $this->db                = $db;
        $this->rewards_calc      = $rewards_calc;
        $this->periods_table     = $periods_table;
        $this->active_days_table = $active_days_table;
        $this->perfect_table     = $perfect_table;
        $this->record_notifier   = $record_notifier;
    }

    public function run()
    {
        $this->rewards_calc->recalc_perfect_counts($this->perfect_table);

        // Félicitations record : vérifier les membres actuellement verrouillés
        // (période en cours), dont la série peut avoir dépassé leur record.
        if ($this->record_notifier !== null && !empty($this->config['chastity_record_pm_enabled'])) {
            $res = $this->db->sql_query('SELECT DISTINCT user_id FROM ' . $this->periods_table . '
                                         WHERE end_date = 0 AND start_date > 0');
            while ($row = $this->db->sql_fetchrow($res)) {
                try {
                    $this->record_notifier->check_and_notify((int) $row['user_id'], false);
                } catch (\Throwable $e) {}
            }
            $this->db->sql_freeresult($res);
        }

        $this->config->set('chastity_rewards_last_gc', time());
    }

    public function is_runnable()
    {
        return (bool) ($this->config['chastity_rewards_enabled'] ?? 0);
    }

    public function should_run()
    {
        return (int) ($this->config['chastity_rewards_last_gc'] ?? 0) < time() - 86400;
    }
}
