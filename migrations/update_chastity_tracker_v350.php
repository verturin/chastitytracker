<?php
/**
 * Chastity Tracker — Migration v3.5.0
 * Système de cages : catalogue, fabricants, collection, photos, usage
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v350 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_cage_catalog');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v343'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_cage_manufacturers' => [
                    'COLUMNS' => [
                        'manufacturer_id' => ['UINT', null, 'auto_increment'],
                        'name'            => ['VCHAR:200', ''],
                        'address'         => ['TEXT_UNI', ''],
                        'phone'           => ['VCHAR:50', ''],
                        'email'           => ['VCHAR:200', ''],
                        'website'         => ['VCHAR:255', ''],
                        'is_partner'      => ['BOOL', 0],
                        'partner_notes'   => ['TEXT_UNI', ''],
                        'created_at'      => ['UINT:11', 0],
                        'updated_at'      => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'manufacturer_id',
                ],
                $this->table_prefix . 'chastity_cage_catalog' => [
                    'COLUMNS' => [
                        'catalog_id'       => ['UINT', null, 'auto_increment'],
                        'cage_name'        => ['VCHAR:200', ''],
                        'cage_brand'       => ['VCHAR:200', ''],
                        'cage_material'    => ['VCHAR:100', ''],
                        'cage_type'        => ['VCHAR:100', ''],
                        'cage_description' => ['TEXT_UNI', ''],
                        'manufacturer_id'  => ['UINT', 0],
                        'added_by_user_id' => ['UINT', 0],
                        'is_validated'     => ['BOOL', 0],
                        'usage_count'      => ['UINT', 0],
                        'created_at'       => ['UINT:11', 0],
                        'updated_at'       => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'catalog_id',
                    'KEYS' => [
                        'manufacturer_id' => ['INDEX', 'manufacturer_id'],
                        'is_validated'    => ['INDEX', 'is_validated'],
                    ],
                ],
                $this->table_prefix . 'chastity_cage_photos' => [
                    'COLUMNS' => [
                        'photo_id'     => ['UINT', null, 'auto_increment'],
                        'catalog_id'   => ['UINT', 0],
                        'user_id'      => ['UINT', 0],
                        'filename'     => ['VCHAR:255', ''],
                        'is_main'      => ['BOOL', 0],
                        'is_validated' => ['BOOL', 0],
                        'uploaded_at'  => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'photo_id',
                    'KEYS' => [
                        'catalog_id' => ['INDEX', 'catalog_id'],
                    ],
                ],
                $this->table_prefix . 'chastity_cages' => [
                    'COLUMNS' => [
                        'cage_id'    => ['UINT', null, 'auto_increment'],
                        'user_id'    => ['UINT', 0],
                        'catalog_id' => ['UINT', 0],
                        'cage_notes' => ['TEXT_UNI', ''],
                        'is_active'  => ['BOOL', 1],
                        'added_at'   => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'cage_id',
                    'KEYS' => [
                        'user_id'    => ['INDEX', 'user_id'],
                        'catalog_id' => ['INDEX', 'catalog_id'],
                    ],
                ],
                $this->table_prefix . 'chastity_cage_usage' => [
                    'COLUMNS' => [
                        'usage_id'   => ['UINT', null, 'auto_increment'],
                        'user_id'    => ['UINT', 0],
                        'period_id'  => ['UINT', 0],
                        'cage_id'    => ['UINT', 0],
                        'start_date' => ['UINT:11', 0],
                        'end_date'   => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'usage_id',
                    'KEYS' => [
                        'user_id'   => ['INDEX', 'user_id'],
                        'period_id' => ['INDEX', 'period_id'],
                        'cage_id'   => ['INDEX', 'cage_id'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'chastity_cage_usage',
                $this->table_prefix . 'chastity_cages',
                $this->table_prefix . 'chastity_cage_photos',
                $this->table_prefix . 'chastity_cage_catalog',
                $this->table_prefix . 'chastity_cage_manufacturers',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['cage_catalog', 'cage_manufacturers'],
            ]]],
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['cage_collection', 'cage_catalog'],
            ]]],
        ];
    }
}
