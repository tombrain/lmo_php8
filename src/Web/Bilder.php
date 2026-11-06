<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Web;

/**
 * Bilder aus dem Bilderordner (img/<art>/<groesse>/) zu einem Namen: Wappen der Mannschaften
 * (teams), Symbole der Ligen (liga) und Bilder der Spieler (spieler). Bis 4.5 HTML_icon() und
 * findImage() der Classlib; gesucht wird wie dort.
 */
final class Bilder
{
    /** Endungen in der Reihenfolge der Suche (bis 4.5 img_types der Classlib, mit diesem Wert vorgegeben) */
    public const ENDUNGEN = ['.gif', '.jpg', '.png', '.jpeg'];

    /**
     * @param string $ordner Pfad zum Bilderordner (PATH_TO_IMGDIR)
     * @param string $adresse Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function __construct(private string $ordner, private string $adresse)
    {
    }

    /**
     * Das Bild als <img> mit Breite und Hoehe; ohne Bild der Ersatztext, ohne den der Text aus
     * $html ab dem siebten Zeichen bis zum letzten Hochkomma (so wie bisher).
     *
     * @param string $art teams, liga oder spieler
     * @param string $groesse small, middle oder big
     * @param string $html weitere Attribute des <img>
     */
    public function bild(string $name, string $art, string $groesse = 'small', string $html = '', string $ersatz = ''): string
    {
        $ordner = '/' . $art . '/' . $groesse . '/';
        // der Ersatztext erst nach allen Endungen, sonst endet die Suche schon bei der ersten
        foreach (self::ENDUNGEN as $endung) {
            $bild = $this->suche($name, $ordner, $endung, $html);
            if ($bild != '') {
                return $bild;
            }
        }
        if ($ersatz != '') {
            return $ersatz;
        }
        $hochkomma = strrpos($html, "'");

        return substr($html, 6, ($hochkomma === false ? 0 : $hochkomma) - 6);
    }

    /** Das kleine Symbol einer Liga zum Namen ihrer Datei (ohne Endung). */
    public function ligaSymbol(string $datei, string $html = '', string $ersatz = ''): string
    {
        $name = basename($datei);
        $punkt = strrpos($name, '.');

        return $this->bild(substr($name, 0, $punkt === false ? 0 : $punkt), 'liga', 'small', $html, $ersatz);
    }

    /**
     * Sucht das Bild unter dem Namen ohne "/", dann nur mit Buchstaben und Ziffern, dann ohne die
     * Ziffern und I, A, ( und ) am Ende.
     */
    private function suche(string $name, string $ordner, string $endung, string $html): string
    {
        $name = str_replace('/', '', $name);
        if (!file_exists($this->ordner . $ordner . $name . $endung)) {
            $name = (string) preg_replace('/[^a-zA-Z0-9]/', '', $name);
        } else {
            return $this->img($name, $ordner, $endung, $html);
        }
        if (!file_exists($this->ordner . $ordner . $name . $endung)) {
            $name = (string) preg_replace('/[I(A)0-9]+$/', '', $name);
        } else {
            return $this->img($name, $ordner, $endung, $html);
        }

        return file_exists($this->ordner . $ordner . $name . $endung) ? $this->img($name, $ordner, $endung, $html) : '';
    }

    private function img(string $name, string $ordner, string $endung, string $html): string
    {
        $groesse = getimagesize($this->ordner . $ordner . $name . $endung);

        return "<img src='" . $this->adresse . $ordner . rawurlencode($name) . $endung . "' " . ($groesse !== false ? $groesse[3] : '') . ' ' . $html . '> ';
    }
}
