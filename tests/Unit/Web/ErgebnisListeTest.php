<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Darstellung;
use Lmo\Web\Seiten\ErgebnisListe;
use PHPUnit\Framework\TestCase;

/**
 * Die Ergebnisse eines Spieltags ohne den alten Code des LMO. Vier Mannschaften A bis D (B heisst
 * "B & Co"), am 1. Spieltag A - B 2:1 und C - D noch offen; Wappen als "[W:Name]".
 */
class ErgebnisListeTest extends TestCase
{
    /**
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     * @param list<string> $partien weitere Zeilen fuer [Round1]
     */
    private static function liste(array $optionen = [], array $partien = [], int $mannschaften = 4): ErgebnisListe
    {
        $namen = ['[Teams]', '1=A', '2=B & Co', '3=C', '4=D', '5=E'];
        $zeilen = array_merge(
            ['[Options]', 'Teams=' . $mannschaften, 'Rounds=1', 'Matches=2'],
            $optionen,
            array_slice($namen, 0, $mannschaften + 1),
            ['[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=1', 'TA2=3', 'TB2=4'],
            $partien
        );
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }
        $darstellung = new Darstellung(static fn (string $datei, string $name, string $attribute): string => '[W:' . $name . ']');

        return new ErgebnisListe(Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), $text, $darstellung, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG');
    }

    public function testSpieleMitToren(): void
    {
        $html = self::liste()->zeige(1, 'x.l98', 'P&amp;t=', 'd.m.Y', null, false);

        self::assertStringContainsString('1. T2', $html);
        self::assertStringContainsString('<a href="P&amp;t=1" title="T269">A</a>&nbsp;[W:A]', $html);
        self::assertStringContainsString('[W:B & Co]&nbsp;<a href="P&amp;t=2" title="T269">B &amp; Co</a>', $html, 'Name escaped, Wappen fertiges HTML');
        self::assertMatchesRegularExpression('#<td align="right">2</td>\s*<td align="center" width="8">:</td>\s*<td align="left">1</td>#', $html);
        self::assertStringContainsString('<td align="right">_</td>', $html, 'offenes Spiel');
        self::assertStringNotContainsString('lmoFooter', $html);
    }

    public function testOhneSpielplanKeinLink(): void
    {
        $html = self::liste(['Plan=0'])->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false);

        self::assertStringNotContainsString('<a href', $html);
        self::assertStringContainsString('>A&nbsp;[W:A]', $html);
    }

    public function testLieblingsmannschaftFett(): void
    {
        $html = self::liste(['favTeam=2'])->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false);

        self::assertStringContainsString('<strong>B &amp; Co</strong>', $html);
        self::assertStringNotContainsString('<strong>A</strong>', $html);
    }

    public function testBreiteJeNachSpalten(): void
    {
        self::assertSame(10, self::liste()->breite(1));
        self::assertSame(12, self::liste(['Spez=1'])->breite(1));
        self::assertSame(14, self::liste([], ['ET1=3'])->breite(1), 'gegen beide gewertet');
        self::assertStringContainsString('colspan="14"', self::liste([], ['ET1=3'])->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false));
    }

    public function testGegenBeideGewertet(): void
    {
        $html = self::liste([], ['ET1=3'])->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false);

        self::assertStringContainsString('<td align="center" width="2">/</td>', $html);
        self::assertStringContainsString('lmo-st2.svg', $html, 'Hinweis zur Wertung');
        self::assertStringContainsString('T219:&lt;/strong&gt;T212', $html);
    }

    public function testNotizImHinweis(): void
    {
        $html = self::liste([], ['NT1=Regen & Wind'])->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false);

        self::assertStringContainsString('T22:&lt;/strong&gt;Regen &amp; Wind', $html);
        self::assertStringContainsString('IMG/lmo-st2.svg', $html);
    }

    public function testTorstatistikUndSpielfrei(): void
    {
        $html = self::liste([], [], 5)->zeige(1, 'x.l98', 'P', 'd.m.Y', 'Tore: <b>3</b>', true);

        self::assertStringContainsString('&nbsp;Tore: <b>3</b></td>', $html);
        self::assertStringContainsString('T4004: <a href="P5" title="T269">E</a>&nbsp;[W:E]', $html);

        $leer = self::liste()->zeige(1, 'x.l98', 'P', 'd.m.Y', '', true);
        self::assertStringContainsString('class="lmoFooter"', $leer, 'Zeile auch ohne Statistikdatei');
        self::assertStringNotContainsString('T4004', $leer, 'alle spielen');
    }

    public function testZusatzDerAddons(): void
    {
        $html = self::liste()->zeige(1, 'x.l98', 'P', 'd.m.Y', null, false, static fn (Spiel $spiel): string => '<i>' . $spiel->partie . '</i>');

        self::assertStringContainsString('&nbsp;<i>1</i></td>', $html);
        self::assertStringContainsString('&nbsp;<i>2</i></td>', $html);
    }
}
