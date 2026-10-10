<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Instance;
use Lmo\Tests\GoldenMaster\Support\Matrix;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use Lmo\Tests\GoldenMaster\Support\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Addon "mini": Minitabelle (lmo-minitab.php) und Mini-Spielplan (lmo-mininext.php), direkt
 * aufgerufen wie in einem IFRAME.
 *
 * Die Minitabelle rechnet mit der classlib, die Haupttabelle mit lmo-calctable.php: beide muessen
 * dieselbe Tabelle zeigen. Eigene Testinstanz, weil der Mini-Spielplan Cache-Dateien schreibt.
 */
final class MiniAddonTest extends TestCase
{
    use AssertsSnapshots;

    private static Instance $app;
    private static Runner $runner;

    public static function setUpBeforeClass(): void
    {
        [self::$app, self::$runner] = Fixture::freshInstance();
    }

    private static function page(string $script, string $query): string
    {
        return self::$runner->http(['script' => 'addon/mini/' . $script, 'query' => $query])['body'];
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
    public function testMiniTableShowsTheSameTableAsTheMainTable(string $leagueFile): void
    {
        $content = (string)file_get_contents(self::$app->ligenDir() . '/' . $leagueFile);
        preg_match('/^\[Teams\]\R(.*?)(?=^\[)/ms', $content, $section);
        preg_match_all('/^(\d+)=(.*?)\s*$/m', $section[1], $names, PREG_SET_ORDER);
        $teamName = array_column($names, 2, 1);
        $minus = (bool)preg_match('/^MinusPoints=2/m', $content);
        preg_match('/^Rounds=(\d+)/m', $content, $rounds);

        // Haupttabelle nach dem letzten Spieltag
        $core = json_decode(self::$runner->calc($leagueFile), true)['tabtype=0 newtabtype=0 action=table endtab=' . $rounds[1]];
        $expected = [];
        foreach ($core['tab0'] as $key) {
            $nr = (int)substr($key, -7);
            $diff = $core['dtore'][$nr];
            $expected[] = [
                html_entity_decode($teamName[$nr]),
                $minus ? $core['punkte'][$nr] . ':' . $core['negativ'][$nr] : (string)$core['punkte'][$nr],
                ($diff > 0 ? '+' : '') . $diff,
            ];
        }

        // Minitabelle mit allen Plaetzen
        $html = self::page('lmo-minitab.php', 'mini_liga=' . $leagueFile . '&mini_template=standard&mini_ueber=99&mini_unter=99');
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        preg_match_all(
            '~<tr style="[^"]*">\s*<td align="center"><strong>(\d+)</strong></td>.*?<acronym title="([^"]*)">.*?</acronym></td>\s*'
            . '<td align="center">([^<]*)</td>\s*<td align="center">([^<]*)</td>~s',
            $html,
            $rows,
            PREG_SET_ORDER
        );
        $actual = array_map(static fn (array $r): array => [html_entity_decode($r[2]), $r[4], $r[3]], $rows);

        self::assertCount(count($expected), $actual, 'Anzahl Tabellenzeilen');
        self::assertSame(range(1, count($actual)), array_map(static fn (array $r): int => (int)$r[1], $rows), 'Platznummern');
        self::assertSame(array_column($expected, 1), array_column($actual, 1), 'Punkte je Platz');
        self::assertSame(array_column($expected, 2), array_column($actual, 2), 'Tordifferenz je Platz');
        self::assertSame(array_column($expected, 0), array_column($actual, 0), 'Mannschaft je Platz');
    }

    /** @return int[] Tendenz je Tabellenzeile (title des Tendenz-Bildes) fuer golden/basic mit dem angegebenen aktuellen Spieltag */
    private static function tendencies(int $actual): array
    {
        $source = str_replace("\r\n", "\n", (string)file_get_contents(self::$app->ligenDir() . '/golden/basic.l98'));
        $content = preg_replace('/^Actual=.*$/m', 'Actual=' . $actual, $source, 1, $count);
        self::assertSame(1, $count);
        file_put_contents(self::$app->ligenDir() . '/tendenz' . $actual . '.l98', $content);

        $html = self::page('lmo-minitab.php', 'mini_liga=tendenz' . $actual . '.l98&mini_template=standard&mini_ueber=99&mini_unter=99');
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        preg_match_all('~<img src="[^"]*lmo-tab\d\.gif" border="0" title="(-?\d+)">~', $html, $titles);
        self::assertCount(8, $titles[1], 'Tendenz fuer alle 8 Mannschaften');
        return array_map('intval', $titles[1]);
    }

    public function testNoTendencyBeforeTheSecondMatchday(): void
    {
        // am 1. Spieltag gibt es keinen Vorspieltag
        self::assertSame(array_fill(0, 8, 0), self::tendencies(1));
    }

    public function testTendencyComparesWithThePreviousMatchday(): void
    {
        $tendencies = self::tendencies(3);

        self::assertNotSame(array_fill(0, 8, 0), $tendencies, 'Plaetze haben sich seit Spieltag 2 veraendert');
        // jeder gewonnene Platz ist fuer eine andere Mannschaft ein verlorener
        self::assertSame(0, array_sum($tendencies));
    }

    /** @return string[] bisherige Begegnungen (Listeneintraege) im Mini-Spielplan */
    private static function previousMatches(string $query): array
    {
        $html = self::page('lmo-mininext.php', $query);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        preg_match_all('~<li class="[^"]*">(.*?)</li>~s', $html, $items);
        return array_map(static fn (string $item): string => trim((string)preg_replace('/\s+/', ' ', strip_tags($item))), $items[1]);
    }

    public function testPreviousMatchesIncludeLeaguesOfTheArchiveFolder(): void
    {
        // zwei fruehere Saisons mit denselben Mannschaften im Archivordner
        $ligen = self::$app->ligenDir();
        mkdir($ligen . '/fruehere');
        copy($ligen . '/golden/basic.l98', $ligen . '/fruehere/saison1.l98');
        copy($ligen . '/golden/basic.l98', $ligen . '/fruehere/saison2.l98');
        // erste gespielte Partie der Liga: diese beiden Mannschaften haben sicher gegeneinander gespielt
        $content = str_replace("\r\n", "\n", (string)file_get_contents($ligen . '/golden/basic.l98'));
        self::assertSame(1, preg_match('/^TA1=(\d+)\nTB1=(\d+)\nGA1=\d+\nGB1=\d+$/m', $content, $pair), 'gespielte Partie in der Testliga');
        $query = 'file=golden/basic.l98&a=' . $pair[1] . '&b=' . $pair[2];

        $own = self::previousMatches($query . '&mini_withArchiv=0');
        $all = self::previousMatches($query . '&folder=fruehere');

        self::assertNotEmpty($own, 'Begegnungen der beiden Mannschaften in der eigenen Liga');
        self::assertCount(3 * count($own), $all, 'eigene Liga und zwei Ligen des Archivs');
    }

    public function testTeamsThatNeverMeetShowNoPreviousMatches(): void
    {
        // Mannschaft gegen sich selbst: diese Paarung gibt es in keinem Spielplan
        self::assertSame([], self::previousMatches('file=golden/basic.l98&a=1&b=1&mini_withArchiv=0'));
    }

    public function testMissingArchiveFolderShowsMessage(): void
    {
        $html = self::page('lmo-mininext.php', 'file=golden/basic.l98&a=1&b=2&folder=gibtesnicht');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertStringContainsString('class="error"', $html);
    }

    public static function pages(): array
    {
        return [
            'Minitabelle, Standardausschnitt' => ['lmo-minitab.php', 'mini_liga=golden/basic.l98&mini_template=standard'],
            'Minitabelle, Ausschnitt um Platz 5' => ['lmo-minitab.php', 'mini_liga=golden/basic.l98&mini_template=standard&mini_platz=5&mini_ueber=1&mini_unter=1'],
            'Minitabelle, Minuspunkte' => ['lmo-minitab.php', 'mini_liga=golden/minus2_direct.l98&mini_template=standard'],
            'Minitabelle ohne Liga' => ['lmo-minitab.php', ''],
            'Mini-Spielplan, naechstes Spiel' => ['lmo-mininext.php', 'file=1l_2024-25.l98&a=1'],
            'Mini-Spielplan, Paarung' => ['lmo-mininext.php', 'file=1l_2024-25.l98&a=1&b=2'],
            'Mini-Spielplan ohne Mannschaft' => ['lmo-mininext.php', 'file=1l_2024-25.l98'],
            'Mini-Spielplan ohne Liga' => ['lmo-mininext.php', ''],
        ];
    }

    /** @dataProvider pages */
    public function testPageOutputIsUnchanged(string $script, string $query): void
    {
        $html = self::page($script, $query);
        // Countdown bis zum Spiel: die Testuhr laeuft weiter, je nach Dauer des Aufrufs ist schon eine Minute weniger uebrig
        $html = (string)preg_replace('/(Minuten: )\d+/', '$1{MIN}', $html);

        $this->assertMatchesSnapshot('mini', $script . '?' . $query, Normalizer::html($html, self::$app->path(), $query));
    }
}
