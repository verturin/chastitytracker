<?php
/**
 * Chastity Tracker — Migration v3.9.3
 * CTR — Contrat de chasteté : ajout de la permission ACL dédiée
 * u_chastity_contract. Volontairement PAS accordée à ROLE_USER_STANDARD :
 * cette fonctionnalité doit être activée manuellement par l'admin sur les
 * groupes de son choix (permission ACP > Permissions).
 *
 * Le module UCP correspondant (mode "contract") est ajouté séparément dans
 * la migration v394, car v393 peut déjà avoir été jouée chez certains
 * utilisateurs et ne sera jamais rejouée pour inclure un module.add ajouté
 * après coup.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v393 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_ctr_v393_done']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v392'];
    }

    public function update_data()
    {
        return [
            ['permission.add', ['u_chastity_contract', true]],
            ['config.add', ['chastity_ctr_v393_done', 1]],
        ];
    }
}
