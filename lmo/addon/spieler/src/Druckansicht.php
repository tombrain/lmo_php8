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

namespace Lmo\Addon\Spieler;

use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Vorlagen;

/**
 * Die Spielerstatistik einer Liga zum Drucken: eine eigene HTML-Seite, aufgerufen ueber
 * addon/spieler/lmo-statprint.php (Links unter der Statistik). Mit begin die Spieler einer Seite,
 * ohne alle.
 */
final class Druckansicht
{
    /**
     * @param string $ordner Ordner der Statistiken (addon/spieler/stats)
     * @param string $datei Ligadatei (Parameter file)
     * @param array<int, string> $text Sprachtexte des LMO
     * @param array<int, string> $t Sprachtexte des Addons
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public static function html(string $ordner, string $datei, Anfrage $anfrage, Einstellungen $einstellungen, array $text, array $t, string $bilder, Darstellung $darstellung): string
    {
        // Zahlen aus der Anfrage als Zahlen behandeln: mit ihnen wird gerechnet
        $sort = $anfrage->gesetzt('sort') ? (int) $anfrage->text('sort') : $einstellungen->sortierung;
        $alle = !$anfrage->gesetzt('begin');
        $begin = $alle ? 0 : max(0, (int) $anfrage->text('begin'));
        $direction = $anfrage->gesetzt('direction') ? (int) $anfrage->text('direction') : $einstellungen->richtung;
        $team = $anfrage->gesetzt('team') ? urldecode($anfrage->text('team')) : '';

        $statistik = Statistik::lies($ordner . '/' . substr(basename($datei), 0, -4) . '.stat', $team, $einstellungen->vereinsweise, $t[25] ?? '');
        if ($statistik === null) {
            return '<p class="error"><img src="' . $bilder . '/wrong.svg" border="0" width="20" alt=""> ' . ($t[14] ?? '') . '</p>';
        }
        $spalten = $statistik->spalten;
        // Sortierspalte aus der Anfrage oder Konfiguration muss es in der Statistik geben
        if (!isset($spalten[$sort])) {
            $sort = 0;
        }
        $data = $statistik->sortiert($sort, $direction == 1, $einstellungen->nullwerte);
        $zeile = count($data);
        $proSeite = $einstellungen->proSeite;
        if ($begin + $proSeite > $zeile) {
            $maxdisplay = $zeile - $begin;
        } else {
            $maxdisplay = $proSeite;
        }
        if ($proSeite <= 0 || $alle) {
            $maxdisplay = $zeile;
            $begin = 0;
        }

        // gezeigt werden alle Spalten ausser der mit den Spielerlinks und, fuer einen Verein, der mit dem Verein
        $gezeigt = static fn (string $spalte): bool => $spalte != ($t[32] ?? '') && ($spalte != ($t[25] ?? '') || $team == '');
        $zeilen = [];
        for ($j1 = $begin; $j1 < $begin + $maxdisplay; $j1++) {
            $platz = '';
            if (!isset($data[$j1 - 1][$sort]) || ($data[$j1][$sort] ?? null) !== $data[$j1 - 1][$sort] && $j1 != $begin) {
                $platz .= ($j1 + 1) . '. ';
            }
            if ($j1 > 0 && $j1 == $begin) {
                for ($x = $begin - 1; $x >= 0; $x--) {
                    if (($data[$x][$sort] ?? null) != ($data[$j1][$sort] ?? null)) {
                        $platz .= ($x + 2) . '. ';
                        break;
                    }
                    if ($x == 0) {
                        $platz .= '1. ';
                    }
                }
            }
            $zellen = [];
            foreach ($spalten as $j2 => $spalte) {
                if ($gezeigt($spalte)) {
                    $wert = (string) ($data[$j1][$j2] ?? '');
                    $zellen[] = ['wert' => $wert, 'zahl' => is_numeric($wert), 'sortiert' => $j2 == $sort];
                }
            }
            $zeilen[] = [
                'ungerade' => $j1 % 2 != 0,
                'platz' => $platz,
                // Spielerbild
                'bild' => $darstellung->spielerbild((string) ($data[$j1][0] ?? '')),
                'zellen' => $zellen,
            ];
        }

        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'druck.html.twig', [
            'text' => $text,
            't' => $t,
            'team' => $team,
            'spalten' => array_values(array_filter($spalten, $gezeigt)),
            'zeilen' => $zeilen,
        ]);
    }
}
