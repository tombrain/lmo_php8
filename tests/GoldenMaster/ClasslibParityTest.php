<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Matrix;
use PHPUnit\Framework\TestCase;

/**
 * Kern (lmo-calctable.php) und classlib (liga::calcTable()) muessen dieselbe Tabelle liefern:
 * fuer jede Liga der Testinstanz, in Gesamt-, Heim-, Auswaerts-, Hin- und Rueckrundentabelle
 * und nach jedem einzelnen Spieltag. Verglichen werden Spiele, Punkte, Minuspunkte, Tore,
 * Gegentore und die Reihenfolge - auf jedem Platz muss dasselbe Team stehen, auch bei
 * vollstaendigem Gleichstand (hoehere Teamnummer vorn) und mit Handicap-Reihenfolge (HandS).
 */
final class ClasslibParityTest extends TestCase
{
    /** tabtype von lmo-calctable.php => Tabellenart von liga::calcTable() */
    private const ARTEN = [0 => 'all', 1 => 'heim', 2 => 'gast', 3 => 'rueck', 4 => 'hin'];

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

    public static function leagues(): array
    {
        $cases = [];
        foreach (array_keys(Matrix::leagues(Fixture::sourceRoot())) as $file) {
            $cases[$file] = [$file];
        }
        return $cases;
    }

    /** @dataProvider leagues */
    public function testClasslibCalculatesTheSameTablesAsTheCore(string $leagueFile): void
    {
        $fixture = Fixture::get();
        $core = json_decode($fixture->runner()->calc($leagueFile, true), true);
        $liga = new \liga();
        $liga->loadFile($fixture->app()->ligenDir() . '/' . $leagueFile);
        if (($liga->options->keyValues['Type'] ?? 0) == 1) {
            self::markTestSkipped('Pokal: keine Tabelle');
        }
        $rounds = $liga->spieltageCount();
        $minus = ($liga->options->keyValues['MinusPoints'] ?? 1) == 2;

        $lines = [];
        $views = 0;
        foreach ($core as $label => $entry) {
            if (!preg_match('/^tabtype=(\d) newtabtype=0 action=table endtab=(\d+)$/', $label, $m) || empty($entry['tab0'])) {
                continue;
            }
            [$tabtype, $endtab] = [(int)$m[1], (int)$m[2]];
            $views++;
            // Kern: die Hinrundentabelle endet immer bei der Saisonhaelfte
            $table = $liga->calcTable($tabtype === 4 ? $rounds : $endtab, self::ARTEN[$tabtype]);

            $coreRows = [];
            foreach ($entry['tab0'] as $key) {
                $nr = (int)substr($key, -7);
                $coreRows[] = [$nr, [$entry['spiele'][$nr], $entry['punkte'][$nr], $minus ? $entry['negativ'][$nr] : 0, $entry['etore'][$nr], $entry['atore'][$nr]]];
            }
            $libRows = [];
            foreach ($table as $row) {
                $libRows[] = [(int)$row['team']->nr, [$row['spiele'], $row['pPkt'], $minus ? $row['mPkt'] : 0, $row['pTor'], $row['mTor']]];
            }
            $libValues = array_column($libRows, 1, 0);
            foreach ($coreRows as $pos => [$nr, $values]) {
                $sameValues = isset($libValues[$nr]) && array_map('intval', $libValues[$nr]) === array_map('intval', $values);
                $samePlace = isset($libRows[$pos]) && $libRows[$pos][0] === $nr;
                if (!$sameValues || !$samePlace) {
                    $lines[] = sprintf('%s nach Spieltag %d, Platz %d: Kern Team %d %s, classlib Team %d %s',
                        self::ARTEN[$tabtype], $endtab, $pos + 1, $nr, implode('/', $values),
                        $libRows[$pos][0] ?? 0, implode('/', $libRows[$pos][1] ?? []));
                }
            }
        }

        self::assertGreaterThan(0, $views, 'keine Tabellen verglichen');
        self::assertSame([], array_slice($lines, 0, 20), count($lines) . ' Abweichungen (Spiele/Punkte/Minuspunkte/Tore/Gegentore)');
    }
}
