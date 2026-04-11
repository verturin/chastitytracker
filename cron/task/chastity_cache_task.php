<?php
/**
 * Chastity Tracker — Cron task : mise à jour du cache
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\cron\task;

use phpbb\config\config;
use phpbb\cron\task\base;

class chastity_cache_task extends base
{
    /** @var config */
    protected $config;

    /** @var \verturin\chastitytracker\service\cache_updater */
    protected $cache_updater;

    public function __construct(config $config, $cache_updater)
    {
        $this->config        = $config;
        $this->cache_updater = $cache_updater;
    }

    public function run()
    {
        $this->cache_updater->update_cache();
        $this->config->set('chastity_cache_last_gc', time());
    }

    public function is_runnable()
    {
        return (bool) ($this->config['chastity_cache_cron_enabled'] ?? 1);
    }

    public function should_run()
    {
        // Intervalle en minutes (depuis ACP) converti en secondes
        $interval = max(1, (int) ($this->config['chastity_cache_gc'] ?? 60)) * 60;
        return (int) $this->config['chastity_cache_last_gc'] < time() - $interval;
    }
}
