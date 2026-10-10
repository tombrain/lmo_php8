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
        if (preg_match('/^HandS=1/m', $content)) {
            self::markTestSkipped('Handicap-Reihenfolge kennt nur die Haupttabelle');
        }
        preg_match('/^\[Teams\]\R(.*?)(?=^\[)/ms', $content, $section);
        preg_match_all('/^(\d+)=(.*?)\s*$/m', $section[1], $names, PREG_SET_ORDER);
        $teamName = array_column($names, 2, 1);
        $minus = (bool)preg_match('/^MinusPoints=2/m', $content);
        preg_match('/^Rounds=(\d+)/m', $content, $rounds);

        // Haupttabelle nach dem letzten Spieltag
        $core = json_decode(self::$runner->calc($leagueFile), true)['tabtype=0 newtabtype=0 action=table endtab=' . $rounds[1]];
        $expected = [];
        $keys = [];
        foreach ($core['tab0'] as $key) {
            $nr = (int)substr($key, -7);
            $diff = $core['dtore'][$nr];
            $expected[] = [
                html_entity_decode($teamName[$nr]),
                $minus ? $core['punkte'][$nr] . ':' . $core['negativ'][$nr] : (string)$core['punkte'][$nr],
                ($diff > 0 ? '+' : '') . $diff,
            ];
            $keys[] = [$core['punkte'][$nr], $core['negativ'][$nr], $core['etore'][$nr], $core['atore'][$nr]];
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
        // Mannschaft je Platz; bei vollstaendigem Gleichstand (Punkte und Tore) ist die Reihenfolge nicht geregelt
        $position = array_flip(array_column($expected, 0));
        foreach ($actual as $pos => [$name]) {
            self::assertArrayHasKey($name, $position, "unbekannte Mannschaft $name");
            self::assertSame($keys[$pos], $keys[$position[$name]], 'Platz ' . ($pos + 1) . ": $name steht in der Haupttabelle auf Platz " . ($position[$name] + 1));
        }
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

        $this->assertMatchesSnapshot('mini', $script . '?' . $query, Normalizer::html($html, self::$app->path(), $query));
    }
}
