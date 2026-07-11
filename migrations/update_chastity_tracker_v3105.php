<?php
/**
 * Chastity Tracker — Migration v3.10.5
 * CTR — Contrat de chasteté : motif de refus lors du rejet d'un contrat en
 * attente de validation, et statut permettant l'arrêt définitif depuis une
 * suspension par mot de sécurité.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3105 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contracts', 'last_rejection_reason');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3104'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'last_rejection_reason' => ['TEXT', ''],
                ],
            ],
        ];
    }
}
