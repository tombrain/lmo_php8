<?php

namespace Lmo\Tests\RealData\Support;

use Lmo\Tests\GoldenMaster\Support\Fixture;

/**
 * Tabellen einer Liga mit Kern und classlib rechnen und mit der Vergleichstabelle der Liga
 * (RealLeague) abgleichen: Reihenfolge, Spiele, Punkte, Minuspunkte, Tore.
 */
trait ComparesTables
{
    /** classlib im Testprozess laden (ohne init.php) */
    private static function loadClasslib(): void
    {
        foreach ([
            'PATH_TO_ADDONDIR' => Fixture::sourceRoot() . '/lmo/addon',
            'CLASSLIB_VERSION_NR' => '2.8',
            'CLASSLIB_VERSION' => '(classlib&nbsp;2.8)',
        ] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        require_once PATH_TO_ADDONDIR . '/classlib/classes.php';
    }

    /** @return array<string,array> Liga-ID => [Datei] fuer alle als Pruefmassstab nutzbaren Ligen */
    private static function usableLeagues(): array
    {
        $cases = [];
        foreach (RealLeague::files() as $file) {
            $league = RealLeague::load($file);
            if ($league->usable()) {
                $cases[$league->id()] = [$file];
            }
        }
        return $cases;
    }

    /**
     * Gesamttabelle des Kerns nach dem letzten Spieltag.
     *
     * @param string $relative Ligadatei relativ zum Ligenverzeichnis der Testinstanz
     * @return array<int,array> LMO-Teamnummer => Werte, in der Reihenfolge des Kerns
     */
    private static function coreTable(string $relative, int $rounds): array
    {
        $all = json_decode(Fixture::get()->runner()->calc($relative), true);
        $label = 'tabtype=0 newtabtype=0 action=table endtab=' . $rounds;
        self::assertArrayHasKey($label, $all ?? [], "calc.php lieferte keine Gesamttabelle fuer $relative");
        $entry = $all[$label];
        $rows = [];
        foreach ($entry['tab0'] as $key) {
            $nr = (int)substr($key, -7);
            $rows[$nr] = [
                'points' => $entry['punkte'][$nr], 'goals' => $entry['etore'][$nr],
                'against' => $entry['atore'][$nr], 'matches' => $entry['spiele'][$nr],
                'minus' => $entry['negativ'][$nr],
            ];
        }
        return $rows;
    }

    /** @return array<int,array> LMO-Teamnummer => Werte, in der Reihenfolge von liga::calcTable() */
    private static function classlibTable(\liga $liga, int $rounds): array
    {
        $rows = [];
        foreach ($liga->calcTable($rounds) as $row) {
            $rows[(int)$row['team']->nr] = [
                'points' => $row['pPkt'], 'goals' => $row['pTor'], 'against' => $row['mTor'], 'matches' => $row['spiele'],
                'minus' => $row['mPkt'],
            ];
        }
        return $rows;
    }

    /**
     * @param array<int,array> $actual LMO-Teamnummer => Werte, in der berechneten Reihenfolge
     */
    private static function assertTableMatches(RealLeague $league, array $actual): void
    {
        $expected = $league->expectedRows();
        $expectedOrder = $league->expectedOrder();
        $actualOrder = array_keys($actual);

        $lines = [];
        foreach ($expectedOrder as $pos => $nr) {
            $exp = $expected[$nr];
            // Minuspunkte nur vergleichen, wenn die Erwartung sie nennt (Zwei-Punkte-Regel)
            $act = isset($actual[$nr]) ? array_intersect_key($actual[$nr], $exp) : null;
            $valuesDiffer = $act === null || array_map('intval', $act) != array_map('intval', $exp);
            $atPos = $actualOrder[$pos] ?? null;
            // vollstaendiger Gleichstand ohne Regel (OpenLigaDB, ties_free): Teams mit gleichen Werten sind austauschbar
            $samePlace = $atPos === $nr || ($league->tiesFree() && $atPos !== null && $expected[$atPos] == $exp)
                // bekannte Regelvariante (siehe RealLeague::RULE_VARIANTS): nur die Werte zaehlen
                || $league->ruleVariant() !== null;
            if ($valuesDiffer || !$samePlace) {
                $lines[] = sprintf('%2d. %-28s Quelle %s  berechnet %s  (dort Platz %s)', $pos + 1, $league->teamName($nr),
                    self::fmt($exp), $act ? self::fmt($act) : '-', ($p = array_search($nr, $actualOrder, true)) === false ? '-' : $p + 1);
            }
        }
        self::assertSame([], $lines, $league->name() . ' (Vergleich: ' . $league->source() . ")\n" . implode("\n", $lines));
    }

    private static function fmt(array $row): string
    {
        return sprintf('%2d Sp %3d%s Pkt %3d:%-3d', $row['matches'], $row['points'],
            isset($row['minus']) ? ':' . $row['minus'] : '', $row['goals'], $row['against']);
    }
}
