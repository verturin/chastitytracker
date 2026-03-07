<?php
namespace verturin\chastitytracker\migrations;

class v3_0_9_add_cache_and_history extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_cache') &&
               $this->db_tools->sql_table_exists($this->table_prefix . 'chastity_history');
    }

    public static function depends_on()
    {
        return array('\verturin\chastitytracker\migrations\install_chastity_tracker');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'chastity_cache' => array(
                    'COLUMNS' => array(
                        'user_id' => array('UINT', 0),
                        'days_current_period' => array('UINT', 0),
                        'days_current_year' => array('UINT', 0),
                        'days_since_last_end' => array('UINT', 0),
                        'last_update' => array('TIMESTAMP', 0),
                        'next_update' => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'user_id',
                    'KEYS' => array(
                        'cc_next_update' => array('INDEX', 'next_update'),
                    ),
                ),
                $this->table_prefix . 'chastity_history' => array(
                    'COLUMNS' => array(
                        'id' => array('UINT', null, 'auto_increment'),
                        'user_id' => array('UINT', 0),
                        'year' => array('UINT', 0),
                        'total_days' => array('UINT', 0),
                        'last_update' => array('TIMESTAMP', 0),
                    ),
                    'PRIMARY_KEY' => 'id',
                    'KEYS' => array(
                        'ch_user_year' => array('UNIQUE', array('user_id', 'year')),
                        'ch_year' => array('INDEX', 'year'),
                    ),
                ),
            ),
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'chastity_cache',
                $this->table_prefix . 'chastity_history',
            ),
        );
    }

    public function update_data()
    {
        return array(
            array('config.add', array('chastity_last_cache_update', 0)),
            array('config.add', array('chastity_last_history_update', 0)),
        );
    }
}
