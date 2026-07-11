<?php
/**
 * Chastity Tracker — Migration v3.10.2
 * CTR — Contrat de chasteté : le mot de sécurité doit être visible EN CLAIR
 * dans le contrat exporté, car il doit être connu des DEUX parties (ce n'est
 * pas un secret entre elles, seulement vis-à-vis de tiers). Ajoute une
 * colonne dédiée en clair, en plus du hash existant conservé pour la
 * vérification lors de l'invocation.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3102 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contracts', 'safeword_plain');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3101'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contracts' => [
                    'safeword_plain' => ['VCHAR:255', ''],
                ],
            ],
        ];
    }
}
