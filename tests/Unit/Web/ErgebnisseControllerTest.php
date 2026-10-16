<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\ErgebnisseController;
use Lmo\Web\Darstellung;
use Lmo\Web\Statistikdatei;
use PHPUnit\Framework\TestCase;

/**
 * Ergebnis- und Tabellenseite: was der Controller aus der Anfrage liest und welche Teile die
 * Seite bekommt. Vier Mannschaften, drei Spieltage, aktueller Spieltag 2.
 */
class ErgebnisseControllerTest extends TestCase
{
    /**
     * @param array<string, int|bool|string> $konfiguration Abweichungen von der Vorgabe
     */
    private static function controller(array $konfiguration = [], ?Statistikdatei $statistik = null): ErgebnisseController
    {
        $zeilen = [
            '[Options]', 'Teams=4', 'Rounds=3', 'Matches=2', 'Actual=2',
            '[Teams]', '1=A', '2=B', '3=C', '4=D',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=1', 'GB1=0', 'TA2=3', 'TB2=4', 'GA2=0', 'GB2=0',
            '[Round2]', 'TA1=2', 'TB1=3', 'GA1=5', 'GB1=0', 'TA2=4', 'TB2=1', 'GA2=2', 'GB2=0',
            '[Round3]', 'TA1=1', 'TB1=3', 'TA2=2', 'TB2=4',
        ];
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }

        return new ErgebnisseController(
            Liga::ausEintraegen(LigaDatei::zerlege($zeilen)),
            $text,
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            dirname(__DIR__, 3) . '/lmo/vorlagen',
            'IMG',
            $konfiguration + ['tabonres' => 0, 'tabpkt' => 0, 'einzutore' => false, 'einzutoretab' => false, 'einspielfrei' => false, 'datumsformat' => 'd.m.Y'],
            $statistik
        );
    }

    /** @param array<string, string> $parameter */
    private static function seite(string $action, array $parameter = [], array $konfiguration = [], ?Statistikdatei $statistik = null): string
    {
        return self::controller($konfiguration, $statistik)->zeige(new Anfrage($parameter), $action, '/lmo.php', 'x.l98');
    }

    public function testErgebnisseOhneTabelle(): void
    {
        $html = self::seite('results');

        self::assertStringContainsString('2. T2', $html, 'ohne st der aktuelle Spieltag');
        self::assertStringNotContainsString('lmoTabelleMeister', $html);
        self::assertStringContainsString('lmoSubmenu', $html);
    }

    public function testTabelleOhneErgebnisse(): void
    {
        $html = self::seite('table');

        self::assertStringContainsString('lmoTabelleMeister', $html);
        self::assertStringNotContainsString('2. T2', $html);
    }

    public function testMitTabonresBeides(): void
    {
        $html = self::seite('table', [], ['tabonres' => 1]);

        self::assertStringContainsString('lmoTabelleMeister', $html);
        self::assertStringContainsString('2. T2', $html);
    }

    public function testPfeileZumSpieltagDavorUndDanach(): void
    {
        $html = self::seite('results', ['st' => '2', 'tabtype' => '1']);

        self::assertStringContainsString('<a href="/lmo.php?action=results&amp;tabtype=1&amp;file=x.l98&amp;st=1" title="T6">T5 T6</a>', $html);
        self::assertStringContainsString('<a href="/lmo.php?action=results&amp;tabtype=1&amp;file=x.l98&amp;st=3" title="T8">T8 T7</a>', $html);

        self::assertStringNotContainsString('title="T6"', self::seite('results', ['st' => '1']), 'am ersten Spieltag kein Zurueck');
        self::assertStringNotContainsString('title="T8"', self::seite('results', ['st' => '3']), 'am letzten kein Vor');
    }

    public function testGewaehlterSpieltagBestimmtDenTabellenstand(): void
    {
        // nach dem 1. Spieltag fuehrt A (1:0), danach D
        $nachEins = self::seite('table', ['st' => '1']);
        self::assertStringContainsString('st=1&amp;tabtype=', $nachEins);
        self::assertStringContainsString('endtab=1', $nachEins);

        $bisEndtab = self::seite('table', ['endtab' => '1']);
        self::assertStringContainsString('endtab=1&amp;st=2', $bisEndtab, 'endtab ohne st: Spieltag bleibt der aktuelle');

        $alle = self::seite('table');
        self::assertStringContainsString('endtab=3&amp;st=2', $alle, 'ohne beides: alle Spieltage');
    }

    public function testTorstatistik(): void
    {
        $statistik = new Statistikdatei(['gzutore' => 8, 'gdstore' => 2, 'zutore' => [2 => 7], 'dstore' => [2 => 3.5]]);
        $html = self::seite('table', [], ['tabonres' => 1, 'einzutore' => true, 'einzutoretab' => true], $statistik);

        self::assertStringContainsString('T38: 7&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; T38T4001: 3.5', $html, 'Spieltag');
        self::assertStringContainsString('T4000T38: 8&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; T38&nbsp;T4001: 2', $html, 'Saison');

        $ohneDatei = self::seite('results', [], ['einzutore' => true]);
        self::assertStringContainsString('class="lmoFooter"', $ohneDatei, 'leere Zeile ohne Statistikdatei');
    }

    public function testAnfrage(): void
    {
        $anfrage = new Anfrage(['st' => '5', 'leer' => '', 'null' => '0', 'liste' => ['x']]);

        self::assertTrue($anfrage->hat('st'));
        self::assertFalse($anfrage->hat('leer'));
        self::assertFalse($anfrage->hat('null'));
        self::assertFalse($anfrage->hat('fehlt'));
        self::assertSame(5, $anfrage->zahl('st'));
        self::assertSame(7, $anfrage->zahl('fehlt', 7));
        self::assertSame(7, $anfrage->zahl('leer', 7));
        self::assertSame(7, $anfrage->zahl('liste', 7));
        self::assertSame('5', $anfrage->text('st'));
        self::assertSame('', $anfrage->text('liste'));
    }

    public function testStatistikdateiLesen(): void
    {
        $pfad = tempnam(sys_get_temp_dir(), 'lmo');
        file_put_contents($pfad, "<?php\n\$gzutore=12;\n\$gdstore=1.5;\n\$zutore[1]=4;\n\$dstore[1]=2;\n");
        try {
            $statistik = Statistikdatei::lies($pfad);
        } finally {
            unlink($pfad);
        }

        self::assertNotNull($statistik);
        self::assertSame(12, $statistik->toreSaison());
        self::assertSame(1.5, $statistik->toreJeSpielSaison());
        self::assertSame(4, $statistik->toreSpieltag(1));
        self::assertSame('', $statistik->toreSpieltag(2));
        self::assertNull(Statistikdatei::lies($pfad), 'fehlt die Datei');
    }
}
