<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\TabelleController;
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
 * Die Tabelle nach den Tipps: fuer einen Tipper, fuer alle, fuer Gaeste.
 */
class TabelleControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tipptabelle-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert/vereine', 0777, true);
        file_put_contents($this->ordner . '/tipps/x_tester.tip', "[Round1]\nGA1=2\nGB1=0\n");
        file_put_contents($this->ordner . '/tipps/x_anna.tip', "[Round1]\nGA1=0\nGB1=1\n");
        file_put_contents($this->ordner . '/tipps/y_bert.tip', "[Round1]\nGA1=0\nGB1=5\n");
        file_put_contents($this->ordner . '/tipps/auswert/vereine/x_tester.ver', "[1]\nSG1=1\nTP1=3\n[2]\nSG1=1\nTP1=0\n");
    }

    protected function tearDown(): void
    {
        foreach (['/tipps/auswert/vereine/*', '/tipps/*.tip'] as $muster) {
            array_map('unlink', glob($this->ordner . $muster) ?: []);
        }
        rmdir($this->ordner . '/tipps/auswert/vereine');
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
        for ($i = 0; $i <= 5000; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege([
            '[Options]', 'Name=Testliga', 'Teams=2', 'Rounds=2', 'Matches=1', 'Actual=1', 'PointsForWin=3', 'PointsForDraw=1', 'PointsForLost=0',
            '[Teams]', '1=Alpha', '2=Beta',
            '[Round1]', 'TA1=1', 'TB1=2',
            '[Round2]', 'TA1=2', 'TB1=1',
        ]));

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'tabelle', 'file' => 'x.l98']),
            'tipp',
            $mitLiga ? new GeladeneLiga('x.l98', $liga, '') : null,
            new Konfiguration(['eintippspiel' => '1', 'tabpkt' => '1', 'tipp' => $tipp + [
                'dirtipp' => 'tipps/', 'tipptabelle1' => '1', 'tipptabelle' => '1', 'wertverein' => '1', 'einsichterst' => '0', 'tippmodus' => '1', 'regeln' => '0',
            ]]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung($sitzung)
        );
    }

    private function controller(): TabelleController
    {
        return new TabelleController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
    }

    private function seite(Kontext $kontext): string
    {
        self::assertTrue($this->controller()->zustaendig($kontext));

        return $this->controller()->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        self::assertFalse($this->controller()->zustaendig($this->kontext(['todo' => 'einsicht'])));
        self::assertFalse($this->controller()->zustaendig($this->kontext(['viewermode' => '1'])));
        self::assertFalse($this->controller()->zustaendig($this->kontext(mitLiga: false)));
    }

    public function testEigeneTipps(): void
    {
        $html = $this->seite($this->kontext());

        self::assertStringContainsString('<caption>tester</caption>', $html);
        self::assertStringContainsString('>T173</th>', $html);
        self::assertLessThan(strpos($html, 'Beta') ?: 0, strpos($html, 'Alpha') ?: PHP_INT_MAX, 'Alpha hat nach dem Tipp gewonnen');
        self::assertStringContainsString('<strong>3.00</strong>', $html);
        self::assertStringContainsString('1. T2&nbsp;</th>', $html, 'Stand');
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=tabelle&amp;file=x.l98&amp;tabtype=0&amp;nick=tester&amp;st=2" title="T8">', $html, 'naechster Spieltag');
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=tabelle&amp;file=x.l98&amp;endtab=1&amp;nick=tester&amp;tabtype=1" title="T28">T41</a>', $html);
        self::assertStringContainsString('>T261</th>', $html, 'Wertung nach Vereinen');
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=tabelle&amp;file=x.l98&amp;endtab=&amp;nick=" title="T184">T183</a>', $html);
    }

    public function testAlleTipper(): void
    {
        $html = $this->seite($this->kontext(['nick' => '']));

        self::assertStringContainsString('>T184</th>', $html);
        self::assertStringNotContainsString('<caption>', $html, 'ohne Auswahl der Tabellenart');
        self::assertStringContainsString('<strong>1.50</strong>', $html, 'zwei Tipper der Liga x, je ein Sieg: 3 Punkte aus 2 Spielen');
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=tabelle&amp;file=x.l98&amp;endtab=&amp;nick=tester" title="T173">T182</a>', $html, 'zur eigenen Tabelle');
        self::assertStringContainsString('&ampnick=tester&amp;tabtype=1', $this->seite($this->kontext(['nick' => 'tester', 'st' => '0'])), 'ohne Stand wie bis 4.5');
    }

    public function testGaesteUndFremdeOrdner(): void
    {
        $gast = $this->seite($this->kontext(sitzung: ['lmotipperok' => '0']));
        self::assertStringContainsString('>T184</th>', $gast, 'ohne Nick die Tipps aller');

        $fremd = $this->seite($this->kontext(['nick' => '../tipps/x_tester']));
        self::assertStringContainsString('T181 ../tipps/x_tester', $fremd);
        self::assertStringContainsString('<strong>0.00</strong>', $fremd, 'keine Tipps aus fremden Pfaden');
        self::assertStringContainsString('T17', $fremd, 'und keine Auswertung');
        self::assertStringNotContainsString('lmoInner', $this->seite($this->kontext(tipp: ['tipptabelle1' => '0'])));
    }
}
