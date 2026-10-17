<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\KreuztabelleController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Die Kreuztabelle: drei Mannschaften (B heisst "B'Co & X"), am 1. Spieltag A - B 2:1, am 2.
 * Spieltag B - C noch offen mit Anstoss, am 3. Spieltag C - A am gruenen Tisch fuer C mit Notiz.
 * Wappen als "[W:Name|Attribute]".
 */
class KreuztabelleControllerTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     */
    private static function kontext(string $action = 'cross', array $parameter = [], array $optionen = []): Kontext
    {
        $anstoss = mktime(15, 30, 0, 8, 23, 2025);
        $zeilen = array_merge(['[Options]', 'Teams=3', 'Rounds=3', 'Matches=1'], $optionen, [
            '[Teams]', '1=A', "2=B'Co & X", '3=C',
            '[Teamk]', '1=A', "2=B'C", '3=C',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=1',
            '[Round2]', 'TA1=2', 'TB1=3', 'AT1=' . $anstoss,
            '[Round3]', 'TA1=3', 'TB1=1', 'GA1=-2', 'GB1=0', 'NT1=Regen & Wind',
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
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '[W:' . $name . '|' . $attribute . ']')
        );
    }

    /** @param array<string, string> $parameter */
    private static function seite(array $parameter = [], array $optionen = []): string
    {
        return (new KreuztabelleController())->zeige(self::kontext('cross', $parameter, $optionen))->html;
    }

    public function testZustaendigNurFuerDieKreuztabelleEinerLiga(): void
    {
        $c = new KreuztabelleController();

        self::assertTrue($c->zustaendig(self::kontext()));
        self::assertFalse($c->zustaendig(self::kontext('table')));
        self::assertFalse($c->zustaendig(self::kontext('cross', [], ['Type=1'])), 'Pokal');
        self::assertSame('', $c->zeige(self::kontext('cross', [], ['Kreuz=0']))->html, 'in der Liga abgeschaltet');
    }

    public function testKopfUndRandMitEscaptenNamen(): void
    {
        $html = self::seite();

        self::assertStringContainsString('<a href="/lmo.php?action=cross&amp;file=x.l98&amp;croteam=2" title="B&#039;Co &amp; X: T26">B&#039;C</a>', $html);
        self::assertStringContainsString("<acronym title='B&#039;Co &amp; X'>[W:B'Co & X| alt='B&#039;C']</acronym>", $html, 'Wappen mit Name und Kurzname');
        self::assertStringContainsString("<acronym title=''>[W:| alt='']</acronym>", $html, 'leeres Wappen in der rechten Randspalte wie bisher');
        self::assertSame(3, substr_count($html, "<td class='lmoLeer'>&nbsp;</td>"), 'die Diagonale');
    }

    public function testSpieleInDenZellen(): void
    {
        $html = self::seite();

        self::assertStringContainsString('<a href="/lmo.php?action=results&amp;file=x.l98&amp;st=1" title="A - B&#039;Co &amp; X &#10;(1. T2)"><strong>2:1</strong></a>', $html, 'gespielt: Ergebnis fett');
        self::assertStringContainsString('title="B&#039;Co &amp; X - C &#10;(2. T2, Sat. 23.08.2025 15:30)">23.08.</a>', $html, 'offen mit Anstoss: der Tag');
        self::assertStringContainsString('title="C - A &#10;(3. T2&#10;&#10;T219:&#10;C T211&#10;&#10;T22:&#10;Regen &amp; Wind)"><strong>0:0</strong> !</a>', $html, 'gruener Tisch und Notiz im Tooltip');
    }

    public function testHervorgehobeneMannschaft(): void
    {
        $html = self::seite(['croteam' => '2']);

        self::assertStringContainsString("<th align='right'>B&#039;Co &amp; X</th>", $html, 'ohne Link');
        self::assertStringContainsString('title="T26">A</a></th>', $html, 'die anderen verlinkt');
        self::assertSame(5, substr_count($html, "class='lmoBackMarkierung'"), 'Zeile und Spalte, die Kreuzung einmal');
    }
}
