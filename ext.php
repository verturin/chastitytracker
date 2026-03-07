<?php
/**
 * Chastity Tracker Extension
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker;

/**
 * Extension class for Chastity Tracker
 */
class ext extends \phpbb\extension\base
{
    /**
     * Check whether the extension can be enabled.
     * Provides meaningful(s) error message(s) and the back-link on failure.
     * CLI and 3.1/3.2 compatible (nothing to do).
     *
     * @return bool
     */
    public function is_enableable()
    {
        // Vérifier la version PHP
        $php_version = PHP_VERSION;
        if (version_compare($php_version, '7.1.0', '<'))
        {
            trigger_error('PHP 7.1.0 ou supérieur est requis pour cette extension.', E_USER_WARNING);
            return false;
        }

        // Vérifier la version phpBB
        $config = $this->container->get('config');
        if (version_compare($config['version'], '3.2.0', '<'))
        {
            trigger_error('phpBB 3.2.0 ou supérieur est requis pour cette extension.', E_USER_WARNING);
            return false;
        }

        return true;
    }
}
