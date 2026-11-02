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

namespace Lmo\Addon\Tipp;

/**
 * Die Tipp-Einsicht eines Spieltags erstellen: einsicht/<liga>_<st>.ein mit einem Abschnitt je
 * Tipper, darin sein Joker (mit Jokertipp) und seine Tipps des Spieltags, wie sie in seiner
 * Tippdatei stehen. Mit von/bis nur ein Teil der Tipper (gezaehlt nach ihren Tippdateien), die
 * anderen bleiben, wie sie waren. Die Einsicht liest EinsichtController. Bis 4.5
 * lmo-tippsaveeinsicht.php.
 */
final class Einsichtsdatei
{
    /**
     * @param string $tippordner Ordner der Tippdateien mit / am Ende (daten/tipp/<dirtipp>)
     * @param bool $joker mit Jokertipp (tipp/cfg.txt jokertipp)
     */
    public function __construct(private string $tippordner, private bool $joker)
    {
    }

    /**
     * Erstellt die Einsicht eines Spieltags.
     *
     * @param string $liga Name der Liga (Dateiname ohne .l98)
     * @param int $von erster Tipper (ab 1, nach Tippdateien)
     * @param int $bis letzter Tipper
     * @return bool false, wenn die Datei nicht geschrieben werden konnte
     */
    public function erstelle(string $liga, int $st, int $von, int $bis): bool
    {
        $einsichtfile = $this->tippordner . 'einsicht/' . $liga . '_' . $st . '.ein';
        $alt = $st > 0 ? $this->alteDaten($einsichtfile, $liga) : [''];

        // die Tippdateien der Liga (<liga>_<nick>.tip), in fester Reihenfolge wie bei der Auswertung;
        // bis 4.5 jede, deren Name mit dem der Liga beginnt (zu "liga" auch die von "liga2"), in der
        // Reihenfolge des Verzeichnisses
        $dateien = [];
        foreach (is_dir($this->tippordner) ? (scandir($this->tippordner) ?: []) : [] as $datei) {
            $unterstrich = strrpos($datei, '_');
            if ($unterstrich !== false && strtolower(substr($datei, 0, $unterstrich)) == strtolower($liga) && strtolower(substr($datei, -4)) == '.tip') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);

        $aus = '';
        foreach ($dateien as $k => $datei) {
            $nick = substr($datei, (int) strrpos($datei, '_') + 1, -4);
            if ($k >= $von - 1 && $k <= $bis - 1) {
                $aus .= "\n[" . $nick . "]\n" . $this->tipps($this->tippordner . $datei, $st);
            } else {
                // nicht erstellte Tipper: wie sie waren
                $abschnitt = '';
                foreach ($alt as $zeile) {
                    if ($zeile != '') {
                        if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                            $abschnitt = substr($zeile, 1, -1);
                        }
                        if ($abschnitt == $nick) {
                            $aus .= $zeile . "\n";
                        }
                    }
                }
            }
        }

        return @file_put_contents($einsichtfile, $aus, LOCK_EX) !== false;
    }

    /**
     * Die Zeilen der alten Einsicht, je Tipper mit seinem Abschnitt; Tipper ohne Tippdatei fallen weg.
     *
     * @return list<string> beginnt mit '' (wie bis 4.5)
     */
    private function alteDaten(string $datei, string $liga): array
    {
        $alt = [''];
        if (!is_file($datei)) {
            return $alt;
        }
        $nick = '';
        foreach (file($datei) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile == '') {
                continue;
            }
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $nick = substr($zeile, 1, -1);
                if (!file_exists($this->tippordner . $liga . '_' . $nick . '.tip')) {
                    $nick = '';
                }
            }
            if ($nick != '') {
                $alt[] = $zeile;
            }
        }

        return $alt;
    }

    /**
     * Joker und Tipps eines Spieltags aus einer Tippdatei, wie sie dort stehen. Der Joker ist die
     * @-Zeile vor dem ersten Eintrag des Spieltags (sie gilt auch ueber Abschnitte hinweg).
     */
    private function tipps(string $tippdatei, int $st): string
    {
        $aus = '';
        $abschnitt = '';
        $jokerZeile = '';
        $mitJoker = false;
        foreach (file($tippdatei) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if (str_starts_with($zeile, '@') && str_ends_with($zeile, '@')) {
                $jokerZeile = trim(substr($zeile, 1, -1));
            } elseif (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                if ($st != substr($abschnitt, 5)) {
                    continue;
                }
                if ($this->joker && !$mitJoker && $st != 0) {
                    $aus .= '@' . $jokerZeile . "@\n";
                    $mitJoker = true;
                }
                $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                if (substr($abschnitt, 0, 5) == 'Round' && in_array(substr($schluessel, 0, 2), ['GA', 'GB'], true)) {
                    $aus .= $schluessel . '=' . trim(substr($zeile, (int) strpos($zeile, '=') + 1)) . "\n";
                }
            }
        }

        return $aus;
    }
}
