<?php
/**
 * Chastity Tracker — Migration v3.14.15
 * CTR — Contrat de chasteté : genre de la Keyholder externe.
 *
 * Permet d'accorder correctement le contrat (LA/LE KEYHOLDER, Madame/
 * Monsieur, "dite"/"dit"...) lorsque la Keyholder n'est pas inscrite sur le
 * forum. Pour une Keyholder INSCRITE, le genre est déjà disponible via
 * chastity_user_prefs.gender (colonne existante, migration v3.7.9) et n'a
 * donc pas besoin d'être dupliqué ici.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v31415 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contracts', 'kh_external_gender');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3107'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'kh_external_gender' => ['VCHAR:8', 'male'],
                ],
            ],
        ];
    }
}
