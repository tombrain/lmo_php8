<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Instance;
use Lmo\Tests\GoldenMaster\Support\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Addon "ticker": Lauftext auf der Ligaseite, wenn in der Liga "ticker=1" gesetzt ist.
 * Zeigt die Ergebnisse des aktuellen Spieltags (tickerart=1) oder die News der Liga (tickerart=2).
 * Liga: golden/wertung.l98 mit zugesprochenen Siegen und beidseitigem Ergebnis.
 */
final class TickerAddonTest extends TestCase
{
    private const LIGA = 'golden/wertung.l98';

    private static Instance $app;
    private static Runner $runner;

    public static function setUpBeforeClass(): void
    {
        [self::$app, self::$runner] = Fixture::freshInstance();
    }

    /** Setzt Optionen der Liga (z.B. ticker, Actual) und liefert den Lauftext der Ligaseite. */
    private static function ticker(array $options, ?string $news = null): ?string
    {
        $path = self::$app->ligenDir() . '/' . self::LIGA;
        $content = str_replace("\r\n", "\n", (string)file_get_contents($path));
        foreach ($options as $key => $value) {
            $content = preg_replace('/^' . $key . '=.*$/m', $key . '=' . $value, $content, 1, $count);
            self::assertSame(1, $count, "Option $key fehlt in der Testliga");
        }
        if ($news !== null) {
            $content = preg_replace('/^\[News\]\n.*?(?=^\[)/ms', "[News]\n" . $news . "\n", $content, 1, $count);
            self::assertSame(1, $count, 'Abschnitt [News] fehlt in der Testliga');
        }
        file_put_contents($path, $content);

        $html = self::$runner->request('file=' . self::LIGA . '&action=results&st=' . ($options['Actual'] ?? 1));
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        if (!preg_match('~<div data-duration[^>]*class="marquee".*?</div>~s', $html, $marquee)) {
            return null;
        }
        return trim((string)preg_replace('/\s+/', ' ', strip_tags($marquee[0])));
    }

    public function testNoTickerWhenLeagueHasItSwitchedOff(): void
    {
        self::assertNull(self::ticker(['ticker' => 0, 'Actual' => 1]));
    }

    public function testAwardedAwayWinIsMentionedOnlyForThatMatch(): void
    {
        self::assertSame(
            '+++ wertung (1.Spieltag): Mannschaft 1 - Mannschaft 7 3:3 +++ Mannschaft 3 - Mannschaft 4 0:0 '
            . '-Entscheid durch Sportgericht:Mannschaft 4 bekam den Sieg zugesprochen +++ Mannschaft 5 - Mannschaft 2 3:0 '
            . '+++ Mannschaft 8 - Mannschaft 6 0:1 +++',
            self::ticker(['ticker' => 1, 'Actual' => 1])
        );
    }

    public function testAwardedHomeWinAndUnplayedMatch(): void
    {
        self::assertSame(
            '+++ wertung (4.Spieltag): Mannschaft 2 - Mannschaft 6 0:0 -Entscheid durch Sportgericht:Mannschaft 2 bekam den '
            . 'Sieg zugesprochen +++ Mannschaft 4 - Mannschaft 1 2:2 +++ Mannschaft 5 - Mannschaft 3 _:_ +++ Mannschaft 7 - '
            . 'Mannschaft 8 2:1 +++',
            self::ticker(['ticker' => 1, 'Actual' => 4])
        );
    }

    public function testResultForBothSides(): void
    {
        self::assertSame(
            '+++ wertung (6.Spieltag): Mannschaft 1 - Mannschaft 2 0:3 +++ Mannschaft 3 - Mannschaft 8 3:0 +++ Mannschaft 5 - '
            . 'Mannschaft 6 0:0 -Entscheid durch Sportgericht:Mannschaft 6 bekam den Sieg zugesprochen +++ Mannschaft 7 - '
            . 'Mannschaft 4 3:3 -Entscheid durch Sportgericht:Das Ergebnis zählt für beide Mannschaften gleichermaßen aus Sicht '
            . 'der Heimmannschaft. +++',
            self::ticker(['ticker' => 1, 'Actual' => 6])
        );
    }

    public function testNewsTicker(): void
    {
        // tickerart=2 in der Addon-Konfiguration: News der Liga statt Ergebnisse
        $config = self::$app->path() . '/config/ticker/cfg.txt';
        $original = (string)file_get_contents($config);
        file_put_contents($config, preg_replace('/^tickerart=.*$/m', 'tickerart=2', $original));
        try {
            $text = self::ticker(['ticker' => 1, 'Actual' => 1], "NC=2\nN0=Erste Meldung\nN1=Zweite Meldung");
        } finally {
            file_put_contents($config, $original);
        }

        self::assertNotNull($text, 'Lauftext vorhanden');
        self::assertStringContainsString('Erste Meldung +++ Zweite Meldung', $text);
        self::assertStringNotContainsString('Mannschaft 1 - Mannschaft 7', $text, 'keine Ergebnisse im News-Ticker');
    }

    /** Lauftext des direkt aufgerufenen Tickers fuer mehrere Ligen (kommagetrennt). */
    private static function tickerFor(string $leagues): string
    {
        $html = self::$runner->http(['script' => 'addon/ticker/ticker.php', 'query' => 'tickerligen=' . $leagues])['body'];
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertSame(1, preg_match('~<div data-duration[^>]*class="marquee".*?</div>~s', $html, $marquee), 'Lauftext vorhanden');
        return trim((string)preg_replace('/\s+/', ' ', strip_tags($marquee[0])));
    }

    public function testSeveralLeaguesEachShowTheirOwnResults(): void
    {
        self::ticker(['ticker' => 1, 'Actual' => 1]);  // wertung steht am 1. Spieltag
        $own = 'Mannschaft 1 - Mannschaft 7 3:3';

        $one = self::tickerFor(self::LIGA);
        $two = self::tickerFor(self::LIGA . ',golden/kegel.l98');

        self::assertSame(1, substr_count($one, $own));
        self::assertStringContainsString('wertung (1.Spieltag):', $two);
        self::assertStringContainsString('kegel (', $two);
        // die Spiele der ersten Liga stehen nicht noch einmal bei der zweiten
        self::assertSame(1, substr_count($two, $own));
        self::assertStringStartsWith(rtrim($one, ' +'), $two, 'erste Liga unveraendert, zweite angehaengt');
        self::assertGreaterThan(strlen($one) + 50, strlen($two), 'zweite Liga bringt eigene Spiele mit');
    }

    public function testUnknownLeagueDoesNotRemoveTheOthers(): void
    {
        self::ticker(['ticker' => 1, 'Actual' => 1]);

        $text = self::tickerFor(self::LIGA . ',gibtesnicht.l98');

        self::assertStringContainsString('Mannschaft 1 - Mannschaft 7 3:3', $text);
        self::assertStringContainsString('keine passenden Ligen gefunden', $text);
    }
}
