<?php
/**
 * Chastity Tracker — Générateur de document du contrat de chasteté (CTR)
 * Fournit deux méthodes de sortie, sélectionnables en ACP :
 *  - HTML imprimable (aucune dépendance, impression navigateur -> PDF)
 *  - PDF réel via TCPDF (nécessite composer require tecnickcom/tcpdf)
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\service;

/**
 * Sous-classe TCPDF avec pied de page personnalisé : rappel des deux
 * signatures, date de signature, et numérotation des pages — répété sur
 * chaque page A4 du contrat exporté.
 * Chargée uniquement si TCPDF est disponible (voir generate_pdf()).
 */
if (class_exists('\TCPDF') && !class_exists('\verturin\chastitytracker\service\chastity_contract_tcpdf')) {
    class chastity_contract_tcpdf extends \TCPDF
    {
        public $chastity_footer_encage = '';
        public $chastity_footer_kh = '';
        public $chastity_footer_date = '';

        public function Footer()
        {
            $this->SetY(-20);
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 4, 'Signé par ' . $this->chastity_footer_encage . ' et ' . $this->chastity_footer_kh . ' — le ' . $this->chastity_footer_date, 0, 1, 'C');
            $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }
}

class contract_pdf_generator
{
    protected $db;
    protected $contracts_table;
    protected $links_table;
    protected $categories_table;
    protected $prefs_table;

    public function __construct($db, $contracts_table, $links_table, $categories_table, $prefs_table)
    {
        $this->db = $db;
        $this->contracts_table = $contracts_table;
        $this->links_table = $links_table;
        $this->categories_table = $categories_table;
        $this->prefs_table = $prefs_table;
    }

