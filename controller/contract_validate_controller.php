<?php
/**
 * Chastity Tracker — Contrôleur de validation externe du contrat (CTR)
 * Page publique (sans connexion requise) permettant à une Keyholder externe
 * (non-inscrite sur le forum) de consulter un CODE de validation en cliquant
 * le lien unique reçu par email, OU de REFUSER le contrat directement depuis
 * cette même page. Le clic n'active JAMAIS le contrat directement (double
 * vérification voulue) : la Keyholder communique le code à l'encagé, qui
 * doit le saisir dans son UCP pour activer le contrat. Le refus, en
 * revanche, est appliqué immédiatement depuis cette page (pas de double
 * vérification nécessaire pour un refus).
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\controller;

use Symfony\Component\HttpFoundation\Response;

class contract_validate_controller
{
    protected $db;
    protected $request;
    protected $contracts_table;
    protected $links_table;

    public function __construct($db, $request, $contracts_table, $links_table)
    {
        $this->db = $db;
        $this->request = $request;
        $this->contracts_table = $contracts_table;
        $this->links_table = $links_table;
    }

    public function handle()
    {
        $token = $this->request->variable('token', '');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Validation du contrat</title>';
        $html .= '<style>body{font-family:Georgia,serif;max-width:600px;margin:60px auto;text-align:center;color:#2a2a2a;}
            .box{padding:30px;border-radius:10px;}
            .ok{background:#D4EDDA;color:#155724;}
            .err{background:#FFE8E8;color:#a12727;}
            .warn{background:#FFF3CD;color:#6b5300;}
            .code{font-size:2em;letter-spacing:0.15em;font-weight:bold;background:#fff;padding:15px 25px;border-radius:8px;display:inline-block;margin:15px 0;border:2px solid #155724;}
            h1{margin-top:0;}
            textarea{width:100%;box-sizing:border-box;padding:8px;font-family:Georgia,serif;border-radius:6px;border:1px solid #ccc;margin:10px 0;}
            .btn{display:inline-block;padding:10px 22px;border-radius:6px;border:none;font-size:1em;cursor:pointer;margin-top:8px;}
            .btn-reject{background:#a12727;color:#fff;}
            .btn-cancel{background:#eee;color:#333;text-decoration:none;display:inline-block;padding:10px 22px;border-radius:6px;margin-left:8px;}
            details{text-align:left;margin-top:25px;}
            summary{cursor:pointer;color:#a12727;text-align:center;}</style></head><body>';

        if ($token === '')
        {
            $html .= '<div class="box err"><h1>Lien invalide</h1><p>Ce lien de validation est invalide ou incomplet.</p></div>';
        }
        else
        {
            $res = $this->db->sql_query('SELECT contract_id, status, kh_external_name, validation_code FROM ' . $this->contracts_table . "
                WHERE validation_token = '" . $this->db->sql_escape($token) . "'");
            $row = $this->db->sql_fetchrow($res);
            $this->db->sql_freeresult($res);

            if (!$row)
            {
                $html .= '<div class="box err"><h1>Lien invalide</h1><p>Ce lien de validation ne correspond à aucun contrat, ou n\'est plus valable.</p></div>';
            }
            elseif ($row['status'] !== 'pending_validation')
            {
                $html .= '<div class="box err"><h1>Ce contrat n\'est plus en attente</h1><p>Ce contrat a déjà été traité (validé, refusé ou modifié depuis l\'envoi de ce lien).</p></div>';
            }
            // ── Traitement du refus (soumis depuis le formulaire ci-dessous) ──
            elseif ($this->request->is_set_post('reject_contract'))
            {
                $reason = $this->request->variable('reason', '', true);

                $this->reject_contract((int) $row['contract_id'], $row['kh_external_name'], $reason);

                $html .= '<div class="box ok"><h1>❌ Contrat refusé</h1>
                    <p>Vous avez refusé ce contrat. L\'encagé(e) a été notifié(e)' . ($reason !== '' ? ' avec le motif que vous avez indiqué' : '') . '. Le contrat repasse en brouillon : il/elle peut le modifier puis vous le soumettre à nouveau.</p></div>';
            }
            else
            {
                // Le code a déjà été généré à la soumission/au renvoi (même
                // colonne validation_code que pour une Keyholder inscrite) :
                // on l'affiche simplement ici, sans jamais activer le contrat
                // depuis cette page — l'activation se fait uniquement quand
                // l'encagé saisit ce code dans son UCP (double vérification).
                $html .= '<div class="box ok"><h1>🔑 Code de validation</h1>
                    <p>Merci ' . htmlspecialchars($row['kh_external_name']) . '. Voici votre code de validation :</p>
                    <div class="code">' . htmlspecialchars($row['validation_code']) . '</div>
                    <p>Communiquez ce code à votre encagé(e) : il/elle devra le saisir dans son espace personnel pour activer le contrat.</p></div>';

                // ── Option de refus, repliée par défaut pour ne pas la
                // rendre trop visible/accidentelle à côté du code d'accord.
                $html .= '<details><summary>Je ne suis pas d\'accord avec ce contrat</summary>
                    <form method="post" action="">
                        <input type="hidden" name="token" value="' . htmlspecialchars($token) . '" />
                        <label>Motif du refus (optionnel, transmis à l\'encagé(e))&nbsp;:</label>
                        <textarea name="reason" rows="4" maxlength="1000"></textarea>
                        <button type="submit" name="reject_contract" value="1" class="btn btn-reject" onclick="return confirm(\'Confirmer le refus de ce contrat ? Il repassera en brouillon.\');">Refuser ce contrat</button>
                    </form>
                    </details>';
            }
        }

        $html .= '</body></html>';

        $response = new Response($html, 200);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        return $response;
    }

    /**
     * Repasse le contrat en brouillon (comme un refus depuis l'UCP par une
     * Keyholder inscrite), efface le code/token de validation désormais
     * invalides, enregistre le motif, et notifie l'encagé par MP. Le membre
     * "from" du MP est ANONYMOUS (aucun compte pour une KH externe), affiché
     * sous le nom qu'elle a fourni à l'origine.
     */
    private function reject_contract($contract_id, $kh_external_name, $reason)
    {
        global $phpbb_root_path, $phpEx;

        $encage_res = $this->db->sql_query('SELECT encage_user_id FROM ' . $this->contracts_table . '
            WHERE contract_id = ' . $contract_id);
        $encage_row = $this->db->sql_fetchrow($encage_res);
        $this->db->sql_freeresult($encage_res);

        $this->db->sql_query('UPDATE ' . $this->contracts_table . " SET
            status = 'draft',
            validation_code = '',
            validation_token = '',
            last_rejection_reason = '" . $this->db->sql_escape($reason) . "',
            updated_time = " . time() . '
            WHERE contract_id = ' . $contract_id);

        // Les articles "approved" de ce contrat ont forcément été
        // auto-résolus à la soumission (une KH externe n'a jamais pu les
        // valider individuellement, cf. v3.14.11) : ce n'était donc pas une
        // véritable approbation, seulement un déblocage provisoire lié à la
        // soumission. Puisqu'elle refuse, ils doivent repasser "en attente"
        // de validation, comme n'importe quel autre article non traité.
        $this->db->sql_query('UPDATE ' . $this->links_table . "
            SET proposal_status = 'pending', updated_time = " . time() . '
            WHERE contract_id = ' . $contract_id . "
              AND proposal_status = 'approved'");

        if (!$encage_row)
        {
            return;
        }

        if (!function_exists('submit_pm')) {
            include_once($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
        }

        $subject = 'Votre contrat de chasteté a été refusé';
        $message = 'Votre Keyholder (' . $kh_external_name . ") a refusé le contrat que vous lui avez soumis.\n\n";
        if ($reason !== '')
        {
            $message .= "Motif indiqué : " . $reason . "\n\n";
        }
        $message .= "Le contrat est repassé en brouillon : vous pouvez le modifier puis le lui soumettre à nouveau.";

        $bbcode_uid = substr(md5(uniqid()), 0, 8);
        $pm_data = [
            'from_user_id'       => ANONYMOUS,
            'from_user_ip'       => '127.0.0.1',
            'from_username'      => $kh_external_name,
            'enable_sig'         => false,
            'enable_bbcode'      => true,
            'enable_smilies'     => true,
            'enable_urls'        => true,
            'icon_id'            => 0,
            'bbcode_bitfield'    => 'AQ==',
            'bbcode_uid'         => $bbcode_uid,
            'message'            => $message,
            'address_list'       => ['u' => [(int) $encage_row['encage_user_id'] => 'to']],
        ];

        try {
            submit_pm('post', $subject, $pm_data, false);
        } catch (\Throwable $e) {
            if (function_exists('add_log')) {
                add_log('admin', 'LOG_CHASTITY_CONTRACT_EMAIL_FAILED', $e->getMessage());
            }
        }
    }
}
