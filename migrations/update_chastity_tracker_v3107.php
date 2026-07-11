<?php
/**
 * Chastity Tracker — Migration v3.10.7
 * CTR — Contrat de chasteté : indicateur de suspension automatique suite à
 * la fin de la relation Keyholder (distinct d'une suspension par mot de
 * sécurité, qui utilise safeword_suspended_by).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3107 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contracts', 'suspended_kh_relation_end');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3106'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'suspended_kh_relation_end' => ['BOOL', 0],
                ],
            ],
        ];
    }
}
