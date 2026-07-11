<?php
/**
 * Chastity Tracker — Cron task : complétion automatique du Locktober
 * Passe locktober_completed = 1 pour toute période Locktober ayant atteint
 * 31 jours (active ou terminée), afin d'attribuer le badge « Réussi » sans
 * intervention manuelle.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\cron\task;

use phpbb\config\config;
use phpbb\cron\task\base;
use phpbb\db\driver\driver_interface;

class chastity_locktober_task extends base
{
    /** @var config */
    protected $config;

    /** @var driver_interface */
    protected $db;

    /** @var string */
    protected $periods_table;

    public function __construct(config $config, driver_interface $db, $periods_table)
    {
        $this->config        = $config;
        $this->db            = $db;
        $this->periods_table = $periods_table;
    }

    public function run()
    {
        $now = time();

        // On ne marque "réussi" qu'une fois le mois d'octobre de l'année
        // concernée terminé, et seulement si la période couvre TOUT octobre :
        //   - commencée au plus tard le 1er octobre 00:00 de l'année N
        //   - encore en cage au 31 octobre 23:59 de l'année N (active ou
        //     terminée après cette date)
        // La période n'est jamais fermée : seul le drapeau passe à 1.
        $sql = 'SELECT DISTINCT locktober_year FROM ' . $this->periods_table . '
                WHERE is_locktober = 1 AND locktober_completed = 0 AND locktober_year > 0';
        $res = $this->db->sql_query($sql);
        $years = [];
        while ($row = $this->db->sql_fetchrow($res))
        {
            $years[] = (int) $row['locktober_year'];
        }
        $this->db->sql_freeresult($res);

        foreach ($years as $year)
        {
            $oct_start = mktime(0, 0, 0, 10, 1, $year);    // 1er oct. 00:00
            $oct_end   = mktime(23, 59, 59, 10, 31, $year); // 31 oct. 23:59

            // Octobre de cette année doit être terminé
            if ($now <= $oct_end)
            {
                continue;
            }

            // Couverture "encagé tout octobre" : commencé au plus tard dans la
            // journée du 1er oct, fini au plus tôt dans la journée du 31 oct.
            $cover_start = mktime(23, 59, 59, 10, 1, $year);
            $cover_end   = mktime(0, 0, 0, 10, 31, $year);

            $this->db->sql_query('UPDATE ' . $this->periods_table . '
                SET locktober_completed = 1
                WHERE is_locktober = 1
                  AND locktober_completed = 0
                  AND locktober_year = ' . (int) $year . '
                  AND start_date <= ' . (int) $cover_start . '
                  AND (end_date = 0 OR end_date >= ' . (int) $cover_end . ')');
        }

        $this->config->set('chastity_locktober_last_gc', $now);
    }

    public function is_runnable()
    {
        return (bool) ($this->config['chastity_locktober_enabled'] ?? 0);
    }

    public function should_run()
    {
        // Toutes les 24h
        return (int) ($this->config['chastity_locktober_last_gc'] ?? 0) < time() - 86400;
    }
}
