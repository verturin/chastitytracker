<?php
/**
 * Chastity Tracker — Service period_calculator
 *
 * Centralise les calculs de dates / durées de l'extension afin d'éviter
 * la duplication des formules inline (secondes → jours, format sub-24h, etc.).
 *
 * Étape 1 : création du service + méthodes utilitaires.
 * Étape 2 : remplacement progressif des calculs inline dans les fichiers.
 *
 * @copyright (c) 2024 verturin
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace verturin\chastitytracker\service;

if (!defined('IN_PHPBB'))
{
    exit;
}

class period_calculator
{
    /** @var \phpbb\language\language|null */
    protected $language;

    /**
     * @param \phpbb\language\language|null $language  Optionnel : pour traduire jour/jours.
     */
    public function __construct($language = null)
    {
        $this->language = $language;
    }

    /**
     * Nombre de jours pleins écoulés entre deux timestamps.
     */
    public function days_between($start_ts, $end_ts)
    {
        $secs = (int) $end_ts - (int) $start_ts;
        if ($secs <= 0) { return 0; }
        return (int) floor($secs / 86400);
    }

    /**
     * Nombre de jours pleins écoulés depuis un timestamp jusqu'à maintenant.
     */
    public function days_since($start_ts)
    {
        return $this->days_between($start_ts, time());
    }

    /**
     * Secondes écoulées depuis un timestamp jusqu'à maintenant (jamais négatif).
     */
    public function seconds_since($start_ts)
    {
        return max(0, time() - (int) $start_ts);
    }

    /**
     * Récupère un libellé de langue avec repli si la clé/le service est absent.
     */
    protected function lang($key, $fallback)
    {
        if ($this->language !== null && method_exists($this->language, 'lang')) {
            $val = $this->language->lang($key);
            // lang() renvoie la clé elle-même si la traduction est absente
            if ($val !== $key && $val !== '') {
                return $val;
            }
        }
        return $fallback;
    }

    /**
     * Formate une durée écoulée pour l'affichage du statut d'une période active.
     *  - < 24h  → "1h05", "5h30"
     *  - >= 24h → "3 jours" / "1 jour"
     *
     * @param int  $secs   secondes écoulées
     * @param bool $with_word  true = ajoute " jour(s)" ; false = juste le nombre (pour calcul manuel)
     * @return string
     */
    public function format_duration($secs, $with_word = true)
    {
        $secs = (int) $secs;

        if ($secs > 0 && $secs < 86400) {
            $h = (int) floor($secs / 3600);
            $m = (int) floor(($secs % 3600) / 60);
            return $h . 'h' . sprintf('%02d', $m);
        }

        $days = (int) floor($secs / 86400);
        if (!$with_word) {
            return (string) $days;
        }
        $word = ($days === 1)
            ? $this->lang('CHASTITY_DAY', 'jour')
            : $this->lang('CHASTITY_DAYS', 'jours');
        return $days . ' ' . $word;
    }

    /**
     * Variante "sans accent" pour les contextes GD imagestring (mini badge).
     * Identique à format_duration mais sans le mot accentué.
     */
    public function format_duration_ascii($secs)
    {
        $secs = (int) $secs;
        if ($secs > 0 && $secs < 86400) {
            $h = (int) floor($secs / 3600);
            $m = (int) floor(($secs % 3600) / 60);
            return $h . 'h' . sprintf('%02d', $m);
        }
        $days = (int) floor($secs / 86400);
        return $days . ' ' . ($days === 1 ? 'jour' : 'jours');
    }
}
