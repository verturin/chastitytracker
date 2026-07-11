<?php
/**
 * Chastity Tracker — Migration v3.9.0 (finale)
 * Jalon de version : clôt la chaîne de migrations de la 3.9.0.
 * Regroupe l'ensemble des évolutions développées sous 3.8.1 (anneaux,
 * Locktober refondu, périodes parfaites, journées spéciales, anniversaires,
 * paliers consécutifs/totaux, badges figés, options d'affichage).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v390 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_v390_done']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381i'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_v390_done', 1]],
        ];
    }
}
