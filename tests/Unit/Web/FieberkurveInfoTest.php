<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\FieberkurveController;
use Lmo\Web\Controller\InfoController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Fieberkurve und Info-Seite. Drei Mannschaften (C heisst "O'Neill"), drei Spieltage: am 1.
 * gewinnt A gegen B, am 2. B gegen C, der 3. ist noch offen.
 */
class FieberkurveInfoTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     */
    private static function kontext(string $action, array $parameter = [], array $optionen = []): Kontext
    {
        $zeilen = array_merge(['[Options]', 'Teams=3', 'Rounds=3', 'Matches=1', 'Graph=1'], $optionen, [
            '[Teams]', '1=A', '2=B', "3=O'Neill",
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=1', 'GB1=0',
            '[Round2]', 'TA1=2', 'TB1=3', 'GA1=2', 'GB1=0',
            '[Round3]', 'TA1=3', 'TB1=1',
        ]);
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
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', 'https://lmo.example'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '')
        );
    }

    private static function kurve(array $parameter = [], array $optionen = []): string
    {
        return (new FieberkurveController(static fn (): string => '1,2,3,1'))->zeige(self::kontext('graph', $parameter, $optionen))->html;
    }

    public function testPlaetzeJeSpieltag(): void
    {
        $platz = FieberkurveController::plaetze(Liga::ausEintraegen(LigaDatei::zerlege([
            '[Options]', 'Teams=3', 'Rounds=3', 'Matches=1', '[Teams]', '1=A', '2=B', '3=C',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=1', 'GB1=0', '[Round2]', 'TA1=2', 'TB1=3', 'GA1=2', 'GB1=0', '[Round3]', 'TA1=3', 'TB1=1',
        ])));

        // nach dem 2. Spieltag: A und B je 3 Punkte und +1, B mit mehr Toren (2:1 gegen 1:0) vorn
        self::assertSame([1, 2, 0, ''], $platz[1], 'am 3. Spieltag hat niemand gespielt');
        self::assertSame([3, 1, 0, ''], $platz[2]);
        self::assertSame([2, 3, 0, ''], $platz[3]);
    }

    public function testKurvenImSkript(): void
    {
        $html = self::kurve();

        self::assertStringContainsString('<script src="https://lmo.example/js/chart.js"></script>', $html);
        self::assertStringContainsString('labels: [1,2,3]', $html);
        self::assertStringContainsString("label: 'O´Neill'", $html, 'Apostroph wie bisher ersetzt');
        self::assertStringContainsString('data: [1,2,],', $html, 'Platz 0 faellt weg, die Reihe endet mit Komma');
        self::assertStringContainsString("backgroundColor: 'rgba(1,2,3,1)'", $html);
        self::assertStringContainsString('max: 3,', $html);
        self::assertSame(2, substr_count($html, 'hidden: false'), 'ohne Auswahl die ersten zwei');
    }

    public function testGewaehlteKurven(): void
    {
        $html = self::kurve(['stat1' => '3']);

        self::assertSame(1, substr_count($html, 'hidden: false'));
        self::assertMatchesRegularExpression("/label: 'O´Neill',(?:(?!label:).)*hidden: false/s", $html);

        self::assertSame(2, substr_count(self::kurve([], ['kurve1=2', 'kurve2=3']), 'hidden: false'), 'die der Liga');
        self::assertSame(1, substr_count(self::kurve(['stat1' => '0', 'stat2' => '2']), 'hidden: false'), 'stat2 ohne stat1 rutscht nach vorn');
    }

    public function testZustaendigkeit(): void
    {
        $c = new FieberkurveController();

        self::assertTrue($c->zustaendig(self::kontext('graph')));
        self::assertFalse($c->zustaendig(self::kontext('graph', [], ['Type=1'])), 'Pokal');
        self::assertSame('', $c->zeige(self::kontext('graph', [], ['Graph=0']))->html, 'abgeschaltet');
        self::assertTrue((new InfoController('1.2.3'))->zustaendig(self::kontext('info', [], ['Type=1'])), 'Info auch im Pokal');
    }

    public function testInfo(): void
    {
        $html = (new InfoController('1.2.3'))->zeige(self::kontext('info'))->html;

        self::assertStringContainsString('<h1>T54 - Vers. 1.2.3</h1>', $html);
        self::assertStringContainsString('<p>T49</p>', $html);
    }
}
