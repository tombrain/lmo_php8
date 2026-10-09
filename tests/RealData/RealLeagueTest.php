<?php

namespace Lmo\Tests\RealData;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\RealData\Support\RealLeague;
use PHPUnit\Framework\TestCase;

/**
 * Echte Ligen (tests/RealData/fixtures): Kern (lmo-calctable.php) und classlib (liga::calcTable())
 * muessen dieselbe Tabelle liefern wie die Quelle - Reihenfolge, Spiele, Punkte und Tore.
 */
final class RealLeagueTest extends TestCase
{
    /** @var array<string,array> Kern-Ergebnis je Liga (calc.php rechnet alle Ansichten auf einmal) */
    private static array $core = [];

    public static function setUpBeforeClass(): void
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

    /** Ligadateien wieder entfernen: die Instanz teilen sich alle Suites (Ligenuebersicht!) */
    public static function tearDownAfterClass(): void
    {
        $dir = Fixture::get()->app()->ligenDir() . '/real';
        foreach (glob($dir . '/*.l98') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    public static function leagues(): array
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

    /** @dataProvider leagues */
    public function testCoreMatchesSourceTable(string $file): void
    {
        $league = RealLeague::load($file);
        $entry = $this->core($league);

        $rows = [];
        foreach ($entry['tab0'] as $key) {
            $nr = (int)substr($key, -7);
            $rows[$nr] = [
                'points' => $entry['punkte'][$nr], 'goals' => $entry['etore'][$nr],
                'against' => $entry['atore'][$nr], 'matches' => $entry['spiele'][$nr],
                'minus' => $entry['negativ'][$nr],
            ];
        }
        self::assertTableMatches($league, $rows);
    }

    /** @dataProvider leagues */
    public function testClasslibMatchesSourceTable(string $file): void
    {
        $league = RealLeague::load($file);
        $path = sys_get_temp_dir() . '/real-' . $league->id() . '.l98';
        file_put_contents($path, $league->toL98());
        $liga = new \liga();
        $liga->loadFile($path);
        unlink($path);

        $rows = [];
        foreach ($liga->calcTable($league->rounds()) as $row) {
            $rows[(int)$row['team']->nr] = [
                'points' => $row['pPkt'], 'goals' => $row['pTor'], 'against' => $row['mTor'], 'matches' => $row['spiele'],
                'minus' => $row['mPkt'],
            ];
        }
        self::assertTableMatches($league, $rows);
    }

    /** Gesamttabelle des Kerns nach dem letzten Spieltag. */
    private function core(RealLeague $league): array
    {
        if (!isset(self::$core[$league->id()])) {
            $fixture = Fixture::get();
            $relative = 'real/' . $league->id() . '.l98';
            @mkdir($fixture->app()->ligenDir() . '/real');
            file_put_contents($fixture->app()->ligenDir() . '/' . $relative, $league->toL98());
            $all = json_decode($fixture->runner()->calc($relative), true);
            $label = 'tabtype=0 newtabtype=0 action=table endtab=' . $league->rounds();
            self::assertArrayHasKey($label, $all, 'calc.php lieferte keine Gesamttabelle');
            self::$core[$league->id()] = $all[$label];
        }
        return self::$core[$league->id()];
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
