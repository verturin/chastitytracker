<?php
/**
 * Chastity Tracker — Migration v3.9.7
 * CTR — Contrat de chasteté : bibliothèque d'articles modèles gérée en ACP.
 *
 * Ajoute deux colonnes à chastity_contract_articles :
 *  - is_global  : article "modèle" créé par l'ACP, visible et proposable par
 *                 tous les membres (par opposition à un article perso, créé
 *                 par un membre pour son propre contrat)
 *  - category   : regroupement thématique pour l'affichage (exclusivité,
 *                 dispositif, discipline, communication, etc.)
 *
 * Peuple ensuite la bibliothèque avec un premier jeu d'articles modèles,
 * couvrant les thèmes habituels d'un contrat de chasteté, reformulés de
 * façon neutre et adaptable (propriété d'aucune source externe).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\migrations;

class update_chastity_tracker_v397 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_column_exists($this->table_prefix . 'chastity_contract_articles', 'is_global');
    }

    static public function depends_on()
    {
        return ['\verturin\chastitytracker\migrations\update_chastity_tracker_v396'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'chastity_contract_articles' => [
                    'is_global' => ['BOOL', 0],
                    'category'  => ['VCHAR:64', ''],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'seed_default_articles']]],
        ];
    }

    public function seed_default_articles()
    {
        $table = $this->table_prefix . 'chastity_contract_articles';

        // N'insère les modèles par défaut qu'une seule fois (si aucun article
        // global n'existe déjà), pour ne jamais dupliquer au fil des mises à jour.
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $table . ' WHERE is_global = 1';
        $result = $this->db->sql_query($sql);
        $count  = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);

        if ($count > 0) {
            return;
        }

        $now = time();
        $articles = [
            // ── Exclusivité et engagement ──
            ['category' => 'exclusivite', 'title' => 'Exclusivité affective et sexuelle',
             'body' => "L'encagé s'engage à réserver son attention, son désir et sa fidélité à sa Keyholder, sans intérêt affectif ou sexuel envers une tierce personne pendant la durée du contrat."],
            ['category' => 'exclusivite', 'title' => 'Priorité donnée à la relation',
             'body' => "Les deux parties s'engagent à faire de leur relation une priorité, en y consacrant le temps et l'attention nécessaires."],

            // ── Dispositif de chasteté ──
            ['category' => 'dispositif', 'title' => 'Choix du dispositif par la Keyholder',
             'body' => "Le choix du modèle de cage, sa taille et son niveau de contrainte reviennent à la Keyholder, en tenant compte du confort et de la sécurité de l'encagé."],
            ['category' => 'dispositif', 'title' => 'Port permanent ou alterné',
             'body' => "La durée du port (permanent, alterné, ou selon des périodes définies) est décidée d'un commun accord, avec la possibilité pour la Keyholder de l'ajuster dans le temps."],
            ['category' => 'dispositif', 'title' => 'Retrait pour raisons médicales ou d\'hygiène',
             'body' => "Le dispositif peut être retiré à tout moment pour des raisons médicales, d'hygiène, ou de sécurité, sans que cela ne remette en cause l'esprit du contrat."],

            // ── Gestion des clés ──
            ['category' => 'cles', 'title' => 'Gestion exclusive des clés',
             'body' => "La Keyholder conserve la gestion des clés ou du code de verrouillage. L'encagé n'y a pas d'accès direct pendant les périodes de verrouillage convenues."],
            ['category' => 'cles', 'title' => 'Clé de secours accessible en cas d\'urgence',
             'body' => "Une clé ou un moyen de déverrouillage de secours reste accessible à tout moment en cas d'urgence médicale, pour la sécurité de l'encagé."],

            // ── Vie sexuelle et intimité ──
            ['category' => 'intimite', 'title' => 'Initiative des rapports laissée à la Keyholder',
             'body' => "L'initiative des moments d'intimité revient principalement à la Keyholder, l'encagé restant disponible et attentif à ses désirs."],
            ['category' => 'intimite', 'title' => 'Rythme des soulagements décidé ensemble',
             'body' => "La fréquence et les modalités des soulagements de l'encagé sont discutées et décidées d'un commun accord, révisables à tout moment."],
            ['category' => 'intimite', 'title' => 'Attention portée au plaisir de la Keyholder',
             'body' => "L'encagé s'efforce d'être attentif et disponible pour le plaisir et le bien-être de sa Keyholder en priorité."],

            // ── Discipline et règles de vie ──
            ['category' => 'discipline', 'title' => 'Respect des consignes établies',
             'body' => "L'encagé s'engage à respecter les règles définies dans le contrat et les consignes données par sa Keyholder au quotidien."],
            ['category' => 'discipline', 'title' => 'Conséquences proportionnées en cas de manquement',
             'body' => "En cas de non-respect des règles, la Keyholder peut appliquer une conséquence proportionnée et discutée à l'avance (privation, tâche, prolongation de port), sans jamais recourir à l'humiliation ou à la violence."],
            ['category' => 'discipline', 'title' => 'Participation aux tâches du quotidien',
             'body' => "L'encagé s'engage à participer activement aux tâches domestiques ou du quotidien convenues avec sa Keyholder."],

            // ── Communication et sécurité ──
            ['category' => 'communication', 'title' => 'Communication honnête et régulière',
             'body' => "Les deux parties s'engagent à communiquer honnêtement sur leurs ressentis, leurs limites et leurs besoins, à intervalles réguliers."],
            ['category' => 'communication', 'title' => 'Droit d\'exprimer un malaise à tout moment',
             'body' => "Chacune des parties peut à tout moment exprimer un inconfort, une gêne ou un besoin de pause, sans crainte de jugement."],
            ['category' => 'communication', 'title' => 'Mot de sécurité toujours respecté',
             'body' => "L'usage du mot de sécurité par l'une ou l'autre partie entraîne l'arrêt ou la suspension immédiate des règles en cours, sans discussion préalable."],

            // ── Confidentialité et cadre ──
            ['category' => 'cadre', 'title' => 'Confidentialité de la relation',
             'body' => "Le contenu de ce contrat et les détails de la relation restent strictement privés, sauf accord explicite des deux parties pour les partager."],
            ['category' => 'cadre', 'title' => 'Discrétion en dehors du cadre privé',
             'body' => "En dehors du cadre privé convenu, les deux parties s'engagent à la discrétion sur leur relation."],
            ['category' => 'cadre', 'title' => 'Révision possible du contrat',
             'body' => "Le contrat peut être révisé à tout moment d'un commun accord ; en cas de désaccord, un nouveau contrat peut être proposé pour remplacer l'ancien."],
        ];

        foreach ($articles as $a) {
            $this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', [
                'user_id'      => 0,
                'title'        => $a['title'],
                'body'         => $a['body'],
                'is_draft'     => 0,
                'is_global'    => 1,
                'category'     => $a['category'],
                'created_time' => $now,
                'updated_time' => $now,
            ]));
        }
    }
}
