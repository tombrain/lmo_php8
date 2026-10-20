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

namespace Lmo\Web\Seiten;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Vorlagen;

/**
 * Die Auswahl der Spieltage (im Pokal der Runden) ueber Ergebnissen, Tabelle, Tippspiel und
 * Adminbereich, mit der Vorlage spieltagsmenu.html.twig.
 */
final class SpieltagsMenue
{
    /** @var array<int, string> */
    private array $text;
    private string $vorlagen;

    /**
     * @param array<int, string> $text Sprachtexte
     * @param string $vorlagen Ordner der Twig-Vorlagen
     */
    public function __construct(array $text, string $vorlagen)
    {
        $this->text = $text;
        $this->vorlagen = $vorlagen;
    }

    /**
     * Das Menue als HTML; ohne Liga eine leere Zeile.
     *
     * @param int $aktuell der gewaehlte Spieltag
     * @param string $link Link auf einen Spieltag, es fehlt nur dessen Nummer (fertiges HTML)
     * @param bool $aktuellenVerlinken auch den gewaehlten Spieltag verlinken (Adminbereich, Tippspiel)
     */
    public function zeige(?Liga $liga, int $aktuell, string $link, bool $aktuellenVerlinken = false): string
    {
        return Vorlagen::zeige($this->vorlagen, 'spieltagsmenu.html.twig', [
            'eintraege' => $liga !== null ? $this->eintraege($liga, $aktuell, $link, $aktuellenVerlinken) : [],
        ]);
    }

    /**
     * @return list<array{text: mixed, titel: mixed, link: string|null, umbruch: bool}>
     */
    private function eintraege(Liga $liga, int $aktuell, string $link, bool $aktuellenVerlinken): array
    {
        $anzahl = $liga->einstellungen()->spieltage();
        $pokal = $liga->einstellungen()->istPokal();
        $eintraege = [];
        for ($i = 1; $i <= $anzahl; $i++) {
            [$text, $titel] = $pokal ? $this->runde($i, $anzahl) : [sprintf('%02d', $i), $this->text[9]];
            $eintraege[] = [
                'text' => $text,
                'titel' => $titel,
                'link' => $i == $aktuell && !$aktuellenVerlinken ? null : $link . $i,
                'umbruch' => self::umbruchNach($i, $anzahl),
            ];
        }

        return $eintraege;
    }

    /**
     * Name und Titel einer Pokalrunde: die letzten vier heissen Finale, Halbfinale usw.
     *
     * @return array{mixed, mixed}
     */
    private function runde(int $i, int $anzahl): array
    {
        $vomEnde = $anzahl - $i;
        if ($vomEnde <= 3) {
            return [$this->text[364 - 2 * $vomEnde], $this->text[365 - 2 * $vomEnde]];
        }

        return [$i, $this->text[366]];
    }

    /** Lange Ligen bekommen zwei bis vier Zeilen: Umbruch nach diesem Spieltag? */
    private static function umbruchNach(int $i, int $anzahl): bool
    {
        if ($anzahl > 47 && $anzahl % 4 == 0) {
            return $i == $anzahl / 4 || $i == $anzahl / 2 || $i == $anzahl / 4 * 3;
        }
        if ($anzahl > 35) {
            return $i == ceil($anzahl / 3) || $i == ceil($anzahl / 3 * 2);
        }
        if ($anzahl > 23) {
            return $i == ceil($anzahl / 2);
        }

        return false;
    }
}
