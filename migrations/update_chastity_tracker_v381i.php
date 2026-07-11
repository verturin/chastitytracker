<?php
/**
 * Chastity Tracker — Migration v3.8.1i
 * Deux options d'affichage des paliers (consécutifs / totaux) :
 *  - chastity_ms_show_next : afficher le prochain palier non atteint, grisé
 *  - chastity_ms_compact   : n'afficher que le dernier obtenu + le prochain grisé
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381i extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_ms_show_next']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381h'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_ms_show_next', 0]],
            ['config.add', ['chastity_ms_compact', 0]],
        ];
    }
}
