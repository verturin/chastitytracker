<?php
/**
 * Chastity Tracker — Migration v3.4.3
 * V1 Badge cadenas + API JSON + Inactivité auto
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v343 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists(
            $this->table_prefix . 'chastity_users',
            'inactivity_warned'
        );
    }

    public static function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\install_chastity_tracker'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_users' => [
                    'inactivity_warned' => ['TINT:1', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'chastity_users' => [
                    'inactivity_warned',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['permission.add', ['u_chastity_lock_badge', true]],
            ['permission.permission_set', ['ROLE_USER_STANDARD', 'u_chastity_lock_badge']],
            ['config.add', ['chastity_badge_enabled', 1]],
            ['config.add', ['chastity_inactivity_enabled', 0]],
            ['config.add', ['chastity_inactivity_warn_days', 10]],
            ['config.add', ['chastity_inactivity_cancel_days', 20]],
            ['config.add', ['chastity_inactivity_warn_message', 'Bonjour {USERNAME}, vous ne vous êtes pas connecté depuis {DAYS} jours. Votre période de mise en cage sera automatiquement annulée dans {REMAINING} jours si vous ne vous connectez pas.']],
            ['config.add', ['chastity_inactivity_cancel_message', 'Bonjour {USERNAME}, votre période de mise en cage a été automatiquement annulée car vous ne vous êtes pas connecté depuis {DAYS} jours.']],
            ['config.add', ['chastity_inactivity_last_gc', 0, true]],
        ];
    }

    public function revert_data()
    {
        return [
            ['permission.remove', ['u_chastity_lock_badge']],
            ['config.remove', ['chastity_badge_enabled']],
            ['config.remove', ['chastity_inactivity_enabled']],
            ['config.remove', ['chastity_inactivity_warn_days']],
            ['config.remove', ['chastity_inactivity_cancel_days']],
            ['config.remove', ['chastity_inactivity_warn_message']],
            ['config.remove', ['chastity_inactivity_cancel_message']],
            ['config.remove', ['chastity_inactivity_last_gc']],
        ];
    }
}
