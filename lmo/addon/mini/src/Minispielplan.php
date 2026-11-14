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

namespace Lmo\Addon\Mini;

use Lmo\Liga\Begegnung;
use Lmo\Liga\Begegnungen;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Bilder;

/**
 * Der Mini-Spielplan (lmo-mininext.php): das naechste Spiel einer Mannschaft (a, sonst die
 * Lieblingsmannschaft) gegen b (sonst den naechsten Gegner) mit Countdown, dem Spiel davor und den
 * bisherigen Ergebnissen beider, auch aus den Ligen des Archivs. Liefert die Werte fuer die Vorlage
 * (Lmo\Addon\Mini\Vorlagen) unter den Namen der Platzhalter.
 */
final class Minispielplan
{
    /**
     * @param array<int|string, mixed> $text die Sprachtexte (mit denen des Mini unter 'mini')
     */
    public function __construct(private array $text, private Bilder $bilder)
    {
    }

    /**
     * Die Werte.
     *
     * @param string $datei die Ligadatei (Pfad)
     * @param int|string|null $a Nummer der Mannschaft a; null: die Lieblingsmannschaft
     * @param int|string|null $b Nummer der Mannschaft b; null: der naechste Gegner
     * @param \Closure(): list<string> $archiv die Ligadateien des Archivs (Pfade); erst gefragt, wenn es ein Spiel gibt
     * @param int $jetzt die Zeit fuer naechstes Spiel und Countdown
     * @return array{abbruch: string|null, werte: array<string, string>, bloecke: array<string, list<array<string, string>>>}
     */
    public function werte(Liga $roh, string $datei, int|string|null $a, int|string|null $b, \Closure $archiv, bool $aehnlich, int|float|string $balken, int $jetzt): array
    {
        $liga = Begegnungen::aus($roh);
        $werte = [];
        $bloecke = ['previous' => [], 'matches' => []];
        $favorit = $liga->mannschaft((string) $roh->einstellungen()->wert('favTeam'));
        $teamA = null;
        if ($a !== null) {
            $teamA = $liga->mannschaft($a);
        } elseif (($teamA = $favorit) === null) {
            return ['abbruch' => $this->t(8), 'werte' => [], 'bloecke' => []];
        }

        $partie = null;
        $vorher = null;
        $letztesSpiel = true;
        $teamB = $liga->mannschaft($b);
        if ($teamB === null) {
            // der naechste Gegner von a (ohne a: der Lieblingsmannschaft)
            $fuer = $teamA ?? $favorit;
            foreach ($fuer === null ? [] : $liga->nachZeit($fuer) as $spiel) {
                if ($jetzt < $spiel->zeit) {
                    $partie = $spiel;
                    $werte['gameTxt'] = $this->t(1);
                    $werte['gameNote'] = $partie->notiz;
                    break;
                }
                $vorher = $spiel;
            }
            if ($partie === null) {
                // kein Spiel mehr: das letzte (Saison zu Ende)
                $partie = $vorher;
                $vorher = null;
                $letztesSpiel = false;
                $werte['gameTxt'] = $this->t(2);
            }
            if ($partie !== null) {
                $teamB = $partie->heim == $teamA ? $partie->gast : $partie->heim;
            }
        } else {
            // a und b angegeben: ihr Spiel
            $partie = $teamA === null ? null : ($liga->zwischen($teamA, $teamB, false)[0] ?? null);
            $werte['gameTxt'] = $this->t(3);
        }

        if ($partie !== null) {
            $heim = $liga->daten($partie->heim);
            $gast = $liga->daten($partie->gast);
            $werte['gameDate'] = $partie->datum();
            $werte['gameTime'] = $partie->uhrzeit();
            $werte['ligaDatum'] = $this->t(14) . ': ' . date('d.m.Y', (int) filemtime($datei));
            $rest = (int) $partie->zeit - $jetzt;
            $tage = floor($rest / 86400);
            $rest -= $tage * 86400;
            $stunden = floor($rest / 3600);
            $rest -= $stunden * 3600;
            $minuten = floor($rest / 60);
            $werte['countDown'] = $this->t(15) . ': ' . $tage . ', ' . $this->t(16) . ': ' . $stunden . ', ' . $this->t(17) . ': ' . $minuten;
            // die Fusszeile (bis 4.5 mit der Version der Classlib)
            $werte['copy'] = $this->t(0);
            $werte += $this->mannschaften('', (string) $heim->name, (string) $gast->name);
            $werte['homeName'] = (string) $heim->name;
            $werte['guestName'] = (string) $gast->name;
            $werte['homeNameMiddle'] = (string) $heim->mittel;
            $werte['guestNameMiddle'] = (string) $gast->mittel;
            $werte['homeNameShort'] = (string) $heim->kurz;
            $werte['guestNameShort'] = (string) $gast->kurz;
            if (!$letztesSpiel) {
                $werte['homeTore'] = $partie->toreHeimText();
                $werte['guestTore'] = $partie->toreGastText();
            }

            // das Spiel davor
            if ($vorher !== null) {
                $vorherHeim = $liga->daten($vorher->heim);
                $vorherGast = $liga->daten($vorher->gast);
                $bloecke['previous'][] = [
                    'previous_gameDate' => $vorher->datum(),
                    'previous_gameTime' => $vorher->uhrzeit(),
                    'previous_gameNote' => $vorher->notiz,
                ] + $this->mannschaften('previous_', (string) $vorherHeim->name, (string) $vorherGast->name) + [
                    'previous_homeName' => (string) $vorherHeim->name,
                    'previous_guestName' => (string) $vorherGast->name,
                    'previous_homeNameMiddle' => (string) $vorherHeim->mittel,
                    'previous_guestNameMiddle' => (string) $vorherGast->mittel,
                    'previous_homeNameShort' => (string) $vorherHeim->kurz,
                    'previous_guestNameShort' => (string) $vorherGast->kurz,
                    'previous_hTore' => $vorher->toreHeimText(),
                    'previous_gTore' => $vorher->toreGastText(),
                    'previous_gameTxt' => $this->t(7),
                ];
            }

            [$bloecke['matches'], $werte] = $this->bisherige($liga, $datei, $teamA, $teamB, $archiv(), $aehnlich, $balken, $werte);
        }

        return ['abbruch' => null, 'werte' => $werte, 'bloecke' => $bloecke];
    }

