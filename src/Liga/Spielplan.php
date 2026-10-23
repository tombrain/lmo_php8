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

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Partie;

/**
 * Aenderungen am Spielplan einer Liga in den Rohdaten: den Rueckrundenspielplan erstellen und eine
 * Partie in einen anderen Spieltag verschieben. Bis 4.5 ueber die Classlib (lmo-rueckrunde.php,
 * lmo-adminrounds.php); gezaehlt wird wie dort: die Partien eines Spieltags von der ersten an,
 * solange sie Mannschaften nennen (auch leere Plaetze mit 0).
 */
final class Spielplan
{
    /**
     * Die zweite Haelfte der Spieltage mit den Partien der ersten, mit getauschtem Heimrecht und
     * ohne Ergebnis. Was dort stand, faellt weg; Tage und Tabellenkorrektur bleiben.
     *
     * @return bool false bei ungerader Anzahl der Spieltage (nichts geaendert)
     */
    public static function rueckrunde(Liga $liga): bool
    {
        $spieltage = (int) ($liga->optionen['Rounds'] ?? 0);
        if ($spieltage % 2 != 0) {
            return false;
        }
        $haelfte = intdiv($spieltage, 2);
        for ($i = 1; $i <= $haelfte; $i++) {
            $hinspiele = self::partien($liga, $i);
            $rueckrunde = $liga->spieltag($i + $haelfte);
            $rueckrunde->partien = [];
            foreach ($hinspiele as $nr => $partie) {
                $neu = Partie::leer();
                $neu->setzeMannschaften((int) $partie->gast, (int) $partie->heim);
                $rueckrunde->partien[$nr] = $neu;
            }
        }

        return true;
    }

    /**
     * Verschiebt die Partie Heim gegen Gast vom einen in den anderen Spieltag: dort faellt sie weg
     * (die folgenden ruecken auf), hier kommt sie auf den ersten leeren Platz (0-0, wie ihn das
     * Speichern bis zur Anzahl der Partien auffuellt), sonst hinter die letzte. Die Anzahl der
     * Partien je Spieltag (Matches) waechst, wenn ein Spieltag nun mehr hat.
     *
     * @return bool false, wenn es einen der Spieltage oder die Partie dort nicht gibt
     */
    public static function verschiebe(Liga $liga, int $von, int $nach, int $heim, int $gast): bool
    {
        if (!isset($liga->spieltage[$von], $liga->spieltage[$nach]) || $von == $nach) {
            return false;
        }
        $alt = self::partien($liga, $von);
        $gefunden = null;
        foreach ($alt as $nr => $partie) {
            if ((int) $partie->heim == $heim && (int) $partie->gast == $gast) {
                $gefunden = $nr;
                break;
            }
        }
        if ($gefunden === null) {
            return false;
        }
        $partie = $alt[$gefunden];
        unset($alt[$gefunden]);
        $liga->spieltage[$von]->partien = self::abEins(array_values($alt));
        $neu = array_values(self::partien($liga, $nach));
        $leer = null;
        foreach ($neu as $i => $platz) {
            if ((int) $platz->heim == 0 && (int) $platz->gast == 0) {
                $leer = $i;
                break;
            }
        }
        if ($leer !== null) {
            $neu[$leer] = $partie;
        } else {
            $neu[] = $partie;
        }
        $liga->spieltage[$nach]->partien = self::abEins(array_values($neu));

        $hoechste = 0;
        foreach (array_keys($liga->spieltage) as $nr) {
            $hoechste = max($hoechste, count(self::partien($liga, $nr)));
        }
        if ($hoechste > $liga->einstellungen()->partienJeSpieltag()) {
            $liga->setzeEinstellung('Matches', (string) $hoechste);
        }

        return true;
    }

    /**
     * Die Partien eines Spieltags von der ersten an, solange sie Mannschaften nennen.
     *
     * @return array<int, Partie>
     */
    private static function partien(Liga $liga, int $spieltag): array
    {
        $partien = [];
        $alle = $liga->spieltage[$spieltag]->partien ?? [];
        for ($nr = 1; isset($alle[$nr]) && $alle[$nr]->heim !== null; $nr++) {
            $partien[$nr] = $alle[$nr];
        }

        return $partien;
    }

    /**
     * @param list<Partie> $partien
     * @return array<int, Partie> ab Nummer 1
     */
    private static function abEins(array $partien): array
    {
        return $partien === [] ? [] : array_combine(range(1, count($partien)), $partien);
    }
}
