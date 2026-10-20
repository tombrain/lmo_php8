<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\RanglisteController;
use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Rangliste des Tippspiels: Einzel-, Team- und interne Wertung, fuer Angemeldete und Gaeste.
 */
class RanglisteControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-rangliste-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/tipps/auswert/x.aus', "[anna]\nTeam=Rot\nName=Anna <A>\nEmail=a@x\nSG1=2\nTP1=4\nSG2=1\nTP2=0\n\n"
            . "[tester]\nTeam=Blau\nName=Tina\nEmail=\nSG1=2\nTP1=1\nSG2=2\nTP2=5\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/tipps/auswert/*') ?: []);
        rmdir($this->ordner . '/tipps/auswert');
        rmdir($this->ordner . '/tipps');
        rmdir($this->ordner);
    }

    /**
     * @param array<string, string> $parameter
     * @param array<string, string> $tipp
     * @param array<string, int|string> $sitzung
     */
    private function kontext(array $parameter = [], array $tipp = [], array $sitzung = ['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => ''], bool $mitLiga = true): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Name=Testliga', 'Teams=2', 'Rounds=2', 'Matches=1', 'Actual=1', '[Teams]', '1=Alpha', '2=Beta']));

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'wert', 'file' => 'x.l98']),
            'tipp',
            $mitLiga ? new GeladeneLiga('x.l98', $liga, '') : null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => $tipp + [
                'dirtipp' => 'tipps/', 'tippmodus' => '1', 'tipperimteam' => '0', 'anzseite1' => '30', 'showname' => '1', 'shownick' => '1', 'showemail' => '1',
                'showzus' => '0', 'showstsiege' => '1', 'krit1' => '0', 'krit2' => '1', 'krit3' => '2', 'regeln' => '0',
            ]]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung($sitzung)
        );
    }

    private function controller(): RanglisteController
    {
        return new RanglisteController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
    }

    private function seite(Kontext $kontext): string
    {
        self::assertTrue($this->controller()->zustaendig($kontext));

        return $this->controller()->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        self::assertFalse($this->controller()->zustaendig($this->kontext(['todo' => 'tabelle'])));
        self::assertFalse($this->controller()->zustaendig($this->kontext(['viewermode' => '1'])));
        self::assertFalse($this->controller()->zustaendig($this->kontext(mitLiga: false)), 'die Gesamtwertung ohne Liga zeigt noch der alte Weg');
    }

    public function testEinzelwertung(): void
    {
        $html = $this->seite($this->kontext());

        self::assertStringContainsString('<caption>tester</caption>', $html);
        self::assertLessThan(strpos($html, '(anna)') ?: 0, strpos($html, '(tester)') ?: PHP_INT_MAX, 'tester hat 6 Punkte, anna 4');
        self::assertStringContainsString("<a href='mailto:a@x'>Anna &lt;A&gt;</a> (anna)", $html, 'Namen maskiert');
        self::assertStringContainsString('<strong>Tina (tester)</strong>', $html, 'der eigene fett, ohne Email kein Link');
        self::assertStringContainsString('lmoTabelleMeister', $html);
        self::assertStringContainsString('>1.50<', $html, 'Quote');
        self::assertStringContainsString('wertung=intern&amp;teamintern=Rot" title="T144"', $html, 'Link zur internen Wertung');
        self::assertStringNotContainsString('lmo-tab', $html, 'ohne Spieltag keine Pfeile');
    }

    public function testBisSpieltagMitPfeilen(): void
    {
        $html = $this->seite($this->kontext(['endtab' => '2']));

        self::assertStringContainsString('2. T2', $html);
        self::assertStringContainsString('IMG/lmo-tab1.gif', $html, 'tester steigt auf');
        self::assertStringContainsString('IMG/lmo-tab2.gif', $html);
        self::assertStringContainsString('title="T43"', $html, 'zurueck zu Spieltag 1');
    }

    public function testTeamUndIntern(): void
    {
        $team = $this->seite($this->kontext(['wertung' => 'team']));
        self::assertStringContainsString('>T26Ø</acronym>', $team);
        self::assertLessThan(strpos($team, '>Rot<') ?: 0, strpos($team, '>Blau<') ?: PHP_INT_MAX);

        $intern = $this->seite($this->kontext(['wertung' => 'intern', 'teamintern' => 'Rot']));
        self::assertStringContainsString('(anna)', $intern);
        self::assertStringNotContainsString('(tester)', $intern);
    }

    public function testGastUndOhneAuswertung(): void
    {
        self::assertStringContainsString('<caption>T158</caption>', $this->seite($this->kontext(sitzung: ['lmotipperok' => '0'])));

        unlink($this->ordner . '/tipps/auswert/x.aus');
        $ohne = $this->seite($this->kontext(['endtab' => '2']));
        self::assertSame(2, substr_count($ohne, 'T17'), 'je Rechnung eine Meldung, wie bis 4.5');
    }
}
