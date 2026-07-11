<?php
/**
 * Chastity Tracker — Migration v3.8.1f
 * Badges anniversaire : encagé le jour de son anniversaire ou de celui
 * de sa keyholder. Configs : activation + image/libellé pour chaque type.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v381f extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_birthday_enabled']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v381e'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_birthday_enabled', 1]],
            ['config.add', ['chastity_birthday_self_label', 'Anniversaire']],
            ['config.add', ['chastity_birthday_self_image', '']],
            ['config.add', ['chastity_birthday_kh_label', 'Anniversaire keyholder']],
            ['config.add', ['chastity_birthday_kh_image', '']],
        ];
    }
}
