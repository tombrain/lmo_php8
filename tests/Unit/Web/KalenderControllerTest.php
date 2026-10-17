<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Der Kalender (in der Liga eingeschaltet, DatC=1): eine Liga mit zwei Spieltagen im Maerz 2025 (Sa 8. und So 16.), der zweite
 * mit "B & Co"; ein Pokal mit zwei Runden (Halbfinale mit Hin- und Rueckspiel, Finale).
 * "Jetzt" ist Montag, der 10. Maerz 2025.
 */
class KalenderControllerTest extends TestCase
{
    private static function jetzt(): int
    {
        return mktime(12, 0, 0, 3, 10, 2025);
    }

    /** @param array<string, string> $parameter */
    private static function kontext(array $zeilen, array $parameter = [], string $action = 'cal'): Kontext
    {
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter),
            $action,
            new GeladeneLiga('x.l98', Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), ''),
            new Konfiguration([]),
            $text,
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            new \Lmo\Addon\Addons(),
            ['date' => [14 => 'Januar', 16 => 'Maerz', 30 => 'Mai']]
        );
    }

    /** @param list<string> $optionen */
    private static function liga(array $optionen = []): array
    {
        return array_merge(['[Options]', 'Teams=4', 'Rounds=2', 'Matches=2', 'Actual=1', 'DatC=1'], $optionen, [
            '[Teams]', '1=A', '2=B & Co', '3=C', '4=D',
            '[Round1]', 'TA1=1', 'TB1=2', 'AT1=' . mktime(15, 30, 0, 3, 8, 2025), 'TA2=3', 'TB2=4', 'AT2=' . mktime(15, 30, 0, 3, 8, 2025),
            '[Round2]', 'TA1=2', 'TB1=3', 'AT1=' . mktime(15, 30, 0, 3, 16, 2025),
        ]);
    }

    /** @param array<string, string> $parameter */
    private static function seite(array $zeilen, array $parameter = []): string
    {
        return (new KalenderController(self::jetzt()))->zeige(self::kontext($zeilen, $parameter))->html;
    }

    public function testZustaendigFuerLigaUndPokal(): void
    {
        $c = new KalenderController(self::jetzt());

        self::assertTrue($c->zustaendig(self::kontext(self::liga())));
        self::assertTrue($c->zustaendig(self::kontext(self::liga(['Type=1']))), 'auch im Pokal');
        self::assertFalse($c->zustaendig(self::kontext(self::liga(), [], 'table')));
        self::assertSame('', $c->zeige(self::kontext(self::liga(['DatC=0'])))->html, 'in der Liga abgeschaltet');
    }

    public function testMonatDesGewaehltenSpieltags(): void
    {
        $html = self::seite(self::liga());

        self::assertStringContainsString('>Maerz 2025</a>', $html, 'Monatsname aus den Sprachtexten');
        self::assertStringContainsString('cal=022025" title="T142"', $html, 'zurueck');
        self::assertStringContainsString('cal=042025" title="T144"', $html, 'weiter');
        self::assertStringContainsString('cal=032025" title="T143"', $html, 'heute');
        self::assertStringContainsString('cal=2025" title="T173"', $html, 'das Jahr');
        // Maerz 2025 beginnt am Samstag: fuenf leere Tage davor
        self::assertStringContainsString(str_repeat('<td class="lmoLeer">&nbsp;</td>', 5) . "<td align='left'", $html);
        self::assertStringContainsString('title="1. T2: &#10;A - B &amp; Co, &#10;C - D">1. T145</a>', $html, 'beide Spiele am 8.');
        self::assertStringContainsString('class="lmoFrontMarkierung">8<br>', $html, 'Wochenende');
        self::assertStringContainsString('class="lmoBackMarkierung">10</td>', $html, 'heute');
        self::assertStringContainsString('title="2. T2: &#10;B &amp; Co - C">2. T145</a>', $html);
    }

    public function testJahr(): void
    {
        $html = self::seite(self::liga(), ['cal' => '2025']);

        self::assertStringContainsString('T156 2025', $html);
        self::assertStringContainsString('cal=2024" title="T157"', $html);
        self::assertStringContainsString('cal=032025" title="T172">T162</a>', $html, 'Link auf den Maerz');
        self::assertSame(2, substr_count($html, 'T145</a>'), 'beide Spieltage im Maerz');
    }

    public function testOhneAnstossDerZeitraumDesSpieltags(): void
    {
        $zeilen = ['[Options]', 'Teams=2', 'Rounds=1', 'Matches=1', 'DatC=1', '[Teams]', '1=A', '2=B',
            '[Round1]', 'D1=14.03.2025', 'D2=16.03.2025', 'TA1=1', 'TB1=2'];

        $html = self::seite($zeilen, ['cal' => '032025']);

        self::assertSame(3, substr_count($html, 'title="1. T2 &#10;(T155)"'), 'Freitag bis Sonntag');
    }

    public function testPokalrunden(): void
    {
        $zeilen = ['[Options]', 'Type=1', 'Teams=4', 'Rounds=2', 'Actual=1', 'DatC=1',
            '[Teams]', '1=A', '2=B', '3=C', '4=D',
            '[Round1]', 'MO=2', 'TA1=1', 'TB1=2', 'TA2=3', 'TB2=4',
            'AT11=' . mktime(15, 0, 0, 3, 8, 2025), 'AT12=' . mktime(15, 0, 0, 3, 15, 2025),
            '[Round2]', 'TA1=1', 'TB1=3', 'AT11=' . mktime(15, 0, 0, 3, 22, 2025)];

        $html = self::seite($zeilen);

        self::assertStringContainsString('title="T373: &#10;A - B &#10;(1. T375)"', $html, 'Halbfinale, Hinspiel');
        self::assertStringContainsString('title="T373: &#10;A - B &#10;(2. T375)"', $html, 'Halbfinale, Rueckspiel');
        self::assertStringContainsString('title="T374: &#10;A - C"', $html, 'Finale');
    }

    public function testUebersetzteNamen(): void
    {
        $text = [14 => 'Januar', 30 => 'Mai'];

        self::assertSame('Januar 2025', KalenderController::uebersetzt('January 2025', $text));
        self::assertSame('Mai 2025', KalenderController::uebersetzt('May 2025', $text), 'May wie bisher mit Text 30');
        self::assertSame('June 2025', KalenderController::uebersetzt('June 2025', $text), 'ohne Text bleibt der englische');
    }
}
