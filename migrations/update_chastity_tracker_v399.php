<?php
/**
 * Chastity Tracker — Migration v3.9.9
 * CTR — Contrat de chasteté : mot de sécurité.
 *
 * Permet à l'une ou l'autre partie du contrat de le suspendre IMMÉDIATEMENT
 * en saisissant le mot de sécurité convenu. Seule l'AUTRE personne (celle qui
 * n'a pas invoqué le mot) peut ensuite lever la suspension — un frein de
 * secours asymétrique, cohérent avec l'usage d'un mot de sécurité classique.
 *
 * Colonnes ajoutées à chastity_contracts :
 *  - safeword_hash           : le mot de sécurité, hashé (jamais en clair)
 *  - safeword_suspended_by   : user_id de la personne qui a invoqué le mot
 *                              (0 = pas de suspension en cours par ce moyen)
 *  - safeword_suspended_time : horodatage de l'invocation
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v399 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contracts', 'safeword_hash');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v398'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'safeword_hash'           => ['VCHAR:255', ''],
                    'safeword_suspended_by'   => ['UINT', 0],
                    'safeword_suspended_time' => ['UINT:11', 0],
                ],
            ],
        ];
    }
}
