<?php
/**
 * Chastity Tracker — Migration v3.10.1
 * CTR — Contrat de chasteté : suivi du traitement admin des articles
 * personnalisés proposés par les membres.
 *
 * Ajoute admin_review_status à chastity_contract_links, distinct de
 * proposal_status (qui concerne la validation entre les 2 parties du
 * contrat) : suit si l'admin a déjà statué sur l'ajout de cet article
 * personnalisé à la bibliothèque de modèles réutilisables.
 * Valeurs : 'none' (pas d'article personnalisé / pas concerné),
 * 'pending' (en attente d'examen admin), 'approved', 'rejected'.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v3101 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contract_links', 'admin_review_status');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v3100'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contract_links' => [
                    'admin_review_status' => ['VCHAR:20', 'none'],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'mark_custom_articles_pending']]],
        ];
    }

    public function mark_custom_articles_pending()
    {
        // Les articles personnalisés déjà existants (article_id = 0) passent
        // en attente d'examen admin, pour ne pas perdre ceux créés avant
        // cette migration.
        $this->db->sql_query('UPDATE ' . $this->table_prefix . "chastity_contract_links
            SET admin_review_status = 'pending'
            WHERE article_id = 0");
    }
}
