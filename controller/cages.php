<?php
/**
 * Chastity Tracker — Public Cages Catalog Controller
 * Affiche le catalogue des cages aux utilisateurs autorisés.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\controller;

class cages
{
    protected $helper;
    protected $template;
    protected $user;
    protected $auth;
    protected $db;
    protected $request;
    protected $container;

    public function __construct($helper, $template, $user, $auth, $db, $request, $container)
    {
        $this->helper    = $helper;
        $this->template  = $template;
        $this->user      = $user;
        $this->auth      = $auth;
        $this->db        = $db;
        $this->request   = $request;
        $this->container = $container;
    }

    public function handle()
    {
        // Vérifier la permission d'accès
        if (!$this->auth->acl_get('u_chastity_view'))
        {
            throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
        }

        $this->user->add_lang_ext('verturin/chastitytracker', 'common');

        // Récupérer les tables
        try {
            $catalog_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_catalog');
            $mfr_table     = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_manufacturers');
            $photos_table  = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_photos');
            try {
                $materials_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_materials');
            } catch (\Exception $e) { $materials_table = ''; }
        } catch (\Exception $e) {
            throw new \phpbb\exception\http_exception(503, 'L\'extension n\'est pas correctement configurée.');
        }

        // Vérifier que la table existe
        $check = $this->db->sql_query("SHOW TABLES LIKE '" . $this->db->sql_escape($catalog_table) . "'");
        $exists = $this->db->sql_fetchrow($check);
        $this->db->sql_freeresult($check);
        if (!$exists)
        {
            throw new \phpbb\exception\http_exception(503, 'Catalogue de cages non disponible.');
        }

        // Filtres
        $filter_brand    = $this->request->variable('brand', '', true);
        $filter_material = $this->request->variable('material', '', true);
        $where = ' WHERE c.is_validated = 1';
        if (!empty($filter_brand))    { $where .= " AND c.cage_brand = '" . $this->db->sql_escape($filter_brand) . "'"; }
        if (!empty($filter_material)) { $where .= " AND c.cage_material = '" . $this->db->sql_escape($filter_material) . "'"; }

        $sql = 'SELECT c.*, m.name AS manufacturer_name,
                       u.username AS added_by_username, u.user_colour AS added_by_colour
                FROM ' . $catalog_table . ' c
                LEFT JOIN ' . $mfr_table . ' m ON m.manufacturer_id = c.manufacturer_id
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = c.added_by_user_id'
                . $where . ' ORDER BY c.cage_name ASC';
        $res = $this->db->sql_query($sql);
        $rows = [];
        while ($r = $this->db->sql_fetchrow($res)) { $rows[] = $r; }
        $this->db->sql_freeresult($res);

        // Verrouillage des proposeurs et commentateurs (pour le badge cadenas)
        $locked_users = [];
        if (!empty($rows))
        {
            $uids = array_filter(array_map(function($r){ return (int) $r['added_by_user_id']; }, $rows));
            if (!empty($uids))
            {
                try {
                    $users_ct_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_users');
                    $sql_l = 'SELECT user_id FROM ' . $users_ct_table . " WHERE chastity_status = 'locked' AND " . $this->db->sql_in_set('user_id', array_unique($uids));
                    $res = $this->db->sql_query($sql_l);
                    while ($lrow = $this->db->sql_fetchrow($res)) { $locked_users[(int) $lrow['user_id']] = true; }
                    $this->db->sql_freeresult($res);
                } catch (\Exception $e) {}
            }
        }

        // Photos principales + toutes les photos par cage pour lightbox
        $main_photos = [];
        $all_photos = [];
        if (!empty($rows))
        {
            $ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
            $sql = 'SELECT catalog_id, filename, is_main FROM ' . $photos_table . '
                    WHERE ' . $this->db->sql_in_set('catalog_id', $ids) . '
                    ORDER BY is_main DESC, photo_id ASC';
            $res = $this->db->sql_query($sql);
            while ($p = $this->db->sql_fetchrow($res)) {
                $cid = (int) $p['catalog_id'];
                if ((int) $p['is_main'] === 1 && !isset($main_photos[$cid])) {
                    $main_photos[$cid] = $p['filename'];
                }
                if (!isset($all_photos[$cid])) { $all_photos[$cid] = []; }
                $all_photos[$cid][] = $p['filename'];
            }
            $this->db->sql_freeresult($res);
        }

        // Map matériaux
        $materials_map = [];
        if (!empty($materials_table)) {
            $check = $this->db->sql_query("SHOW TABLES LIKE '" . $this->db->sql_escape($materials_table) . "'");
            if ($this->db->sql_fetchrow($check)) {
                $this->db->sql_freeresult($check);
                $res = $this->db->sql_query('SELECT material_key, material_name FROM ' . $materials_table);
                while ($r = $this->db->sql_fetchrow($res)) { $materials_map[$r['material_key']] = $r['material_name']; }
                $this->db->sql_freeresult($res);
            } else { $this->db->sql_freeresult($check); }
        }

        // Charger les commentaires validés (table ratings)
        $comments_by_cage = [];
        try {
            $ratings_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_cage_ratings');
            if ($ratings_table && !empty($rows))
            {
                $ids = array_map(function($r){ return (int) $r['catalog_id']; }, $rows);
                $sql = 'SELECT r.catalog_id, r.rating, r.comment, r.user_id, r.created_at,
                               u.username, u.user_colour
                        FROM ' . $ratings_table . ' r
                        LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = r.user_id
                        WHERE r.is_validated = 1 AND r.comment <> ''
                          AND " . $this->db->sql_in_set('r.catalog_id', $ids) . '
                        ORDER BY r.created_at DESC';
                $res = $this->db->sql_query($sql);
                $commenter_ids = [];
                while ($cr = $this->db->sql_fetchrow($res)) {
                    $catid = (int) $cr['catalog_id'];
                    if (!isset($comments_by_cage[$catid])) { $comments_by_cage[$catid] = []; }
                    $comments_by_cage[$catid][] = $cr;
                    if ((int) $cr['user_id'] > 0) { $commenter_ids[(int) $cr['user_id']] = true; }
                }
                $this->db->sql_freeresult($res);

                // Compléter locked_users avec commentateurs verrouillés
                if (!empty($commenter_ids))
                {
                    try {
                        $users_ct_table = $this->container->getParameter('verturin.chastitytracker.tables.chastity_users');
                        $sql_l = 'SELECT user_id FROM ' . $users_ct_table . " WHERE chastity_status = 'locked' AND " . $this->db->sql_in_set('user_id', array_keys($commenter_ids));
                        $res = $this->db->sql_query($sql_l);
                        while ($lrow = $this->db->sql_fetchrow($res)) { $locked_users[(int) $lrow['user_id']] = true; }
                        $this->db->sql_freeresult($res);
                    } catch (\Exception $e) {}
                }
            }
        } catch (\Exception $e) {}

        foreach ($rows as $row)
        {
            $cid = (int) $row['catalog_id'];
            $mkey = $row['cage_material'];
            $added_by_uid = (int) ($row['added_by_user_id'] ?? 0);
            $this->template->assign_block_vars('catalog', [
                'NAME'         => $row['cage_name'],
                'BRAND'        => $row['cage_brand'],
                'MATERIAL'     => isset($materials_map[$mkey]) ? $materials_map[$mkey] : $mkey,
                'TYPE'         => $row['cage_type'],
                'DESCRIPTION'  => $row['cage_description'],
                'MANUFACTURER' => $row['manufacturer_name'] ?: '',
                'USAGE_COUNT'  => (int) $row['usage_count'],
                'AVG_RATING'   => isset($row['avg_rating']) ? (float) $row['avg_rating'] : 0,
                'RATING_COUNT' => isset($row['rating_count']) ? (int) $row['rating_count'] : 0,
                'MAIN_PHOTO'   => isset($main_photos[$cid]) ? $main_photos[$cid] : '',
                'PHOTO_COUNT'  => isset($all_photos[$cid]) ? count($all_photos[$cid]) : 0,
                'ADDED_BY'     => $added_by_uid ? get_username_string('full', $added_by_uid, $row['added_by_username'] ?: '?', $row['added_by_colour'] ?: '') : '',
                'ADDED_BY_LOCKED' => isset($locked_users[$added_by_uid]),
                'COMMENT_COUNT' => isset($comments_by_cage[$cid]) ? count($comments_by_cage[$cid]) : 0,
            ]);

            // Sous-bloc photos pour lightbox
            if (isset($all_photos[$cid])) {
                foreach ($all_photos[$cid] as $filename) {
                    $this->template->assign_block_vars('catalog.photos', [
                        'FILENAME' => $filename,
                    ]);
                }
            }

            // Sous-bloc commentaires
            if (isset($comments_by_cage[$cid])) {
                foreach ($comments_by_cage[$cid] as $cm) {
                    $this->template->assign_block_vars('catalog.comments', [
                        'AUTHOR'        => get_username_string('full', (int) $cm['user_id'], $cm['username'] ?: '?', $cm['user_colour'] ?: ''),
                        'AUTHOR_LOCKED' => isset($locked_users[(int) $cm['user_id']]),
                        'RATING'        => (int) $cm['rating'],
                        'COMMENT'       => $cm['comment'],
                        'DATE'          => date('d/m/Y', (int) $cm['created_at']),
                    ]);
                }
            }
        }

        // Filtres
        $res = $this->db->sql_query('SELECT DISTINCT cage_brand FROM ' . $catalog_table . " WHERE cage_brand <> '' ORDER BY cage_brand ASC");
        while ($r = $this->db->sql_fetchrow($res)) { $this->template->assign_block_vars('brands', ['NAME' => $r['cage_brand']]); }
        $this->db->sql_freeresult($res);

        $res = $this->db->sql_query('SELECT DISTINCT cage_material FROM ' . $catalog_table . " WHERE cage_material <> '' ORDER BY cage_material ASC");
        while ($r = $this->db->sql_fetchrow($res)) { $this->template->assign_block_vars('materials', ['NAME' => $r['cage_material']]); }
        $this->db->sql_freeresult($res);

        $this->template->assign_vars([
            'FILTER_BRAND'    => $filter_brand,
            'FILTER_MATERIAL' => $filter_material,
            'TOTAL'           => count($rows),
            'BOARD_URL'       => generate_board_url() . '/',
            'U_CATALOG'       => $this->helper->route('verturin_chastitytracker_cages'),
        ]);

        return $this->helper->render('@verturin_chastitytracker/chastity_public_cages.html', $this->user->lang('UCP_CHASTITY_CAGE_CATALOG'));
    }
}
