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

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Web\Darstellung;
use Lmo\Web\Vorlagen;

/**
 * Die Liste der Ligen eines Ordners (Ligenuebersicht und "Liga oeffnen" im Adminbereich): je Liga
 * Symbol, Name als Link, im Adminbereich die Datei, der aktuelle Spieltag bzw. die aktuelle
 * Pokalrunde und das Datum der letzten Aenderung. Sortiert wird nach dem gewaehlten Feld, im
 * Browser laesst sich die Tabelle umsortieren (js/sortable). Vorlage ligenliste.html.twig.
 */
final class Ligenliste
{
    /** Sortierfelder => Spalte im Browser, ohne Spalte "Datei" (oeffentlich) und mit (Adminbereich) */
    private const SPALTEN = [
        false => ['liga_name' => 1, 'file_name' => -1, 'file_date' => 3],
        true => ['liga_name' => 1, 'file_name' => 2, 'file_date' => 4],
    ];

    /**
     * @param array<int, string> $text Sprachtexte
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     * @param string $lmo Adresse des LMO (URL_TO_LMO), fuer js/sortable
     * @param Closure(string): bool $darfLesen Ligadatei => darf der Besucher sie sehen? (check_hilfsadmin)
     */
    public function __construct(
        private array $text,
        private Darstellung $darstellung,
        private string $vorlagen,
        private string $bilder,
        private string $lmo,
        private Closure $darfLesen,
    ) {
    }

    /**
     * Die Liste als HTML; ist der Ordner nicht lesbar, steht eine Fehlermeldung ueber der leeren Liste.
     *
     * @param string $ordner Pfad des Ordners, mit "/" am Ende
     * @param string $unterordner Unterordner relativ zum Ligenordner, mit "/" am Ende ('' fuer den Ligenordner)
     * @param string $adresse Adresse der Seite ($_SERVER['PHP_SELF']), fuer die Sortier-Links
     * @param string $link Link auf eine Liga, es fehlen Unterordner und Datei (fertiges HTML)
     * @param bool $mitDatei Spalte mit dem Dateinamen (angemeldet)
     * @param string $sortierung Feld: liga_name, file_name, file_date, aktueller_spieltag
     * @param string $richtung asc oder desc
     * @param string $datumsformat Datumsformat fuer die letzte Aenderung
     */
    public function zeige(string $ordner, string $unterordner, string $adresse, string $link, bool $mitDatei, string $sortierung, string $richtung, string $datumsformat): string
    {
        $dateien = is_dir($ordner) ? scandir($ordner) : false;
        $ligen = $this->ligen($ordner, $dateien === false ? [] : $dateien);
        usort($ligen, static fn (array $a, array $b): int => self::vergleiche($a, $b, $sortierung));
        if ($richtung == 'desc') {
            $ligen = array_reverse($ligen);
        }
        $zeilen = [];
        foreach ($ligen as $liga) {
            $zeilen[] = $liga + [
                'symbol' => $this->darstellung->ligaSymbol($liga['file_name'], 'alt=""'),
                'link' => $link . $unterordner . $liga['file_name'],
                'datum' => date($datumsformat, $liga['file_date']),
            ];
        }
        $spalten = self::SPALTEN[$mitDatei];
        $sortieren = isset($spalten[$sortierung]) && $spalten[$sortierung] >= 0 && ($richtung == 'asc' || $richtung == 'desc')
            ? $spalten[$sortierung] . ',' . ($richtung == 'desc' ? 1 : 0)
            : null;

        return Vorlagen::zeige($this->vorlagen, 'ligenliste.html.twig', [
            'text' => $this->text,
            'bilder' => $this->bilder,
            'lmo' => $this->lmo,
            'adresse' => $adresse,
            'fehler' => $dateien === false,
            'mit_datei' => $mitDatei,
            'ligen' => $zeilen,
            'sortieren' => $sortieren,
        ]);
    }