    /**
     * Rassemble toutes les données nécessaires à l'affichage du contrat :
     * infos générales + articles groupés et numérotés par catégorie.
     * Ne renvoie QUE les articles validés (approved) — un contrat exporté
     * ne doit montrer que ce qui est effectivement acté.
     */
    /**
     * @param int  $contract_id
     * @param bool $include_pending Si true, inclut aussi les articles en
     *             attente/refusés (aperçu de travail). Si false (défaut),
     *             ne renvoie que les articles VALIDÉS (export officiel).
     */
    public function build_contract_data($contract_id, $include_pending = false)
    {
        $res = $this->db->sql_query('SELECT c.*, ue.username AS encage_username
            FROM ' . $this->contracts_table . ' c
            LEFT JOIN ' . USERS_TABLE . ' ue ON ue.user_id = c.encage_user_id
            WHERE c.contract_id = ' . (int) $contract_id);
        $contract = $this->db->sql_fetchrow($res);
        $this->db->sql_freeresult($res);

        if (!$contract) {
            return null;
        }

        $kh_name = '';
        $kh_gender = 'male';
        if ((int) $contract['kh_user_id'] > 0) {
            $kh_res = $this->db->sql_query('SELECT username FROM ' . USERS_TABLE . '
                WHERE user_id = ' . (int) $contract['kh_user_id']);
            $kh_row = $this->db->sql_fetchrow($kh_res);
            $this->db->sql_freeresult($kh_res);
            $kh_name = $kh_row ? $kh_row['username'] : '';

            // Keyholder INSCRITE : genre déjà défini dans ses préférences
            // d'extension (chastity_user_prefs.gender), pas de duplication.
            $kh_g_res = $this->db->sql_query('SELECT gender FROM ' . $this->prefs_table . '
                WHERE user_id = ' . (int) $contract['kh_user_id']);
            $kh_g_row = $this->db->sql_fetchrow($kh_g_res);
            $this->db->sql_freeresult($kh_g_res);
            if ($kh_g_row && $kh_g_row['gender'] === 'female') {
                $kh_gender = 'female';
            }
        } elseif ($contract['kh_external_name'] !== '') {
            $kh_name = $contract['kh_external_name'];
            // Keyholder EXTERNE : genre saisi par l'encagé au moment de la
            // renseigner (colonne kh_external_gender, migration v3.14.15).
            if (isset($contract['kh_external_gender']) && $contract['kh_external_gender'] === 'female') {
                $kh_gender = 'female';
            }
        }

        // Genre de l'encagé — même logique que la Keyholder inscrite.
        $encage_gender = 'male';
        $eg_res = $this->db->sql_query('SELECT gender FROM ' . $this->prefs_table . '
            WHERE user_id = ' . (int) $contract['encage_user_id']);
        $eg_row = $this->db->sql_fetchrow($eg_res);
        $this->db->sql_freeresult($eg_res);
        if ($eg_row && $eg_row['gender'] === 'female') {
            $encage_gender = 'female';
        }

        $cat_labels = [];
        $cat_order = [];
        $cat_res = $this->db->sql_query('SELECT category_key, label FROM ' . $this->categories_table . ' ORDER BY sort_order ASC');
        while ($cat_row = $this->db->sql_fetchrow($cat_res)) {
            $cat_labels[$cat_row['category_key']] = $cat_row['label'];
            $cat_order[] = $cat_row['category_key'];
        }
        $this->db->sql_freeresult($cat_res);

        // Un contrat DÉFINITIF (signé/actif, ou clos) n'est plus un aperçu de
        // travail, même si l'appelant demande include_pending=true (ex: bouton
        // "Aperçu" générique) : il n'y a de toute façon plus d'articles en
        // attente/refusés possibles sur un contrat actif (cf. blocage de la
        // demande de modification), et l'affichage doit refléter le document
        // définitif, pas un brouillon en cours de négociation.
        $is_definitive = in_array($contract['status'], ['active', 'ended', 'replaced'], true);
        $effective_include_pending = $include_pending && !$is_definitive;

        $status_filter = $effective_include_pending ? "proposal_status IN ('approved', 'pending', 'rejected')" : "proposal_status = 'approved'";
        $art_res = $this->db->sql_query("SELECT * FROM " . $this->links_table . "
            WHERE contract_id = " . (int) $contract_id . " AND $status_filter
            ORDER BY category ASC, sort_order ASC, created_time ASC");
        $articles_by_cat = [];
        while ($art_row = $this->db->sql_fetchrow($art_res)) {
            $cat_key = ($art_row['category'] !== '') ? $art_row['category'] : 'personnalise';
            $articles_by_cat[$cat_key][] = $art_row;
        }
        $this->db->sql_freeresult($art_res);

        // Regrouper les catégories dans l'ordre réel défini en ACP
        // (sort_order), "personnalisé" toujours en dernier quel que soit
        // son sort_order propre.
        $ordered_cat_keys = $cat_order;
        if (($idx = array_search('personnalise', $ordered_cat_keys, true)) !== false) {
            unset($ordered_cat_keys[$idx]);
        }
        $ordered_cat_keys[] = 'personnalise';

        $categories_out = [];
        $cat_number = 0;
        foreach ($ordered_cat_keys as $cat_key) {
            if (!isset($articles_by_cat[$cat_key])) { continue; }
            $rows = $articles_by_cat[$cat_key];
            $cat_number++;
            $arts = [];
            $art_number = 0;
            foreach ($rows as $row) {
                $art_number++;
                $arts[] = [
                    'number' => $cat_number . '.' . $art_number,
                    'title'  => $row['article_title'],
                    'body'   => $row['article_body'],
                    'status' => $row['proposal_status'],
                ];
            }
            $categories_out[] = [
                'number'   => $cat_number,
                'label'    => $cat_labels[$cat_key] ?? $cat_key,
                'articles' => $arts,
            ];
        }

        return [
            'contract_id'   => (int) $contract['contract_id'],
            'encage_name'   => $contract['encage_username'],
            'encage_gender' => $encage_gender,
            'kh_name'       => $kh_name,
            'kh_gender'     => $kh_gender,
            'status'        => $contract['status'],
            'created_time'  => (int) $contract['created_time'],
            'validated_time'=> (int) $contract['validated_time'],
            'categories'    => $categories_out,
            'is_preview'    => $effective_include_pending,
            'safeword'      => $contract['safeword_plain'],
        ];
    }

    /**
     * Termes d'accord grammatical (civilité, article, "dit/dite"...) selon
     * le genre, pour le préambule et la page de signature. Convention
     * identique au reste de l'extension (controller/badge.php,
     * event/main_listener.php) : seul 'female' déclenche l'accord féminin,
     * tout le reste (male/other/valeur absente) reste au masculin par
     * défaut.
     */
    private function civility_terms($gender, $role)
    {
        $is_female = ($gender === 'female');
        if ($role === 'kh') {
            return $is_female
                ? ['civility' => 'Madame',  'dit' => 'dite', 'label' => 'LA KEYHOLDER', 'alt_label' => 'LA MAÎTRESSE',  'possessive' => 'détentrice', 'approved' => 'approuvée']
                : ['civility' => 'Monsieur','dit' => 'dit',  'label' => 'LE KEYHOLDER', 'alt_label' => 'LE MAÎTRE',     'possessive' => 'détenteur',  'approved' => 'approuvé'];
        }
        // role === 'encage'
        return $is_female
            ? ['civility' => 'Madame',  'dit' => 'dite', 'label' => 'L\'ENCAGÉE', 'approved' => 'approuvée']
            : ['civility' => 'Monsieur','dit' => 'dit',  'label' => 'L\'ENCAGÉ',  'approved' => 'approuvé'];
    }

    /**
     * Génère la page HTML imprimable (aucune dépendance externe requise).
     * Le membre imprime cette page depuis son navigateur pour l'enregistrer
     * en PDF (Ctrl+P -> Enregistrer au format PDF).
     */
    public function generate_html($data)
    {
        $created = $data['created_time'] > 0 ? date('d/m/Y', $data['created_time']) : '';
        $validated = $data['validated_time'] > 0 ? date('d/m/Y', $data['validated_time']) : '';
        $is_preview = !empty($data['is_preview']);
        $signed_date = ($validated !== '') ? $validated : ($created !== '' ? $created : date('d/m/Y'));

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>Contrat de chasteté' . ($is_preview ? ' — Aperçu' : '') . '</title>';

        // ── Pagination fiable : Paged.js (polyfill JS) ──
        // Trois tentatives précédentes ont échoué avec le CSS d'impression
        // natif du navigateur : "position: fixed" + valeur négative,
        // counter(page)/counter(pages) hors contexte @page, puis les boîtes
        // de marge @page elles-mêmes (@bottom-left/@bottom-right) et
        // "break-after: avoid" sur les titres de section. La cause commune :
        // Chrome et Firefox n'implémentent qu'une PARTIE de la spécification
        // CSS Paged Media lors d'une impression classique (Ctrl+P), et pas
        // de façon fiable. Paged.js est une bibliothèque JS qui simule
        // CORRECTEMENT toute cette spécification directement dans la page
        // AVANT l'impression : elle découpe réellement le contenu en pages,
        // répète les boîtes de marge (pied de page, numérotation) sur
        // chacune, et respecte les règles de regroupement (titre + premier
        // article). L'utilisateur imprime ensuite (Ctrl+P -> Enregistrer en
        // PDF) une page DÉJÀ correctement paginée par Paged.js, donc le
        // rendu imprimé est fidèle à ce qui s'affiche à l'écran.
        $page_footer_rule = '';
        if (!$is_preview) {
            $footer_text = 'Signé par ' . addslashes($data['encage_name']) . ' et ' . addslashes($data['kh_name']) . ' — le ' . $signed_date;
            $page_footer_rule = '
            @page {
                @bottom-left  { content: "' . $footer_text . '"; font-family: Georgia, serif; font-size: 8pt; color: #777; }
                @bottom-right { content: "Page " counter(page) " / " counter(pages); font-family: Georgia, serif; font-size: 8pt; color: #777; }
            }';
        }

        $html .= '<script src="https://unpkg.com/pagedjs/dist/paged.polyfill.js"></script>';
        $html .= '<style>
            @page { size: A4; margin: 20mm 18mm 26mm 18mm; }' . $page_footer_rule . '
            body { font-family: Georgia, serif; color: #2a2a2a; line-height: 1.6; }
            .contract-wrap { max-width: 800px; margin: 0 auto; }
            h1 { text-align: center; color: #2E4057; border-bottom: 3px solid #BC2A4D; padding-bottom: 15px; }
            .meta { text-align: center; color: #666; margin-bottom: 30px; font-size: 0.95em; }
            .preview-banner { background: #FFF3CD; color: #6b5300; text-align: center; padding: 10px; border-radius: 6px; font-weight: bold; margin-bottom: 25px; }
            h2 { color: #2E4057; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-top: 35px; break-after: avoid; }
            .category-head { break-inside: avoid; }
            .article { margin-bottom: 18px; padding-left: 10px; break-inside: avoid; }
            .article.status-pending { opacity: 0.75; border-left: 3px solid #0d3d5c; padding-left: 10px; }
            .article.status-rejected { opacity: 0.5; border-left: 3px solid #a12727; padding-left: 10px; text-decoration: line-through; }
            .article-title { font-weight: bold; }
            .article-body { white-space: pre-line; margin-top: 4px; }
            .status-badge { display: inline-block; font-size: 0.75em; font-weight: normal; text-decoration: none; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
            .status-badge.pending { background: #E8F4FD; color: #0d3d5c; }
            .status-badge.rejected { background: #FFE8E8; color: #a12727; }
            .signatures { margin-top: 60px; display: flex; justify-content: space-between; break-inside: avoid; }
            .sign-block { width: 45%; border-top: 1px solid #333; padding-top: 8px; text-align: center; }
            .sign-block .sign-label { font-weight: bold; color: #2E4057; }
            .sign-block .sign-approve { font-style: italic; color: #555; margin: 4px 0 30px; }
            .sign-note { margin-top: 20px; font-size: 0.85em; color: #666; text-align: center; break-inside: avoid; }
            .info-box { background: #E8F4FD; border-radius: 8px; padding: 14px 18px; margin-bottom: 25px; break-inside: avoid; }
            .info-box strong { color: #2E4057; }
            .info-box p { margin: 6px 0 0; }
            .preambule { break-inside: avoid; }
            .preambule ul { padding-left: 20px; }
            .preambule li { margin-bottom: 8px; }
            .safeword-block { break-inside: avoid; }
            #chastity-print-bar { position: sticky; top: 0; background: #2E4057; color: #fff; padding: 10px 18px; text-align: center; z-index: 999; font-family: Georgia, serif; }
            #chastity-print-bar button { background: #BC2A4D; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 1em; cursor: pointer; }
            #chastity-print-bar button:disabled { background: #999; cursor: wait; }
            @media print {
                #chastity-print-bar { display: none !important; }
                .preview-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style></head><body>';

        if (!$is_preview) {
            // Barre non imprimée : le bouton ne devient actif qu'une fois
            // Paged.js ayant terminé la pagination réelle du document
            // (évite d'imprimer un rendu pas encore découpé en pages).
            $html .= '<div id="chastity-print-bar"><button id="chastity-print-btn" disabled>⏳ Préparation de la pagination…</button></div>';
        }

        $html .= '<div class="contract-wrap">';

        if ($is_preview) {
            $html .= '<div class="preview-banner">📝 APERÇU DE TRAVAIL — contient aussi les articles en attente ou refusés. Seuls les articles validés figureront dans l\'export officiel du contrat.</div>';
        }

        $html .= '<h1>Contrat de chasteté</h1>';
        $html .= '<p class="meta">Entre <strong>' . htmlspecialchars($data['encage_name']) . '</strong> et <strong>' . htmlspecialchars($data['kh_name']) . '</strong>';
        if ($created !== '') { $html .= '<br>Créé le ' . $created; }
        if ($validated !== '') { $html .= ' — Validé le ' . $validated; }
        $html .= '</p>';

        // Termes de civilité (genre) — réutilisés ici pour le préambule et
        // plus bas pour la page de signature.
        $kh_terms = $this->civility_terms($data['kh_gender'] ?? 'male', 'kh');
        $encage_terms = $this->civility_terms($data['encage_gender'] ?? 'male', 'encage');

        $html .= '<div class="info-box"><strong>ℹ️ Nature du document</strong>
            <p>Ce document est un contrat symbolique et moral, fondé sur le consentement libre, éclairé et permanent des deux parties. Il n\'a aucune portée juridique contraignante. Il peut être suspendu, révisé ou résilié à tout moment par l\'une ou l\'autre partie'
            . (!empty($data['safeword']) ? ', notamment par l\'usage du mot de sécurité défini ci-dessous' : '') . '.</p></div>';

        $html .= '<div class="preambule">';
        $html .= '<h2>Préambule</h2>';
        $html .= '<p>Le présent contrat est conclu librement entre :</p>';
        $html .= '<ul>';
        $html .= '<li>' . $kh_terms['civility'] . ', <strong>' . htmlspecialchars($data['kh_name']) . '</strong>, ' . $kh_terms['dit'] . ' « ' . $kh_terms['label'] . ' » (ou « ' . $kh_terms['alt_label'] . ' »), ' . $kh_terms['possessive'] . ' de la clé et de l\'autorité définie ci-après ;</li>';
        $html .= '<li>' . $encage_terms['civility'] . ', <strong>' . htmlspecialchars($data['encage_name']) . '</strong>, ' . $encage_terms['dit'] . ' « ' . $encage_terms['label'] . ' », qui accepte de céder volontairement le contrôle de sa sexualité à ' . $kh_terms['label'] . '.</li>';
        $html .= '</ul>';
        $html .= '<p>Les deux parties déclarent être majeures, consentantes, agir librement et en dehors de toute contrainte.</p>';
        $html .= '<p>Ce contrat est conclu pour une durée indéterminée et peut être suspendu ou révoqué à tout moment d\'un commun accord, par la fin de la relation Keyholder, ou par l\'usage du mot de sécurité.</p>';
        $html .= '</div>';

        if (empty($data['categories'])) {
            $html .= '<p><em>Aucun article' . ($is_preview ? '' : ' validé') . ' dans ce contrat pour le moment.</em></p>';
        }

        foreach ($data['categories'] as $cat) {
            $arts = $cat['articles'];
            $first_art = array_shift($arts);

            // Le titre de section ET son premier article sont regroupés
            // dans un même bloc "break-inside: avoid" : impossible qu'un
            // titre se retrouve seul en bas de page, séparé de tout article.
            $html .= '<div class="category-head">';
            $html .= '<h2>' . $cat['number'] . '. ' . htmlspecialchars($cat['label']) . '</h2>';
            if ($first_art !== null) {
                $html .= $this->render_article_html($first_art);
            }
            $html .= '</div>';

            foreach ($arts as $art) {
                $html .= $this->render_article_html($art);
            }
        }

        if (!empty($data['safeword'])) {
            $html .= '<div class="safeword-block">';
            $html .= '<h2>🔑 Mot de sécurité</h2>';
            $html .= '<p>Le mot de sécurité convenu entre les deux parties est : <strong>' . htmlspecialchars($data['safeword']) . '</strong>. Son usage entraîne la suspension immédiate de ce contrat.</p>';
            $html .= '</div>';
        }

        $html .= '<div class="signatures">';
        $html .= '<div class="sign-block">
            <div class="sign-label">' . $kh_terms['label'] . '</div>
            <div class="sign-approve">« Lu et ' . $kh_terms['approved'] . ', bon pour accord »</div>
            Signature : ______________________<br>
            <span style="font-size:0.85em;color:#888;">' . htmlspecialchars($data['kh_name']) . '</span>
        </div>';
        $html .= '<div class="sign-block">
            <div class="sign-label">' . $encage_terms['label'] . '</div>
            <div class="sign-approve">« Lu et ' . $encage_terms['approved'] . ', bon pour engagement »</div>
            Signature : ______________________<br>
            <span style="font-size:0.85em;color:#888;">' . htmlspecialchars($data['encage_name']) . '</span>
        </div>';
        $html .= '</div>';
        $html .= '<p class="sign-note">Le « bon pour accord » de chaque partie est matérialisé par <strong>signature électronique</strong> : la validation du contrat au moyen du code unique transmis par email (et, pour une Keyholder inscrite sur le forum, via son compte personnel) vaut signature électronique et engagement des deux parties, au même titre qu\'une signature manuscrite.</p>';
        $html .= '</div>'; // .contract-wrap

        if (!$is_preview) {
            // Paged.js déclenche afterRendered() une fois la pagination du
            // document terminée : c'est à ce moment précis qu'on active le
            // bouton d'impression, jamais avant (sinon l'impression
            // capturerait un rendu non paginé). Un filet de sécurité active
            // quand même le bouton après 8s au cas où : le document reste de
            // toute façon correctement paginé à l'écran par Paged.js, seul
            // le déclenchement du bouton dépendrait alors du minuteur.
            $html .= '<script>
                function chastityEnablePrintBtn() {
                    var btn = document.getElementById("chastity-print-btn");
                    if (btn && btn.disabled) {
                        btn.disabled = false;
                        btn.textContent = "🖨️ Imprimer / Enregistrer en PDF";
                        btn.onclick = function(){ window.print(); };
                    }
                }
                try {
                    class ChastityPrintReady extends Paged.Handler {
                        afterRendered() { chastityEnablePrintBtn(); }
                    }
                    Paged.registerHandlers(ChastityPrintReady);
                } catch (e) {}
                setTimeout(chastityEnablePrintBtn, 8000);
            </script>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Rendu HTML d'un seul article (titre + corps + badge de statut),
     * factorisé car utilisé à la fois pour le premier article de chaque
     * catégorie (regroupé avec son titre) et les suivants.
     */
    private function render_article_html($art)
    {
        $status = $art['status'] ?? 'approved';
        $status_class = ($status === 'pending') ? ' status-pending' : (($status === 'rejected') ? ' status-rejected' : '');
        $html = '<div class="article' . $status_class . '">';
        $html .= '<div class="article-title">' . $art['number'] . ' — ' . htmlspecialchars($art['title']);
        if ($status === 'pending') { $html .= '<span class="status-badge pending">En attente de validation</span>'; }
        elseif ($status === 'rejected') { $html .= '<span class="status-badge rejected">Refusé</span>'; }
        $html .= '</div>';
        $html .= '<div class="article-body">' . htmlspecialchars($art['body']) . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Génère un vrai fichier PDF via TCPDF, si la bibliothèque est installée
     * (composer require tecnickcom/tcpdf). Renvoie null si TCPDF est absent,
     * pour que l'appelant puisse basculer sur generate_html() en secours.
     */
    public function generate_pdf($data)
    {
        if (!class_exists('\TCPDF')) {
            return null;
        }

        $created = $data['created_time'] > 0 ? date('d/m/Y', $data['created_time']) : '';
        $validated = $data['validated_time'] > 0 ? date('d/m/Y', $data['validated_time']) : '';

        $pdf = new chastity_contract_tcpdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Chastity Tracker');
        $pdf->SetTitle('Contrat de chasteté');
        $pdf->SetAuthor($data['encage_name'] . ' / ' . $data['kh_name']);
        $pdf->chastity_footer_encage = $data['encage_name'];
        $pdf->chastity_footer_kh     = $data['kh_name'];
        $pdf->chastity_footer_date   = ($validated !== '') ? $validated : ($created !== '' ? $created : date('d/m/Y'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 12, 'Contrat de chasteté', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $meta = 'Entre ' . $data['encage_name'] . ' et ' . $data['kh_name'];
        if ($created !== '') { $meta .= ' — Créé le ' . $created; }
        if ($validated !== '') { $meta .= ' — Validé le ' . $validated; }
        $pdf->Cell(0, 8, $meta, 0, 1, 'C');
        $pdf->Ln(6);

        $kh_terms = $this->civility_terms($data['kh_gender'] ?? 'male', 'kh');
        $encage_terms = $this->civility_terms($data['encage_gender'] ?? 'male', 'encage');

        $pdf->SetFillColor(232, 244, 253);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell(0, 7, 'Nature du document', 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9.5);
        $nature_txt = "Ce document est un contrat symbolique et moral, fondé sur le consentement libre, éclairé et permanent des deux parties. Il n'a aucune portée juridique contraignante. Il peut être suspendu, révisé ou résilié à tout moment par l'une ou l'autre partie" . (!empty($data['safeword']) ? ", notamment par l'usage du mot de sécurité défini ci-dessous" : '') . '.';
        $pdf->MultiCell(0, 6, $nature_txt, 0, 'L', true);
        $pdf->Ln(6);

        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'Préambule', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 6, 'Le présent contrat est conclu librement entre :', 0, 'L');
        $pdf->MultiCell(0, 6, '- ' . $kh_terms['civility'] . ', ' . $data['kh_name'] . ', ' . $kh_terms['dit'] . ' « ' . $kh_terms['label'] . ' » (ou « ' . $kh_terms['alt_label'] . ' »), ' . $kh_terms['possessive'] . ' de la clé et de l\'autorité définie ci-après ;', 0, 'L');
        $pdf->MultiCell(0, 6, '- ' . $encage_terms['civility'] . ', ' . $data['encage_name'] . ', ' . $encage_terms['dit'] . ' « ' . $encage_terms['label'] . ' », qui accepte de céder volontairement le contrôle de sa sexualité à ' . $kh_terms['label'] . '.', 0, 'L');
        $pdf->MultiCell(0, 6, 'Les deux parties déclarent être majeures, consentantes, agir librement et en dehors de toute contrainte.', 0, 'L');
        $pdf->MultiCell(0, 6, "Ce contrat est conclu pour une durée indéterminée et peut être suspendu ou révoqué à tout moment d'un commun accord, par la fin de la relation Keyholder, ou par l'usage du mot de sécurité.", 0, 'L');
        $pdf->Ln(4);

        if (empty($data['categories'])) {
            $pdf->SetFont('helvetica', 'I', 11);
            $pdf->Cell(0, 8, 'Aucun article validé dans ce contrat pour le moment.', 0, 1, 'L');
        }

        foreach ($data['categories'] as $cat) {
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Ln(4);
            $pdf->Cell(0, 8, $cat['number'] . '. ' . $cat['label'], 0, 1, 'L');
            foreach ($cat['articles'] as $art) {
                // Empêche un article de se couper entre 2 pages : on estime
                // sa hauteur totale (titre + corps) et on force un saut de
                // page AVANT de le commencer si la place restante ne suffit
                // pas, plutôt que de laisser TCPDF le couper au milieu.
                $pdf->SetFont('helvetica', 'B', 11);
                $title_h = $pdf->getStringHeight(0, $art['number'] . ' — ' . $art['title']);
                $pdf->SetFont('helvetica', '', 10);
                $body_h = $pdf->getStringHeight(0, $art['body']);
                $pdf->checkPageBreak($title_h + $body_h + 4);

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->MultiCell(0, 6, $art['number'] . ' — ' . $art['title'], 0, 'L');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->MultiCell(0, 6, $art['body'], 0, 'L');
                $pdf->Ln(2);
            }
        }

        if (!empty($data['safeword'])) {
            // Même logique que pour les articles : estime la hauteur totale
            // du bloc (titre + texte) et force un saut de page AVANT s'il ne
            // tient pas dans la place restante, plutôt que de le laisser se
            // couper au milieu.
            $sw_title_h = 8;
            $pdf->SetFont('helvetica', '', 10);
            $sw_text = 'Le mot de sécurité convenu entre les deux parties est : ' . $data['safeword'] . '. Son usage entraîne la suspension immédiate de ce contrat.';
            $sw_body_h = $pdf->getStringHeight(0, $sw_text);
            $pdf->checkPageBreak(6 + $sw_title_h + $sw_body_h);

            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Ln(6);
            $pdf->Cell(0, 8, 'Mot de sécurité', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 6, $sw_text, 0, 'L');
        }

        $pdf->Ln(15);
        $pdf->SetFont('helvetica', 'B', 10);
        $y = $pdf->GetY();
        $pdf->SetXY(20, $y);
        $pdf->Cell(80, 6, $kh_terms['label'], 0, 0, 'C');
        $pdf->SetXY(110, $y);
        $pdf->Cell(80, 6, $encage_terms['label'], 0, 1, 'C');
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetXY(20, $pdf->GetY());
        $pdf->Cell(80, 6, '« Lu et ' . $kh_terms['approved'] . ', bon pour accord »', 0, 0, 'C');
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->Cell(80, 6, '« Lu et ' . $encage_terms['approved'] . ', bon pour engagement »', 0, 1, 'C');
        $pdf->Ln(10);
        $y = $pdf->GetY();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(20, $y);
        $pdf->Cell(80, 6, '________________________', 0, 0, 'C');
        $pdf->SetXY(110, $y);
        $pdf->Cell(80, 6, '________________________', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(140, 140, 140);
        $pdf->SetXY(20, $pdf->GetY());
        $pdf->Cell(80, 6, $data['kh_name'], 0, 0, 'C');
        $pdf->SetXY(110, $pdf->GetY());
        $pdf->Cell(80, 6, $data['encage_name'], 0, 1, 'C');

        $pdf->Ln(8);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 5, 'Le « bon pour accord » de chaque partie est matérialisé par signature électronique : la validation du contrat au moyen du code unique transmis par email (et, pour une Keyholder inscrite sur le forum, via son compte personnel) vaut signature électronique et engagement des deux parties, au même titre qu\'une signature manuscrite.', 0, 'C');

        return $pdf->Output('contrat-chastete.pdf', 'S');
    }
}
