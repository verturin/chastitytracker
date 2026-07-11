<?php
/**
 * Chastity Tracker — Migration v3.7.0
 * Crée la table chastity_keyholders pour les relations Keyholder ↔ Sub.
 *
 * Statuts :
 *  - pending  : demande envoyée par le sub, en attente de réponse du KH
 *  - active   : relation active (KH a accepté)
 *  - refused  : KH a refusé la demande
 *  - ended    : relation rompue (par le sub, le KH ou un admin)
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v370 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Considéré installé si la table existe ET les 3 modules sont enregistrés
        if (!$this->db_tools->sql_table_exists($this->table_prefix . 'chastity_keyholders')) {
            return false;
        }
        // Vérifier que les modules existent
        $sql = 'SELECT m.module_id FROM ' . MODULES_TABLE . " m
                INNER JOIN " . MODULES_TABLE . " p ON p.module_id = m.parent_id
                WHERE m.module_mode = 'my_keyholder'
                  AND m.module_class = 'ucp'
                  AND p.module_langname = 'UCP_CHASTITY_TRACKER'";
        $r = $this->db->sql_query($sql);
        $exists_sub = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);

        return (bool) $exists_sub;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3510'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'chastity_keyholders' => [
                    'COLUMNS' => [
                        'kh_id'           => ['UINT', null, 'auto_increment'],
                        'sub_user_id'     => ['UINT', 0],     // le soumis (porte la cage)
                        'kh_user_id'      => ['UINT', 0],     // le keyholder (détient la clé)
                        'status'          => ['VCHAR:10', 'pending'], // pending / active / refused / ended
                        'created_at'      => ['UINT:11', 0],  // timestamp demande
                        'accepted_at'     => ['UINT:11', 0],  // timestamp acceptation
                        'ended_at'        => ['UINT:11', 0],  // timestamp fin
                        'ended_by'        => ['UINT', 0],     // user_id qui a rompu (0 = admin)
                        'end_reason'      => ['VCHAR:255', ''], // raison rupture (optionnel)
                        'notes'           => ['TEXT', ''],    // notes libres
                    ],
                    'PRIMARY_KEY' => 'kh_id',
                    'KEYS' => [
                        'sub_user'    => ['INDEX', 'sub_user_id'],
                        'kh_user'     => ['INDEX', 'kh_user_id'],
                        'status'      => ['INDEX', 'status'],
                    ],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Module UCP "Mon Keyholder"
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['my_keyholder'],
            ]]],
            // Module UCP "Mes soumis"
            ['module.add', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['my_subs'],
            ]]],
            // Module ACP "Duos Keyholder"
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['keyholders'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['ucp', 'UCP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\ucp\main_module',
                'modes'           => ['my_keyholder', 'my_subs'],
            ]]],
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['keyholders'],
            ]]],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [$this->table_prefix . 'chastity_keyholders'],
        ];
    }
}
