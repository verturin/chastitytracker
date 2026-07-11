<?php
/**
 * Chastity Tracker — Migration v3.5.1
 * - Table cage_materials (matériaux personnalisables)
 * - Table cage_ratings (notation 1-5 étoiles)
 * - Champ avg_rating dans cage_catalog
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v351 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_cage_materials');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v350'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_cage_materials' => [
                    'COLUMNS' => [
                        'material_id'   => ['UINT', null, 'auto_increment'],
                        'material_key'  => ['VCHAR:50', ''],
                        'material_name' => ['VCHAR:100', ''],
                        'is_validated'  => ['BOOL', 1],
                        'created_at'    => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'material_id',
                ],
                $this->table_prefix . 'chastity_cage_ratings' => [
                    'COLUMNS' => [
                        'rating_id'  => ['UINT', null, 'auto_increment'],
                        'catalog_id' => ['UINT', 0],
                        'user_id'    => ['UINT', 0],
                        'rating'     => ['UINT', 0],
                        'comment'    => ['TEXT_UNI', ''],
                        'created_at' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'rating_id',
                    'KEYS' => [
                        'catalog_user' => ['UNIQUE', ['catalog_id', 'user_id']],
                    ],
                ],
            ],
            'add_columns' => [
                $this->table_prefix . 'chastity_cage_catalog' => [
                    'avg_rating'   => ['DECIMAL:3,2', 0],
                    'rating_count' => ['UINT', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_cage_catalog' => ['avg_rating', 'rating_count'],
            ],
            'drop_tables' => [
                $this->table_prefix . 'chastity_cage_ratings',
                $this->table_prefix . 'chastity_cage_materials',
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Pré-remplir avec les matériaux par défaut
            ['custom', [[$this, 'seed_default_materials']]],
            // Ajouter le module ACP cage_materials
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['cage_materials'],
            ]]],
        ];
    }

    public function seed_default_materials()
    {
        $defaults = [
            'silicone' => 'Silicone',
            'resin'    => 'Résine',
            'metal'    => 'Métal',
            'plastic'  => 'Plastique',
            'nylon'    => 'Nylon',
            '3dprint'  => 'Impression 3D',
            'other'    => 'Autre',
        ];
        foreach ($defaults as $key => $name)
        {
            $this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'chastity_cage_materials '
                . $this->db->sql_build_array('INSERT', [
                    'material_key'  => $key,
                    'material_name' => $name,
                    'is_validated'  => 1,
                    'created_at'    => time(),
                ]));
        }
    }
}
