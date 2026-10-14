<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Darstellung;
use Lmo\Web\Seiten\TabellenSeite;
use PHPUnit\Framework\TestCase;

/**
 * Die Tabellenseite ohne den alten Code des LMO: Rohdaten einer Liga rein, HTML aus
 * vorlagen/tabelle.html.twig raus. Vier Mannschaften A bis D, Wappen als "[W:Name]".
 */
class TabellenSeiteTest extends TestCase
{
    /**
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     * @param list<string> $weitere weitere Zeilen nach den Spieltagen (z. B. [Team1])
     */
    private static function seite(array $optionen = [], array $weitere = []): TabellenSeite
    {
        $zeilen = array_merge(
            ['[Options]', 'Teams=4', 'Rounds=2', 'Matches=2', 'Actual=2', 'CL=0', 'CK=0', 'UC=0', 'AB=1'],
            $optionen,
            ['[Teams]', '1=A', '2=B & Co', '3=C', '4=D'],
            // 1. Spieltag: A schlaegt B, C und D spielen remis; 2. Spieltag: B schlaegt C hoch, D schlaegt A
            ['[Round1]', 'TA1=1', 'TB1=2', 'GA1=1', 'GB1=0', 'TA2=3', 'TB2=4', 'GA2=0', 'GB2=0'],
            ['[Round2]', 'TA1=2', 'TB1=3', 'GA1=5', 'GB1=0', 'TA2=4', 'TB2=1', 'GA2=2', 'GB2=0'],
            $weitere
        );
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }
        $darstellung = new Darstellung(static fn (string $datei, string $name, string $attribute): string => '[W:' . $name . ']');

        return new TabellenSeite(Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), $text, $darstellung, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG');
    }

    /** @return list<string> die Namen der Mannschaften in der Reihenfolge der Zeilen */
    private static function namen(string $html): array
    {
        preg_match_all('/\[W:([^\]]*)\]/', $html, $treffer);

        return array_values(array_unique($treffer[1]));
    }

    public function testReihenfolgeUndFarbenDerGesamttabelle(): void
    {
        $html = self::seite()->zeige(0, 2, '', 'LINK&tabtype=', 'golden/x.l98', 0, 0, '');

        // B 3 Punkte 5:1, D 4 Punkte 2:0, A 3 Punkte 1:2, C 1 Punkt 0:5
        self::assertSame(['D', 'B & Co', 'A', 'C'], self::namen($html));
        self::assertStringContainsString('class="lmoTabelleMeister nobr" align="right">1</td>', $html, 'Platz 1 ist Meister');
        self::assertStringContainsString('lmoTabelleAbsteiger', $html);
    }

    public function testNameWirdEscapedDasWappenNicht(): void
    {
        $html = self::seite()->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');

        self::assertStringContainsString('align="left">B &amp; Co</td>', $html);
        self::assertStringContainsString('[W:B & Co]', $html, 'das Wappen kommt fertig als HTML');
    }

    public function testPfeileZeigenDieVeraenderungZumSpieltagDavor(): void
    {
        $html = self::seite()->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');

        // D war nach dem 1. Spieltag Zweiter (remis), A Erster: D steigt, A faellt
        self::assertStringContainsString('lmo-tab1.', $html);
        self::assertStringContainsString('lmo-tab2.', $html);

        $erster = self::seite()->zeige(0, 1, '', 'L', 'golden/x.l98', 0, 0, '');
        self::assertStringNotContainsString('lmo-tab1.', $erster, 'am ersten Spieltag gibt es keine Pfeile');
        self::assertStringNotContainsString('lmo-tab2.', $erster);
    }

    public function testLieblingsmannschaftFett(): void
    {
        $html = self::seite(['favTeam=3'])->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');

        self::assertStringContainsString('align="left"><strong>C</strong></td>', $html);
        self::assertStringNotContainsString('<strong>A</strong>', $html);
    }

    public function testAuswahlDerTabellenart(): void
    {
        $ohne = self::seite(['tableHeimAusw=0', 'tableHinRueck=0'])->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');
        self::assertStringNotContainsString('tabtype=', $ohne);

        $mit = self::seite(['tableHeimAusw=1'])->zeige(1, 2, '', 'LINK&tabtype=', 'golden/x.l98', 0, 0, '');
        // der Link kommt fertig aus dem alten Code und wird nicht noch einmal escaped
        self::assertStringContainsString('href="LINK&tabtype=0"', $mit, 'Gesamt ist verlinkt');
        self::assertStringContainsString('href="LINK&tabtype=2"', $mit, 'Auswaerts ist verlinkt');
        self::assertStringNotContainsString('href="LINK&tabtype=1"', $mit, 'die gezeigte Art nicht');
    }

    public function testHeimUndAuswaertsbilanzNebenDerTabelle(): void
    {
        $ohne = self::seite()->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');
        $mit = self::seite()->zeige(0, 2, '', 'L', 'golden/x.l98', 2, 0, '');

        self::assertGreaterThan(substr_count($ohne, '<td'), substr_count($mit, '<td'));
        self::assertStringContainsString('T41', $mit);
        self::assertStringContainsString('T42', $mit);
    }

    public function testStrafeImHinweisMitUmgekehrtemVorzeichen(): void
    {
        $html = self::seite([], ['[Team1]', 'SP=2', 'NOT=Hinweis'])->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');

        self::assertStringContainsString('lmo-st2.svg', $html);
        self::assertStringContainsString('T128:&lt;/strong&gt;-2', $html);
        self::assertStringContainsString('Hinweis', $html);

        $bonus = self::seite([], ['[Team1]', 'SP=-2'])->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, '');
        self::assertStringContainsString('T128:&lt;/strong&gt;+2', $bonus);
    }

    public function testTorstatistikUnterDerTabelle(): void
    {
        $html = self::seite()->zeige(0, 2, '', 'L', 'golden/x.l98', 0, 0, 'Tore: <b>42</b>');

        self::assertStringContainsString('Tore: <b>42</b>', $html, 'fertiges HTML, nicht escaped');
    }

    public function testMitFaktor(): void
    {
        self::assertSame(1.5, Darstellung::mitFaktor(3, 2));
        self::assertSame(0, Darstellung::mitFaktor(0, 2));
        self::assertSame(7, Darstellung::mitFaktor(7, 0));
        self::assertSame('-', Darstellung::mitFaktor('-', 2));
    }
}
