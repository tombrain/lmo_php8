<?php

namespace Lmo\Tests\Unit\Liga;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Tabelle;
use PHPUnit\Framework\TestCase;

/**
 * Die Einstellungen, die die Reihenfolge der Tabelle bei Punktgleichheit bestimmen:
 * SortGames (weniger Spiele vorn), Direct (direkter Vergleich: 1 = vor der Tordifferenz,
 * 2 = erst nach Tordifferenz und Toren) und DirectAway (Auswaertstore im direkten Vergleich).
 * Vier Mannschaften A bis D mit den Nummern 1 bis 4.
 */
class TabelleSortierungTest extends TestCase
{
    /**
     * @param list<array{int, int, int, int}> ...$spieltage je Spieltag die Spiele: Heim, Gast, Tore Heim, Tore Gast
     */
    private static function reihenfolge(array $optionen, array ...$spieltage): array
    {
        $zeilen = array_merge(['[Options]', 'Teams=4', 'Rounds=' . count($spieltage), 'Matches=2'], $optionen);
        foreach ($spieltage as $nr => $spiele) {
            $zeilen[] = '[Round' . ($nr + 1) . ']';
            foreach ($spiele as $p => [$heim, $gast, $toreHeim, $toreGast]) {
                array_push($zeilen, 'TA' . ($p + 1) . '=' . $heim, 'TB' . ($p + 1) . '=' . $gast, 'GA' . ($p + 1) . '=' . $toreHeim, 'GB' . ($p + 1) . '=' . $toreGast);
            }
        }
        $namen = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'];

        return array_map(static fn (int $nr): string => $namen[$nr], Tabelle::berechne(Liga::ausEintraegen(LigaDatei::zerlege($zeilen)))->reihenfolge);
    }

    /**
     * A und B haben je 3 Punkte: A aus einem Spiel (1:0), B aus zweien (5:1).
     */
    public function testWenigerSpieleVorn(): void
    {
        $spieltage = [[[1, 2, 1, 0]], [[2, 3, 5, 0]]];

        self::assertSame(['B', 'A', 'D', 'C'], self::reihenfolge([], ...$spieltage), 'ohne die Einstellung entscheidet die Tordifferenz');
        self::assertSame(['A', 'B', 'D', 'C'], self::reihenfolge(['SortGames=1'], ...$spieltage));
    }

    /**
     * A und B haben je 3 Punkte; A gewann gegen B, B hat die bessere Tordifferenz (3:1 gegen 1:2).
     */
    public function testDirekterVergleichVorOderNachDerTordifferenz(): void
    {
        $spieltage = [[[1, 2, 1, 0], [3, 4, 0, 0]], [[2, 3, 3, 0], [1, 4, 0, 2]]];

        self::assertSame(['D', 'B', 'A', 'C'], self::reihenfolge([], ...$spieltage), 'ohne direkten Vergleich');
        self::assertSame(['D', 'A', 'B', 'C'], self::reihenfolge(['Direct=1'], ...$spieltage), 'direkter Vergleich vor der Tordifferenz');
        self::assertSame(['D', 'B', 'A', 'C'], self::reihenfolge(['Direct=2'], ...$spieltage), 'die Tordifferenz entscheidet schon');
    }

    /**
     * A und B liegen in Punkten, Tordifferenz und Toren gleichauf (je 3 Punkte, 1:1); A gewann gegen B.
     */
    public function testDirekterVergleichNachDenTorenEntscheidetBeiVoelligemGleichstand(): void
    {
        $spieltage = [[[1, 2, 1, 0], [3, 4, 0, 0]], [[3, 1, 1, 0], [2, 4, 1, 0]]];

        self::assertSame(['C', 'B', 'A', 'D'], self::reihenfolge([], ...$spieltage), 'ohne direkten Vergleich steht die hoehere Nummer vorn');
        self::assertSame(['C', 'A', 'B', 'D'], self::reihenfolge(['Direct=2'], ...$spieltage));
    }