    /**
     * Die bisherigen Spiele beider, neueste zuerst, und die Bilanz.
     *
     * @param list<string> $archiv
     * @param array<string, string> $werte
     * @return array{list<array<string, string>>, array<string, string>}
     */
    private function bisherige(Begegnungen $liga, string $datei, ?int $teamA, ?int $teamB, array $archiv, bool $aehnlich, int|float|string $balken, array $werte): array
    {
        $heimTxt = $this->t(13);
        $gastTxt = $this->t(12);
        $paarungen = [];
        $nameB = (string) $liga->daten((int) $teamB)->name;
        foreach ($teamA === null || $teamB === null ? [] : $liga->zwischen($teamA, $teamB, true) as $spiel) {
            if ($spiel->toreHeim != -1 && $spiel->toreGast != -1) {
                $paarungen[] = ['zeit' => $spiel->zeit, 'wo' => $spiel->heim == $teamA ? $heimTxt : $gastTxt, 'spiel' => $spiel, 'treffer' => null];
            }
        }
        $nameA = (string) $liga->daten((int) $teamA)->name;
        foreach ($archiv as $ligadatei) {
            if ($ligadatei == $datei || !is_file($ligadatei)) {
                continue;
            }
            $andere = Begegnungen::aus(Liga::lies($ligadatei));
            $andereA = $andere->mannschaftNamens($nameA);
            // unscharfe Suche nach b; nur ein Treffer zaehlt
            $namen = $aehnlich ? Begegnungen::aehnlicheNamen($andere->namen(), $nameB) : null;
            if ($namen !== null && count($namen) == 1) {
                $andereB = $andere->mannschaftNamens($namen[0]);
                $treffer = $namen[0];
            } else {
                $andereB = $andere->mannschaftNamens($nameB);
                $treffer = null;
            }
            if ($andereA !== null && $andereB !== null) {
                foreach ($andere->zwischen($andereA, $andereB, true) as $spiel) {
                    if ($spiel->toreHeim != -1 && $spiel->toreGast != -1) {
                        $paarungen[] = ['zeit' => $spiel->zeit, 'wo' => $spiel->heim == $andereA ? $heimTxt : $gastTxt, 'spiel' => $spiel, 'treffer' => $treffer];
                    }
                }
            }
        }
        // die neuesten zuerst (bei gleicher Zeit nach dem Ort, absteigend)
        usort($paarungen, static fn (array $x, array $y): int => [$y['zeit'], $y['wo']] <=> [$x['zeit'], $x['wo']]);

        $zeilen = [];
        $siege = $remis = $niederlagen = 0;
        foreach ($paarungen as $p) {
            /** @var Begegnung $spiel */
            $spiel = $p['spiel'];
            $zeile = ['date' => $spiel->datum(), 'hTore' => $spiel->toreHeimText(), 'gTore' => $spiel->toreGastText(), 'where' => $p['wo']];
            if ($p['treffer'] !== null && strtolower($p['treffer']) != strtolower($nameB)) {
                $zeile['matchingName'] = '<acronym title="' . $p['treffer'] . '">*</acronym>';
            }
            $wertung = $spiel->wertung();
            $umgekehrt = ['hTore' => $spiel->toreGastText(), 'gTore' => $spiel->toreHeimText()];
            if ($p['wo'] == $heimTxt && $wertung == 1) {
                $zeile['class'] = 'win';
                $siege++;
            } elseif ($p['wo'] == $gastTxt && $wertung == 2) {
                $zeile = ['class' => 'win'] + $umgekehrt + $zeile;
                $siege++;
            } elseif ($p['wo'] == $heimTxt && $wertung == 2) {
                $zeile['class'] = 'lost';
                $niederlagen++;
            } elseif ($p['wo'] == $gastTxt && $wertung == 1) {
                $zeile = ['class' => 'lost'] + $umgekehrt + $zeile;
                $niederlagen++;
            } elseif ($wertung == 0) {
                $zeile['class'] = 'draw';
                $remis++;
            } else {
                $zeile['class'] = 'noResult';
            }
            $zeilen[] = $zeile;
        }

        $anzahl = count($paarungen);
        $breite = static fn (int $wie): string => (string) intval((float) $balken * $wie / ($anzahl + .1));
        $werte['matchesTxt'] = $this->t(4);
        $werte['winCount'] = (string) $siege;
        $werte['drawCount'] = (string) $remis;
        $werte['lostCount'] = (string) $niederlagen;
        $werte['matchCount'] = (string) $anzahl;
        $werte['winWidth'] = $breite($siege);
        $werte['drawWidth'] = $breite($remis);
        $werte['lostWidth'] = $breite($niederlagen);
        $werte['winTxt'] = $this->t(9);
        $werte['drawTxt'] = $this->t(10);
        $werte['lostTxt'] = $this->t(11);

        return [$zeilen, $werte];
    }

    /**
     * Die Wappen von Heim und Gast in allen drei Groessen.
     *
     * @return array<string, string>
     */
    private function mannschaften(string $vorsatz, string $heim, string $gast): array
    {
        $werte = [];
        foreach (['small' => 'Small', 'middle' => 'Middle', 'big' => 'Big'] as $groesse => $name) {
            $werte[$vorsatz . 'imgHome' . $name] = $this->bilder->bild($heim, 'teams', $groesse);
        }
        foreach (['small' => 'Small', 'middle' => 'Middle', 'big' => 'Big'] as $groesse => $name) {
            $werte[$vorsatz . 'imgGuest' . $name] = $this->bilder->bild($gast, 'teams', $groesse);
        }

        return $werte;
    }

    /** Ein Sprachtext des Mini */
    private function t(int $nr): string
    {
        $mini = is_array($this->text['mini'] ?? null) ? $this->text['mini'] : [];
        $wert = $mini[$nr] ?? '';

        return is_scalar($wert) ? (string) $wert : '';
    }
}
