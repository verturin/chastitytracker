<?php
/**
 * Chastity Tracker — Migration v3.9.6
 * CTR — Contrat de chasteté : ajout du module ACP de supervision (mode
 * "contract"), permettant à l'administration de voir tous les contrats
 * (tous membres, tous statuts) et de forcer la fin d'un contrat si
 * nécessaire.
 *
 * Note : cette migration N'essaie PAS de repositionner le module "backup"
 * en dernier (contrairement à la règle habituelle), car la tentative de
 * module.remove + module.add pour ce faire a échoué en pratique avec
 * l'erreur "Un module porte déjà ce nom : ACP_CHASTITY_BACKUP" — le
 * module.remove ne supprimait pas correctement l'entrée existante avant
 * la recréation. "contract" apparaîtra donc juste avant "backup" dans le
 * menu, ce qui reste parfaitement fonctionnel, seul l'ordre esthétique
 * change.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v396 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        $sql = 'SELECT module_id FROM ' . MODULES_TABLE
             . " WHERE module_basename LIKE '%chastitytracker%acp%main_module%'"
             . " AND module_mode = 'contract'";
        $result = $this->db->sql_query($sql);
        $row    = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return (bool) $row;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v395'];
    }

    public function update_data()
    {
        return [
            ['module.add', ['acp', 'ACP_CHASTITY_TRACKER', [
                'module_basename' => '\verturin\chastitytracker\acp\main_module',
                'modes'           => ['contract'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['acp', 'ACP_CHASTITY_TRACKER', 'contract']],
        ];
    }
}