    /**
     * Mit "weniger Spiele vorn" liegen nur Mannschaften mit gleich vielen Spielen gleichauf:
     * B (3 Punkte aus zwei Spielen) steht vor A (3 Punkte aus drei Spielen), obwohl A das direkte
     * Duell gewann. C und D haben je 5 Punkte.
     */
    public function testDirekterVergleichNurBeiGleichVielenSpielen(): void
    {
        $spieltage = [[[1, 2, 1, 0], [3, 4, 0, 0]], [[2, 3, 1, 0], [1, 4, 0, 1]], [[3, 1, 1, 0]], [[4, 3, 0, 0]]];

        self::assertSame(['A', 'B'], array_slice(self::reihenfolge(['Direct=1'], ...$spieltage), 2), 'A gewann gegen B');
        self::assertSame(['B', 'A'], array_slice(self::reihenfolge(['Direct=1', 'SortGames=1'], ...$spieltage), 2));
    }

    /**
     * Hin- und Rueckspiel A - B 2:1 und B - A 1:0: im direkten Vergleich gleichauf (je 3 Punkte, 2:2).
     * B traf dabei einmal auswaerts, A nicht. In der ganzen Tabelle hat A die bessere Tordifferenz.
     */
    public function testAuswaertstoreImDirektenVergleich(): void
    {
        $spieltage = [[[1, 2, 2, 1], [3, 4, 0, 0]], [[2, 1, 1, 0], [3, 4, 0, 0]], [[1, 3, 3, 0], [2, 4, 1, 0]]];

        self::assertSame(['A', 'B'], array_slice(self::reihenfolge(['Direct=1'], ...$spieltage), 0, 2), 'im direkten Vergleich gleichauf: die Reihenfolge der Tabelle bleibt');
        self::assertSame(['B', 'A'], array_slice(self::reihenfolge(['Direct=1', 'DirectAway=1'], ...$spieltage), 0, 2));
        self::assertSame(['A', 'B'], array_slice(self::reihenfolge(['DirectAway=1'], ...$spieltage), 0, 2), 'ohne direkten Vergleich wirkt die Einstellung nicht');
    }

    /**
     * A - B 1:1 und B - A 1:1: auch die Auswaertstore im direkten Vergleich sind gleich (je eines).
     * Dann zaehlen alle Auswaertstore: B traf noch einmal auswaerts bei D, A nur zu Hause.
     */
    public function testAlleAuswaertstoreWennAuchDerDirekteVergleichGleichIst(): void
    {
        $spieltage = [[[1, 2, 1, 1], [3, 4, 0, 0]], [[2, 1, 1, 1], [4, 3, 0, 0]], [[1, 3, 4, 0], [4, 2, 0, 1]]];

        self::assertSame(['A', 'B'], array_slice(self::reihenfolge(['Direct=1'], ...$spieltage), 0, 2));
        self::assertSame(['B', 'A'], array_slice(self::reihenfolge(['Direct=1', 'DirectAway=1'], ...$spieltage), 0, 2));
    }

    /** Drei Mannschaften gleichauf: der direkte Vergleich gilt fuer die ganze Gruppe, nicht nur paarweise. */
    public function testAuswaertstoreBeiDreiMannschaften(): void
    {
        // jeder schlaegt einen und verliert gegen einen, immer 1:0 fuer die Heimmannschaft, nur C gewinnt auswaerts
        $spieltage = [[[1, 2, 1, 0]], [[2, 3, 1, 0]], [[1, 3, 0, 1]]];

        // je 3 Punkte, 1:1 Tore; ohne Auswaertstore bleibt die Reihenfolge der Tabelle (hoehere Nummer vorn)
        self::assertSame(['C', 'B', 'A', 'D'], self::reihenfolge(['Direct=1'], ...$spieltage));
        // C traf auswaerts, A und B nicht
        self::assertSame('C', self::reihenfolge(['Direct=1', 'DirectAway=1'], ...$spieltage)[0]);
    }
}
