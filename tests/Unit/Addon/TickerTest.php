<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Ticker\Ticker;
use Lmo\Addon\Ticker\TickerAddon;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Addon "ticker": Lauftext mit Ergebnissen oder News und der Newsticker ueber der Liga.
 */
class TickerTest extends TestCase
{
    private const TEXT = [0 => 'n.V.', 1 => 'i.E.', 22 => 'Notiz', 211 => 'gewinnt', 224 => 'keine Liga'];
    private const TICKER = [0 => 'Ergebnisse', 1 => '.Spieltag', 2 => '-Sportgericht', 3 => 'zaehlt fuer beide'];

    /** @param list<string> $optionen weitere Zeilen fuer [Options] */
    private static function liga(array $optionen = []): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege(array_merge(
            ['[Options]', 'Name=Testliga', 'Teams=4', 'Rounds=1', 'Matches=2', 'Actual=1', 'favTeam=3'],
            $optionen,
            ['[Teams]', '1=Alpha', '2=Beta', '3=Gamma', '4=Delta'],
            ['[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=1', 'NT1=Regen', 'TA2=3', 'TB2=4', 'GA2=-2', 'GB2=0'],
            ['[News]', 'NC=2', 'N0=Erste', 'N1=Zweite']
        )));
    }

    public function testErgebnisse(): void
    {
        $text = (new Ticker(1, true, self::TEXT, self::TICKER))->text([self::liga()]);

        self::assertStringStartsWith('  +++  Testliga (1.Spieltag): ', $text);
        self::assertStringContainsString('Alpha - Beta 2:1  Notiz: Regen +++ ', $text, 'mit Notiz');
        self::assertStringContainsString('Gamma - Delta ', $text);
        self::assertStringContainsString('-Sportgericht:Gamma gewinnt +++ ', $text, 'Sieg zugesprochen');
        self::assertStringNotContainsString('Regen', (new Ticker(1, false, self::TEXT, self::TICKER))->text([self::liga()]), 'ohne Notizen');
    }

    public function testNurDieLieblingsmannschaft(): void
    {
        $text = (new Ticker(3, true, self::TEXT, self::TICKER))->text([self::liga()]);

        self::assertStringContainsString('Gamma - Delta', $text);
        self::assertStringNotContainsString('Alpha', $text);
    }

    public function testNewsUndMehrereLigen(): void
    {
        $text = (new Ticker(2, true, self::TEXT, self::TICKER))->text([self::liga(), null, self::liga(['Name=Zweite Liga'])]);

        self::assertStringContainsString(' +++ Erste +++ Zweite', $text);
        self::assertStringNotContainsString('Alpha', $text, 'keine Ergebnisse');
        self::assertStringContainsString('keine Liga +++ ', $text, 'eine fehlende Liga loescht die anderen nicht');
        self::assertLessThan(strpos($text, 'Zweite Liga'), (int) strpos($text, 'keine Liga'), 'in der Reihenfolge der Ligen');
    }

    public function testHtml(): void
    {
        $ticker = new Ticker(1, true, self::TEXT, self::TICKER);

        $html = $ticker->html('/lmo', 'A - B 1:0', true, '40', '5000');
        self::assertStringContainsString('src="/lmo/js/jquery.marquee.min.js"', $html);
        self::assertStringContainsString('<p>Ergebnisse</p>', $html);
        self::assertStringContainsString('<div data-duration="5000" class="marquee" style="width: 40em;', $html);
        self::assertStringContainsString('>A - B 1:0</div>', $html);

        $ohneTitel = $ticker->html('/lmo', '', false, '"><b>', '5000');
        self::assertStringNotContainsString('<p>', $ohneTitel);
        self::assertStringContainsString('width: &quot;&gt;&lt;b&gt;em', $ohneTitel, 'Einstellungen maskiert');
    }

    /** @param array<string, string> $parameter */
    private static function kontext(Liga $liga, array $parameter = []): Kontext
    {
        return new Kontext(
            new Anfrage($parameter),
            'results',
            new GeladeneLiga('x.l98', $liga, ''),
            new Konfiguration(['ticker' => ['tickerart' => '1', 'tickertitel' => '1', 'notizanzeigen' => '1', 'breite' => '40', 'geschwindigkeit' => '5000']]),
            self::TEXT,
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            weitereTexte: ['ticker' => self::TICKER]
        );
    }

    public function testUeberDerLiga(): void
    {
        $addon = new TickerAddon();

        self::assertSame('ticker', $addon->name());
        self::assertSame([], $addon->rahmenteile(self::kontext(self::liga())), 'in der Liga abgeschaltet');
        $teile = $addon->rahmenteile(self::kontext(self::liga(['ticker=1'])));
        self::assertSame(['Newsticker'], array_keys($teile));
        self::assertStringContainsString('Alpha - Beta 2:1', $teile['Newsticker']);
        self::assertStringContainsString('<p>Ergebnisse</p>', $teile['Newsticker']);

        $news = $addon->rahmenteile(self::kontext(self::liga(['ticker=1']), ['tickerart' => '2', 'tickertitel' => '0']))['Newsticker'];
        self::assertStringContainsString('Erste +++ Zweite', $news, 'Einstellungen aus der Adresse');
        self::assertStringNotContainsString('<p>', $news);
    }
}
