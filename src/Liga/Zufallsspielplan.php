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

use Closure;

/**
 * Ein ausgeloster Spielplan fuer eine neue Liga (Hin- und Rueckrunde, jeder gegen jeden). Bis 4.5
 * lmo-adminrndprogram.php, von Sebastian Janke (s.janke@tu-bs.de, 2005).
 *
 * Das Vergabesystem arbeitet mit einem Tischemodell. An jedem Tisch sitzen sich 2 Spieler
 * gegenueber; jeder Tisch hat eine Heimrecht- und eine Auswaertsseite.
 * Beispiel mit 4 Tischen und 8 Sitzplaetzen (die Plaetze werden in der Reihenfolge 0-7 vergeben):
 *
 *   7 1 2 3
 *   X o-o-o\
 *   T/T l T|
 *   o-o-o-o/
 *   0 6 5 4
 *
 * T = Tisch (Heimrecht oben), l = Tisch (Heimrecht unten), X = Spieler mit dem festen Sitzplatz,
 * o = Spieler, der gegen den Uhrzeigersinn rotieren muss; - | / \ = Rotationswege.
 * Zu Beginn eines neuen Spieltags rutschen alle Spieler ohne festen Sitzplatz einen Platz gegen
 * den Uhrzeigersinn weiter. Die Positionsnummer von X ist immer der hoechste Index.
 *
 * Danach werden die Plaetze zufaellig auf die Mannschaften verteilt (Fisher-Yates). Bei
 * ungerader Zahl spielt ein Platzhalter mit; seine Spiele fallen weg (spielfrei).
 */
final class Zufallsspielplan
{
    /**
     * Die Paarungen je Spieltag (ab 0): je Spiel [Heim, Gast] als Nummern der Mannschaften ab 1.
     * Es sind immer Hin- und Rueckrunde; wer nur die Hinrunde braucht, nimmt die ersten Spieltage.
     *
     * @param Closure(int, int): int|null $zufall Zufallszahl zwischen beiden Grenzen (mt_rand)
     * @return array<int, list<array{int, int}>>
     */
    public static function paarungen(int $mannschaften, ?Closure $zufall = null): array
    {
        $zufall ??= static fn (int $von, int $bis): int => mt_rand($von, $bis);
        // Anzahl der Spieler
        $spieler = $mannschaften;
        $dummy = false;
        if ($mannschaften % 2 == 1) {
            $spieler++;
            $dummy = true;
        }

        // die Kreuztabelle: [Spieltag][Spieler mit Heimrecht] => Gegner (Auswaerts), -1 ohne Spiel
        $plan = [];
        $spieltageHinrunde = $spieler - 1;
        $spieltage = ($spieler - 1) * 2;
        for ($spieltag = 0; $spieltag < max($spieltage, 40); $spieltag++) {
            $plan[$spieltag] = array_fill(0, max($spieler, 40), -1);
        }

        // Hat der feste Platz zu Beginn Heimrecht? Dieses Recht wechselt an jedem Spieltag, der Tisch wird gedreht.
        $festerPlatzIsHome = true;
        // Spieler X, Sitzbelegung durch Spieler aendert sich nicht
        $spielerNrFesterPlatz = $spieler - 1;

        // Hauptschleife, laeuft sequentiell ueber die Spieltage der Hinrunde
        for ($spieltag = 0; $spieltag < $spieltageHinrunde; $spieltag++) {
            // 1. Abschnitt: Paarung des Spielers auf dem festen Sitzplatz
            $spielerNrGegenueberFesterPlatz = $spieltag;
            if ($festerPlatzIsHome) {
                $plan[$spieltag][$spielerNrFesterPlatz] = $spielerNrGegenueberFesterPlatz;
                $plan[$spieltag + $spieltageHinrunde][$spielerNrGegenueberFesterPlatz] = $spielerNrFesterPlatz;
            } else {
                $plan[$spieltag][$spielerNrGegenueberFesterPlatz] = $spielerNrFesterPlatz;
                $plan[$spieltag + $spieltageHinrunde][$spielerNrFesterPlatz] = $spielerNrGegenueberFesterPlatz;
            }

            // 2. Abschnitt: Alle weiteren Paarungen
            $beweglicheSpieler = $spieler - 1;
            $zielSitzGerade = 2;
            $zielSitzUngerade = $beweglicheSpieler - $zielSitzGerade;
            $zielSitzGeradeVerschoben = ($zielSitzGerade + $spieltag) % $beweglicheSpieler;
            $zielSitzUngeradeVerschoben = ($zielSitzUngerade + $spieltag) % $beweglicheSpieler;

            // Innere Schleife, laeuft ueber alle Paarungen EINES Spieltages
            while ($zielSitzGerade < $beweglicheSpieler) {
                $plan[$spieltag][$zielSitzGeradeVerschoben] = $zielSitzUngeradeVerschoben;
                $plan[$spieltag + $spieltageHinrunde][$zielSitzUngeradeVerschoben] = $zielSitzGeradeVerschoben;

                // Weiterrutschen der beweglichen Spieler
                $zielSitzGerade += 2;
                $zielSitzUngerade -= 2;
                $zielSitzGeradeVerschoben = ($zielSitzGerade + $spieltag) % $beweglicheSpieler;
                $zielSitzUngeradeVerschoben = ($zielSitzUngerade + $spieltag) % $beweglicheSpieler;
            }
            // 1. Tisch (mit festem Spieler) drehen, Heimrecht wechselt
            $festerPlatzIsHome = !$festerPlatzIsHome;
        }

        // die Plaetze zufaellig auf die Mannschaften verteilen (Fisher-Yates)
        $abbildung = range(1, $spieler);
        for ($i = count($abbildung) - 1; $i > 0; $i--) {
            // 0 bis $i: mit $i+1 lag der Index manchmal ausserhalb des Arrays, der Spielplan verlor ein Team
            $j = $zufall(0, $i);
            [$abbildung[$i], $abbildung[$j]] = [$abbildung[$j], $abbildung[$i]];
        }

        $paarungen = [];
        for ($spieltag = 0; $spieltag < $spieltage; $spieltag++) {
            $paarungen[$spieltag] = [];
            for ($heim = 0; $heim < $spieler; $heim++) {
                $gast = $plan[$spieltag][$heim];
                if ($gast > -1) {
                    $heimAbbild = $abbildung[$heim];
                    $gastAbbild = $abbildung[$gast];
                    // Platzhalter "spielfrei" (Mannschaft $spieler) nicht eintragen
                    if (!$dummy || ($heimAbbild != $spielerNrFesterPlatz + 1 && $gastAbbild != $spielerNrFesterPlatz + 1)) {
                        $paarungen[$spieltag][] = [$heimAbbild, $gastAbbild];
                    }
                }
            }
        }

        return $paarungen;
    }
}
