<?php
/**
 * Chastity Tracker — Migration v3.10.3
 * CTR — Contrat de chasteté : conserve la traçabilité de qui a proposé un
 * article, même une fois celui-ci validé et ajouté à la bibliothèque comme
 * GLOBAL (auquel cas user_id est mis à 0 pour le rendre visible par tous,
 * ce qui écrasait jusqu'ici l'information de son auteur d'origine).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3103 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contract_articles', 'submitted_by');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3102'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contract_articles' => [
                    'submitted_by' => ['UINT', 0],
                ],
            ],
        ];
    }
}
