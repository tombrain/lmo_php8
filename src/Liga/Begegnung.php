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

namespace Lmo\Liga;

/**
 * Ein Spiel einer Liga, wie Mini-Spielplan und Viewer es zeigen, mit den Werten, wie sie in der
 * Ligadatei stehen (Tore und Anstoss als Text, '' wenn sie fehlen). Bis 4.5 die Klasse partie der
 * Classlib; die Ausgaben rechnen wie dort.
 */
final class Begegnung
{
    /**
     * @param int $spieltag Nummer des Spieltags
     * @param string $nummer Nummer des Spiels (Partie, im Pokal gefolgt vom Spiel)
     * @param string $zeit Anstoss als Zeitstempel (AT), '' ohne
     * @param int $heim Nummer der Heimmannschaft, 0 ohne
     * @param int $gast Nummer der Gastmannschaft, 0 ohne
     * @param string $toreHeim Tore wie in der Datei (GA): -1 ohne Ergebnis, -2 am gruenen Tisch gewonnen
     * @param string $toreGast Tore wie in der Datei (GB)
     * @param string $bericht Adresse des Spielberichts (BE)
     * @param int $spielende 0 regulaer, 1 im Elfmeterschiessen, 2 nach Verlaengerung (SP)
     */
    public function __construct(
        public int $spieltag,
        public string $nummer,
        public string $zeit,
        public string $notiz,
        public int $heim,
        public int $gast,
        public string $toreHeim,
        public string $toreGast,
        public string $bericht = '',
        public int $spielende = 0,
    ) {
    }

    /** Das Datum des Anstosses; ohne Anstoss $leer. */
    public function datum(string $leer = '', string $format = 'd.m.Y'): string
    {
        return is_numeric($this->zeit) && $this->zeit > 0 ? (new \DateTime())->setTimestamp((int) $this->zeit)->format($format) : $leer;
    }

    /** Die Uhrzeit des Anstosses; ohne Anstoss $leer. */
    public function uhrzeit(string $leer = '', string $format = 'H:i'): string
    {
        return $this->datum($leer, $format);
    }

    /**
     * Die Tore der Heimmannschaft zum Anzeigen: ohne Ergebnis $leer, am gruenen Tisch 0* bzw. 0,
     * sonst geteilt durch den Torfaktor.
     */
    public function toreHeimText(string $leer = '_', int|string $faktor = '1'): string
    {
        return self::tore($this->toreHeim, $this->toreGast, $leer, $faktor);
    }

    /** Die Tore der Gastmannschaft zum Anzeigen, wie toreHeimText(). */
    public function toreGastText(string $leer = '_', int|string $faktor = '1'): string
    {
        return self::tore($this->toreGast, $this->toreHeim, $leer, $faktor);
    }

    /**
     * Wie das Spiel endete: nach Verlaengerung Text 0, im Elfmeterschiessen Text 1, sonst $leer.
     *
     * @param array<int|string, mixed> $text Sprachtexte
     */
    public function spielendeText(array $text, string $leer = ''): string
    {
        return match ($this->spielende) {
            0 => $leer,
            1 => (string) ($text[1] ?? ''),
            2 => (string) ($text[0] ?? ''),
            default => (string) $this->spielende,
        };
    }

    /** Wer gewonnen hat: 1 Heim, 2 Gast, 0 unentschieden, -1 ohne Ergebnis. */
    public function wertung(): int
    {
        // lose verglichen wie bis 4.5: ein leerer Wert zaehlt als kein Ergebnis
        if ($this->toreHeim > -1 && $this->toreGast > -1) {
            return $this->toreHeim > $this->toreGast ? 1 : ($this->toreHeim < $this->toreGast ? 2 : 0);
        }
        if ($this->toreHeim == -2) {
            return 1;
        }

        return $this->toreGast == -2 ? 2 : -1;
    }

    private static function tore(string $eigene, string $andere, string $leer, int|string $faktor): string
    {
        if ($eigene == -1) {
            return $leer;
        }
        if ($eigene == -2) {
            return '0*';
        }
        if ($andere == -2) {
            return '0';
        }

        return (string) (intval($eigene) / (intval($faktor) ?: 1));
    }
}
