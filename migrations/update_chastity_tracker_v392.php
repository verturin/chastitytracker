<?php
/**
 * Chastity Tracker — Migration v3.9.2 (v3.9.3 fonctionnelle)
 * CTR — Contrat de chasteté entre l'encagé et son/sa Keyholder.
 * Étape 1 : structure de base (tables + permission de groupe dédiée).
 *
 * Tables créées :
 *  - chastity_contract_articles      : bibliothèque d'articles (clauses) réutilisables
 *  - chastity_contracts              : les contrats eux-mêmes (un par relation/version)
 *  - chastity_contract_links         : liaison contrat <-> article, avec statut de
 *                                       proposition (pending/approved/rejected) et
 *                                       qui l'a proposé
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v392 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_contracts');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v391'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_contract_tables']]],
        ];
    }

    public function create_contract_tables()
    {
        $tables = [
            $this->table_prefix . 'chastity_contract_articles' => [
                'COLUMNS' => [
                    'article_id'    => ['UINT', null, 'auto_increment'],
                    'user_id'       => ['UINT', 0],
                    'title'         => ['VCHAR:255', ''],
                    'body'          => ['TEXT', ''],
                    'is_draft'      => ['BOOL', 1],
                    'created_time'  => ['UINT:11', 0],
                    'updated_time'  => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'article_id',
            ],
            $this->table_prefix . 'chastity_contracts' => [
                'COLUMNS' => [
                    'contract_id'       => ['UINT', null, 'auto_increment'],
                    'encage_user_id'    => ['UINT', 0],
                    'kh_user_id'        => ['UINT', 0],
                    'kh_external_name'  => ['VCHAR:255', ''],
                    'kh_external_email' => ['VCHAR:255', ''],
                    'status'            => ['VCHAR:20', 'draft'],
                    'validation_code'   => ['VCHAR:64', ''],
                    'validation_token'  => ['VCHAR:64', ''],
                    'sent_time'         => ['UINT:11', 0],
                    'validated_time'    => ['UINT:11', 0],
                    'ended_time'        => ['UINT:11', 0],
                    'replaced_by'       => ['UINT', 0],
                    'pdf_path'          => ['VCHAR:255', ''],
                    'created_time'      => ['UINT:11', 0],
                    'updated_time'      => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'contract_id',
            ],
            $this->table_prefix . 'chastity_contract_links' => [
                'COLUMNS' => [
                    'link_id'        => ['UINT', null, 'auto_increment'],
                    'contract_id'    => ['UINT', 0],
                    'article_id'     => ['UINT', 0],
                    'article_title'  => ['VCHAR:255', ''],
                    'article_body'   => ['TEXT', ''],
                    'sort_order'     => ['UINT', 0],
                    'proposal_status'=> ['VCHAR:20', 'pending'],
                    'proposed_by'    => ['UINT', 0],
                    'created_time'   => ['UINT:11', 0],
                    'updated_time'   => ['UINT:11', 0],
                ],
                'PRIMARY_KEY' => 'link_id',
            ],
        ];

        foreach ($tables as $table_name => $table_data) {
            if ($this->db_tools->sql_table_exists($table_name)) {
                continue;
            }
            $this->db_tools->sql_create_table($table_name, $table_data);
        }
    }

    public function revert_data()
    {
        return [
            ['custom', [[$this, 'drop_contract_tables']]],
        ];
    }

    public function drop_contract_tables()
    {
        foreach (['chastity_contract_links', 'chastity_contracts', 'chastity_contract_articles'] as $t) {
            $table = $this->table_prefix . $t;
            if ($this->db_tools->sql_table_exists($table)) {
                $this->db_tools->sql_table_drop($table);
            }
        }
    }
}
