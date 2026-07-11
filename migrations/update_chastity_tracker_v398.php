<?php
/**
 * Chastity Tracker — Migration v3.9.8
 * CTR — Contrat de chasteté : ajout de la catégorie sur les articles liés à
 * un contrat (chastity_contract_links), pour permettre le regroupement et la
 * numérotation par catégorie dans l'affichage final du contrat.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v398 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contract_links', 'category');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v397'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contract_links' => [
                    'category' => ['VCHAR:64', 'personnalise'],
                ],
            ],
        ];
    }
}
