<?php
/**
 * Chastity Tracker — Migration v3.10.4
 * CTR — Contrat de chasteté : crée la catégorie "Base du Contrat" directement
 * à l'installation, en première position (sort_order = 0), plutôt que de
 * laisser l'administrateur la créer manuellement.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3104 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT category_id FROM ' . $this->table_prefix . "chastity_contract_categories
                WHERE category_key = 'base_contrat'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3103'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'create_base_category']]],
        ];
    }

    public function create_base_category()
    {
        $table = $this->table_prefix . 'chastity_contract_categories';

        // Décaler toutes les catégories existantes d'un rang (sauf la
        // position 0, réservée), pour libérer la première place.
        $this->db->sql_query('UPDATE ' . $table . ' SET sort_order = sort_order + 1 WHERE sort_order > 0');

        $this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
            'category_key' => 'base_contrat',
            'label'        => 'Base du Contrat',
            'sort_order'   => 0,
            'is_locked'    => 0,
            'created_time' => time(),
        ]));
    }
}
