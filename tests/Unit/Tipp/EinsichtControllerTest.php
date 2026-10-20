<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\EinsichtController;
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
 * Die Einsicht eines Spieltags: die Tipps aller Tipper, fuer Angemeldete und Gaeste.
 */
class EinsichtControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-einsicht-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/einsicht', 0777, true);
        // anna mit Joker auf Spiel 1, bert ohne Tipp fuer Spiel 2
        file_put_contents($this->ordner . '/tipps/einsicht/x_1.ein', "\n[bert]\nGA1=1\nGB1=1\nGA2=-1\nGB2=-1\n\n[anna]\n@1@\nGA1=2\nGB1=1\nGA2=0\nGB2=3\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/tipps/einsicht/*') ?: []);
        rmdir($this->ordner . '/tipps/einsicht');
        rmdir($this->ordner . '/tipps');
        rmdir($this->ordner);
    }

    /**
     * @param array<string, string> $parameter
     * @param array<string, string> $tipp
     * @param array<string, int|string> $sitzung
     */
    private function kontext(array $parameter = [], array $tipp = [], array $sitzung = ['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => 'FC'], bool $mitLiga = true): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege([
            '[Options]', 'Name=Testliga', 'Teams=4', 'Rounds=1', 'Matches=2', 'Actual=1',
            '[Teams]', '1=Alpha', '2=Beta', '3=Gamma', '4=Delta',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=1', 'TA2=3', 'TB2=4', 'GA2=-1', 'GB2=-1',
        ]));

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'einsicht', 'file' => 'x.l98']),
            'tipp',
            $mitLiga ? new GeladeneLiga('x.l98', $liga, '') : null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => $tipp + [
                'dirtipp' => 'tipps/', 'tippeinsicht' => '1', 'einsichterst' => '0', 'anzseite' => '20', 'shownick' => '1', 'showname' => '0',
                'tippmodus' => '1', 'rergebnis' => '5', 'rtendenzdiff' => '3', 'rtendenz' => '2', 'rtor' => '0', 'rremis' => '0',
                'jokertipp' => '1', 'jokertippmulti' => '2', 'tipptabelle1' => '1', 'regeln' => '0',
            ]]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung($sitzung)
        );
    }

    private function controller(): EinsichtController
    {
        return new EinsichtController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
    }

    private function seite(Kontext $kontext): string
    {
        self::assertTrue($this->controller()->zustaendig($kontext));

        return $this->controller()->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        self::assertFalse($this->controller()->zustaendig($this->kontext(['todo' => 'edit'])));
        self::assertFalse($this->controller()->zustaendig($this->kontext(['viewermode' => '1'])), 'die Einsicht im Viewer zeigt noch der alte Code');
        self::assertFalse($this->controller()->zustaendig($this->kontext(mitLiga: false)), 'ohne Liga die Startseite');
    }

    public function testTippsPunkteUndStatistik(): void
    {
        $html = $this->seite($this->kontext());

        self::assertStringContainsString('<caption>tester - FC</caption>', $html);
        self::assertStringNotContainsString('T220', $html, 'Tipps immer sichtbar: kein Hinweis');
        self::assertLessThan(strpos($html, "\"nobr\">bert\n") ?: 0, strpos($html, "\"nobr\">anna\n") ?: PHP_INT_MAX, 'nach Nick sortiert');
        self::assertStringContainsString("<td align=\"center\" class=\"lmoBackMarkierung nobr\"><em class='lmoFrontMarkierung'><strong>2:1</strong></em> <small>10</small>", $html, 'Joker verdoppelt');
        self::assertStringContainsString('<td align="center" class="nobr">1:1 <small>0</small>', $html);
        self::assertStringContainsString('<td align="center" class="nobr">_:_&nbsp;', $html, 'kein Tipp');
        self::assertStringContainsString('<td class="lmoFrontMarkierung" align="right">10</td>', $html);
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=tabelle&amp;file=x.l98&amp;endtab=&amp;nick=anna" title="T181 anna">', $html);
        self::assertStringContainsString('>T188', $html);
        self::assertStringContainsString('>1-1-0', $html, 'Tendenz Spiel 1');
        self::assertStringContainsString('<strong>1.5:1.0', $html, 'Durchschnittstipp');
        self::assertStringContainsString('Alpha<br>2:1<br>Beta', $html);
    }

    public function testTendenzModus(): void
    {
        $html = $this->seite($this->kontext(tipp: ['tippmodus' => '0']));

        self::assertStringContainsString("<td align=\"center\" class=\"lmoBackMarkierung nobr\"><p class='lmoFrontMarkierung'>1", $html);
        self::assertStringContainsString('<td align="center" class="nobr">_', $html);
        self::assertStringNotContainsString('Ø-', $html);
    }

    public function testErstNachDemErgebnis(): void
    {
        $html = $this->seite($this->kontext(tipp: ['einsichterst' => '2']));

        self::assertStringContainsString('<td class="lmoFrontMarkierung">T220 T217</td>', $html);
        self::assertStringNotContainsString('0:3', $html, 'Spiel 2 hat noch kein Ergebnis');
        self::assertStringContainsString('2:1', $html);
    }

    public function testSeiten(): void
    {
        $html = $this->seite($this->kontext(['start' => '1'], ['anzseite' => '1']));

        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=einsicht&amp;file=x.l98&amp;st=1&amp;start=0">ann-ann</a>', $html);
        self::assertStringContainsString('ber-ber&nbsp;', $html);
        self::assertStringNotContainsString("\"nobr\">anna\n", $html);
        self::assertStringContainsString("\"nobr\">bert\n", $html);
    }

    public function testOhneEinsichtsdatei(): void
    {
        unlink($this->ordner . '/tipps/einsicht/x_1.ein');
        $html = $this->seite($this->kontext());

        self::assertStringContainsString('T17', $html);
        self::assertStringContainsString('<table class="lmoInner"', $html);
    }

    public function testGaeste(): void
    {
        $gast = $this->kontext(sitzung: ['lmotipperok' => '-5']);
        $html = $this->seite($gast);

        self::assertStringContainsString('<caption>T158</caption>', $html);
        self::assertStringContainsString("\"nobr\">anna\n", $html);
        self::assertSame(0, $gast->sitzung->zahl('lmotipperok'), '"Kennwort vergessen" verlassen');

        $nichtFreigeschaltet = $this->seite($this->kontext(sitzung: ['lmotipperok' => '3']));
        self::assertStringNotContainsString('lmoKreuz', $nichtFreigeschaltet);

        $abgeschaltet = $this->seite($this->kontext(tipp: ['tippeinsicht' => '0'], sitzung: ['lmotipperok' => '0']));
        self::assertStringNotContainsString('lmoKreuz', $abgeschaltet);
    }
}
