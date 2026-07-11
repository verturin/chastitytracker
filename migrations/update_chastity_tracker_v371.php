<?php
/**
 * Chastity Tracker — Migration v3.7.1
 * Enregistre les 3 modules Keyholder (UCP my_keyholder, my_subs, ACP keyholders).
 *
 * Cette migration sépare l'enregistrement des modules de la création de la table
 * (v3.7.0) car phpBB ne rejoue jamais une migration une fois marquée comme installée
 * dans phpbb_migrations, même si effectively_installed retourne false.
 *
 * Sans ceci, les forums qui ont activé v3.7.0 avant qu'elle inclue update_data()
 * n'auraient jamais les menus visibles.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v371 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Considéré installé si le module UCP "my_keyholder" est enregistré
        $sql = 'SELECT m.module_id FROM ' . MODULES_TABLE . " m
                INNER JOIN " . MODULES_TABLE . " p ON p.module_id = m.parent_id
                WHERE m.module_mode = 'my_keyholder'
                  AND m.module_class = 'ucp'
                  AND p.module_langname = 'UCP_CHASTITY_TRACKER'";
        $r = $this->db->sql_query($sql);
        $exists = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);

        return (bool) $exists;
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v370'];
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
}
