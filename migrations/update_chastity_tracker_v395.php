<?php
/**
 * Chastity Tracker — Migration v3.9.5
 * CTR — Contrat de chasteté : correction du type des colonnes timestamp.
 *
 * La migration v392 avait déclaré created_time/updated_time/sent_time/
 * validated_time/ended_time avec le type abrégé UINT au lieu de UINT:11
 * (utilisé partout ailleurs dans l'extension pour les timestamps Unix).
 * Résultat : "Out of range value for column 'created_time'" à l'insertion,
 * le type généré par UINT seul étant trop étroit pour un timestamp de 2026.
 *
 * Cette migration élargit les colonnes déjà créées par v392 chez les
 * utilisateurs ayant déjà activé l'extension avant ce correctif.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v395 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['chastity_ctr_v395_done']);
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v394'];
    }

    public function update_schema()
    {
        return [
            'change_columns' => [
                $this->table_prefix . 'chastity_contract_articles' => [
                    'created_time' => ['UINT:11', 0],
                    'updated_time' => ['UINT:11', 0],
                ],
                $this->table_prefix . 'chastity_contracts' => [
                    'sent_time'      => ['UINT:11', 0],
                    'validated_time' => ['UINT:11', 0],
                    'ended_time'     => ['UINT:11', 0],
                    'created_time'   => ['UINT:11', 0],
                    'updated_time'   => ['UINT:11', 0],
                ],
                $this->table_prefix . 'chastity_contract_links' => [
                    'created_time' => ['UINT:11', 0],
                    'updated_time' => ['UINT:11', 0],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['chastity_ctr_v395_done', 1]],
        ];
    }
}
