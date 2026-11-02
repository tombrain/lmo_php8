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
 * Die Gesamtwertung des Tippspiels: aus den Auswertungen der Ligen (auswert/<liga>.aus) je Tipper
 * und Liga die Summe der Punkte (TP<Liga>), der getippten Spiele (SG<Liga>) und mit
 * Zusatzauswertung (showzus) der Wertungen P1 bis P6, in auswert/gesamt.aus. Es zaehlen die Ligen
 * zum Tippen (ligenzutippen, mit immeralle alle Auswertungen). Bis 4.5 lmo-tippsavewertgesamt.php.
 */
final class Gesamtwertung
{
    /**
     * @param string $tippordner Ordner der Tippdateien mit / am Ende (daten/tipp/<dirtipp>)
     * @param string $tipperdatei die Tipperdatei (Team, Name, E-Mail)
     * @param array<string, string> $cfg Einstellungen des Tippspiels (tipp/cfg.txt)
     */
    public function __construct(private string $tippordner, private string $tipperdatei, private array $cfg)
    {
    }

    /** Schreibt die Gesamtwertung; false, wenn das nicht ging. */
    public function werte(): bool
    {
        $cfg = $this->cfg;
        $zusatz = ($cfg['showzus'] ?? '') == 1;
        $name = ($cfg['showname'] ?? '') == 1;
        $email = ($cfg['showemail'] ?? '') == 1;

        // die Tipper in der Reihenfolge der Datei (Leerzeilen zaehlen nicht, bis 4.5 als Tipper mit leerem Nick)
        $tipper = [];
        foreach (is_file($this->tipperdatei) ? (file($this->tipperdatei) ?: []) : [] as $zeile) {
            $zeile = rtrim($zeile, "\r\n");
            if ($zeile != '' && !str_starts_with(trim($zeile), '<?php')) {
                $tipper[] = explode('|', $zeile);
            }
        }

        // die Auswertungen der Ligen zum Tippen, nach Dateinamen
        $zuTippen = explode(',', $cfg['ligenzutippen'] ?? '');
        $dateien = [];
        foreach (is_dir($this->tippordner . 'auswert') ? (scandir($this->tippordner . 'auswert') ?: []) : [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.aus' && strtolower(substr($datei, -10)) != 'gesamt.aus') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $ligen = [];
        foreach ($dateien as $datei) {
            if (in_array(substr($datei, 0, -4), $zuTippen) || !(($cfg['immeralle'] ?? '') == 0)) {
                $ligen[] = $datei;
            }
        }

        // je Tipper und Liga: Punkte, Spiele und die Wertungen 1 bis 6; ein Nick zaehlt fuer den ersten Tipper mit ihm
        $tp = [];
        $sg = [];
        $p = [];
        foreach ($ligen as $k => $liga) {
            $abschnitt = '';
            foreach (file($this->tippordner . 'auswert/' . $liga) ?: [] as $zeile) {
                $zeile = trim($zeile);
                if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                    $abschnitt = trim(substr($zeile, 1, -1));
                    continue;
                }
                if (!str_contains($zeile, '=') || str_starts_with($zeile, ';')) {
                    continue;
                }
                $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                $wert = (int) trim(substr($zeile, (int) strpos($zeile, '=') + 1));
                $art = substr($schluessel, 0, 2);
                $j = null;
                foreach ($tipper as $nr => $felder) {
                    if ($felder[0] == $abschnitt) {
                        $j = $nr;
                        break;
                    }
                }
                if ($j === null) {
                    continue;
                }
                // die Punkte als ganze Zahl, wie bis 4.5 (Kommapunkte durch den Joker fallen weg)
                if ($art == 'TP') {
                    $tp[$j][$k] = ($tp[$j][$k] ?? 0) + $wert;
                } elseif ($art == 'SG') {
                    $sg[$j][$k] = ($sg[$j][$k] ?? 0) + $wert;
                } elseif (substr($art, 0, 1) == 'P' && $zusatz) {
                    $p[$j][$k][substr($art, 1, 1)] = ($p[$j][$k][substr($art, 1, 1)] ?? 0) + $wert;
                }
            }
        }

        $aus = "[Options]\nAnzLigen=" . count($ligen) . "\n";
        foreach ($ligen as $k => $liga) {
            $aus .= 'TT' . ($k + 1) . '=' . $liga . "\n";
        }
        foreach ($tipper as $j => $felder) {
            $aus .= "\n[" . $felder[0] . "]\nTeam=" . ($felder[5] ?? '') . "\n";
            if ($name) {
                $aus .= 'Name=' . ($felder[3] ?? '') . "\n";
            }
            if ($email) {
                $aus .= 'Email=' . ($felder[4] ?? '') . "\n";
            }
            foreach (array_keys($ligen) as $k) {
                $aus .= 'TP' . ($k + 1) . '=' . ($tp[$j][$k] ?? 0) . "\n" . 'SG' . ($k + 1) . '=' . ($sg[$j][$k] ?? 0) . "\n";
                if ($zusatz) {
                    for ($nr = 1; $nr < 7; $nr++) {
                        if (($p[$j][$k][$nr] ?? 0) > 0) {
                            $aus .= 'P' . $nr . ($k + 1) . '=' . $p[$j][$k][$nr] . "\n";
                        }
                    }
                }
            }
        }

        return @file_put_contents($this->tippordner . 'auswert/gesamt.aus', $aus, LOCK_EX) !== false;
    }
}
