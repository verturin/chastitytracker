<?php
/**
 *
 * Chastity Tracker Extension - Clean modules migration
 *
 * @copyright (c) 2024
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace verturin\chastitytracker\migrations;

class clean_modules extends \phpbb\db\migration\migration
{
    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v320\v320');
    }

    public function update_data()
    {
        return array(
            // Supprimer tous les anciens modules s'ils existent
            array('custom', array(array($this, 'remove_old_modules'))),
        );
    }
    
    public function remove_old_modules()
    {
        // Supprimer les modules UCP
        $sql = 'DELETE FROM ' . MODULES_TABLE . "
            WHERE module_basename = '\\verturin\\chastitytracker\\ucp\\main_module'
                OR module_basename = '\\vendor\\chastitytracker\\ucp\\main_module'";
        $this->db->sql_query($sql);
        
        // Supprimer les catégories UCP
        $sql = 'DELETE FROM ' . MODULES_TABLE . "
            WHERE module_class = 'ucp'
                AND module_langname = 'UCP_CHASTITY_TRACKER'";
        $this->db->sql_query($sql);
        
        // Supprimer les modules ACP
        $sql = 'DELETE FROM ' . MODULES_TABLE . "
            WHERE module_basename = '\\verturin\\chastitytracker\\acp\\main_module'
                OR module_basename = '\\vendor\\chastitytracker\\acp\\main_module'";
        $this->db->sql_query($sql);
        
        // Supprimer les catégories ACP
        $sql = 'DELETE FROM ' . MODULES_TABLE . "
            WHERE module_class = 'acp'
                AND module_langname = 'ACP_CHASTITY_TRACKER'";
        $this->db->sql_query($sql);
    }
}
