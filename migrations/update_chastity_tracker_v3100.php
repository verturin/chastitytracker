<?php
/**
 * Chastity Tracker — Migration v3.10.0
 * CTR — Contrat de chasteté : catégories d'articles dynamiques.
 *
 * Remplace les 7 catégories codées en dur par une vraie table gérable en
 * ACP (créer/modifier/supprimer). Peuple la table avec les 7 catégories
 * existantes pour ne rien perdre, plus une catégorie fixe "Articles
 * personnalisés" qui accueille les articles proposés par les membres et
 * validés comme globaux par l'admin.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3100 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_contract_categories');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v399'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_categories_table']]],
            ['custom', [[$this, 'seed_categories']]],
        ];
    }

    public function create_categories_table()
    {
        $table = $this->table_prefix . 'chastity_contract_categories';
        if ($this->db_tools->sql_table_exists($table)) {
            return;
        }
        $this->db_tools->sql_create_table($table, [
            'COLUMNS' => [
                'category_id'  => ['UINT', null, 'auto_increment'],
                'category_key' => ['VCHAR:64', ''],
                'label'        => ['VCHAR:255', ''],
                'sort_order'   => ['UINT', 0],
                'is_locked'    => ['BOOL', 0],
                'created_time' => ['UINT:11', 0],
            ],
            'PRIMARY_KEY' => 'category_id',
        ]);
    }

    public function seed_categories()
    {
        $table = $this->table_prefix . 'chastity_contract_categories';

        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $table;
        $result = $this->db->sql_query($sql);
        $count  = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);

        if ($count > 0) {
            return;
        }

        $now = time();
        $categories = [
            ['key' => 'exclusivite',    'label' => 'Exclusivité'],
            ['key' => 'dispositif',     'label' => 'Dispositif'],
            ['key' => 'cles',           'label' => 'Gestion des clés'],
            ['key' => 'intimite',       'label' => 'Intimité'],
            ['key' => 'discipline',     'label' => 'Discipline'],
            ['key' => 'communication',  'label' => 'Communication'],
            ['key' => 'cadre',          'label' => 'Cadre et confidentialité'],
        ];

        $order = 0;
        foreach ($categories as $cat) {
            $order++;
            $this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
                'category_key' => $cat['key'],
                'label'        => $cat['label'],
                'sort_order'   => $order,
                'is_locked'    => 0,
                'created_time' => $now,
            ]));
        }

        // Catégorie fixe "Articles personnalisés" : verrouillée (ne peut pas
        // être supprimée ni renommée depuis l'ACP), utilisée par défaut par
        // les articles proposés par les membres puis validés comme globaux.
        $order++;
        $this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
            'category_key' => 'personnalise',
            'label'        => 'Articles personnalisés',
            'sort_order'   => $order,
            'is_locked'    => 1,
            'created_time' => $now,
        ]));
    }
}
