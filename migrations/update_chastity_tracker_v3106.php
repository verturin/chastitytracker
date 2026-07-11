<?php
/**
 * Chastity Tracker — Migration v3.10.6
 * CTR — Contrat de chasteté : correction du type de last_rejection_reason.
 *
 * La migration v3105 avait déclaré cette colonne en TEXT avec un défaut ''.
 * Or MySQL interdit nativement toute valeur DEFAULT sur les colonnes
 * BLOB/TEXT/GEOMETRY/JSON (contrainte du moteur, pas de phpBB) : en mode
 * strict, tout INSERT omettant cette colonne échouait avec "doesn't have a
 * default value". Remplacée par VARCHAR:255 (un motif de refus court
 * suffit largement), qui autorise nativement un DEFAULT ''.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3106 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_ctr_v3106_done']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3105'];
    }

    public function update_schema()
    {
        return [
            'change_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'last_rejection_reason' => ['VCHAR:255', ''],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_ctr_v3106_done', 1]],
        ];
    }
}