    /**
     * Die Ligen im Ordner, die der Besucher sehen darf, in der Reihenfolge der Dateien.
     *
     * @param list<string> $dateien
     * @return list<array{file_date: int, file_name: string, liga_name: string, aktueller_spieltag: string, rundenbezeichnung: string}>
     */
    private function ligen(string $ordner, array $dateien): array
    {
        $text = $this->text;
        $ligen = [];
        $unbenannt = 0;
        foreach ($dateien as $datei) {
            $pfad = $ordner . $datei;
            if (strtolower(substr($datei, -4)) != '.l98' || !is_readable($pfad) || !($this->darfLesen)($datei)) {
                continue;
            }
            // Abschnitt [Options] der Ligadatei
            $optionen = LigaDatei::liesAbschnitt($pfad, 'Options');
            $name = $optionen['Name'] ?? '';
            if ($name == '') {
                $unbenannt++;
                $name = $text[507] . ' ' . $unbenannt;
            }
            $spieltag = $optionen['Actual'] ?? '';
            $pokal = ($optionen['Type'] ?? '') == '1';
            $runde = $pokal ? $text[370] : $text[2];
            if ($spieltag == '') {
                $runde = '';
            } elseif ($pokal) {
                [$runde, $spieltag] = $this->pokalrunde((int) ($optionen['Teams'] ?? 0), $spieltag, $runde);
            }
            $ligen[] = [
                'file_date' => (int) filemtime($pfad),
                'file_name' => $datei,
                'liga_name' => $name,
                'aktueller_spieltag' => $spieltag,
                'rundenbezeichnung' => $runde,
            ];
        }

        return $ligen;
    }

    /**
     * Name der aktuellen Pokalrunde, gerechnet wie bisher aus der Anzahl der Mannschaften: die
     * letzten Runden heissen Finale, Halbfinale usw. (bei 24 Mannschaften eine Runde mehr) und
     * stehen dann ohne Nummer.
     *
     * @return array{string, string} Bezeichnung, Nummer der Runde ('' ohne)
     */
    private function pokalrunde(int $mannschaften, string $spieltag, string $runde): array
    {
        $text = $this->text;
        $runden = strlen(decbin($mannschaften));
        $vierundzwanzig = $mannschaften == 24;
        // bei 24 Mannschaften gibt es eine Runde mehr: das Finale ist dort Runde $runden
        if ($vierundzwanzig) {
            return match (true) {
                $spieltag == $runden => [$text[374], ''],
                $spieltag == $runden - 1 => [$text[373], ''],
                $spieltag == $runden - 2 => [$text[372], ''],
                $spieltag == $runden - 3 => [$text[371], ''],
                $spieltag == $runden - 4 => [$text[370], '1'],
                default => [$runde, $spieltag],
            };
        }

        return match (true) {
            $spieltag == $runden - 1 => [$text[374], ''],
            $spieltag == $runden - 2 => [$text[373], ''],
            $spieltag == $runden - 3 => [$text[372], ''],
            $spieltag == $runden - 4 => [$text[371], ''],
            default => [$runde, $spieltag],
        };
    }

    /**
     * Vergleich zweier Ligen nach dem Sortierfeld (wie bisher cmp() in lmo-dirlist.php): Zahlen
     * numerisch, sonst natuerlich und ohne Gross-/Kleinschreibung, Akzente wie bisher ersetzt.
     *
     * @param array<string, int|string> $a
     * @param array<string, int|string> $b
     */
    private static function vergleiche(array $a, array $b, string $feld): int
    {
        if (!isset($a[$feld])) {
            $feld = 'liga_name';
        }
        $x = $a[$feld];
        $y = $b[$feld] ?? '';
        if (is_numeric($x) && is_numeric($y)) {
            return $x == $y ? 0 : ($x > $y ? 1 : -1);
        }

        return strnatcasecmp(strtr((string) $x, '¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ', 'YuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy'), strtr((string) $y, '¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ', 'YuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy'));
    }
}
