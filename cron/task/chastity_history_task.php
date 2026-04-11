<?php
/**
 * Chastity Tracker — Cron task : mise à jour de l'historique annuel
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\cron\task;

use phpbb\config\config;
use phpbb\cron\task\base;

class chastity_history_task extends base
{
    /** @var config */
    protected $config;

    /** @var \verturin\chastitytracker\service\history_updater */
    protected $history_updater;

    public function __construct(config $config, $history_updater)
    {
        $this->config          = $config;
        $this->history_updater = $history_updater;
    }

    public function run()
    {
        $this->history_updater->update_history();
        $this->config->set('chastity_history_last_gc', time());
    }

    public function is_runnable()
    {
        return (bool) ($this->config['chastity_history_cron_enabled'] ?? 1);
    }

    public function should_run()
    {
        // Intervalle en minutes (depuis ACP) converti en secondes
        $interval = max(1, (int) ($this->config['chastity_history_gc'] ?? 1440)) * 60;
        return (int) $this->config['chastity_history_last_gc'] < time() - $interval;
    }
}
