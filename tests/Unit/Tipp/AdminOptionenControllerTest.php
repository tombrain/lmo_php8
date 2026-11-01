<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AdminOptionenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Optionen des Tippspiels in der Administration: Seiten ansehen und speichern.
 */
class AdminOptionenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tippoptionen-' . uniqid();
        mkdir($this->ordner . '/config/tipp', 0777, true);
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/config/cfg.txt', "dirliga=ligen/\ntimezone=Europe/Berlin\n");
        file_put_contents($this->ordner . '/config/tipp/cfg.txt', "dirtipp=tipps/\ntippmodus=1\nrergebnis=4\nrtendenzdiff=3\nrtendenz=2\nrtor=1\n"
            . "rtendenztor=1\nrtendenzremis=1\nrremis=1\nentscheidungnv=1\nentscheidungie=1\ngtpunkte=0\nkrit1=0\nimmeralle=0\nligenzutippen=cup\n");
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, mixed> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): ?string
    {
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        $t = [];
        for ($i = 0; $i <= 310; $i++) {
            $t[$i] = 'P' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tippoptions']),
            '',
            null,
            Konfiguration::lies($this->ordner . '/config'),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminOptionenController($this->ordner . '/config');

        return $controller->zustaendig($kontext) ? $controller->zeige($kontext)->html : null;
    }

    private function cfg(): string
    {
        return (string) file_get_contents($this->ordner . '/config/tipp/cfg.txt');
    }

    public function testNurHauptadmin(): void
    {
        self::assertNull($this->seite([], 1));
    }

    public function testAnsehen(): void
    {
        $html = (string) $this->seite([]);

        self::assertStringContainsString('<h1>P33</h1>', $html);
        self::assertStringContainsString('<tr><td align="right">P91</td></tr>', $html, 'die gezeigte Seite ohne Link');
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=tippoptions&amp;show=1">T220</a>', $html);
        self::assertStringContainsString('<option value="1" selected>P31</option>', $html);
        self::assertStringContainsString('<input type="hidden" name="show" value="0">', $html);
        self::assertStringNotContainsString('T138', $html);
    }

    public function testPunkteSpeichern(): void
    {
        $html = (string) $this->seite(['save' => '1', 'show' => '2', 'xrergebnis' => '2', 'xrtendenzdiff' => '1', 'xrtendenz' => '5', 'xrtor' => 'abc', 'xgtpunkte' => '2']);

        self::assertStringStartsWith('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> T138</p>', $html);
        self::assertStringContainsString("rergebnis=2\nrtendenzdiff=5\nrtendenz=5\nrtor=0\nrtendenztor=0\nrtendenzremis=0\nrremis=0\nentscheidungnv=0\nentscheidungie=0\ngtpunkte=2\n", $this->cfg(),
            'die Differenz mindestens die Tendenz, ohne Zahl 0');
        self::assertStringContainsString('name="xrergebnis" size="5" maxlength="5" value="2"', $html, 'die neuen Werte');
        self::assertStringContainsString("timezone=Europe/Berlin\n", (string) file_get_contents($this->ordner . '/config/cfg.txt'));
    }

    public function testImTendenzModusNurRemis(): void
    {
        $this->seite(['save' => '1', 'show' => '0', 'xtippmodus' => '0']);
        $html = (string) $this->seite(['save' => '1', 'show' => '2', 'xrergebnis' => '9', 'xrremis' => '3']);

        self::assertStringContainsString("tippmodus=0\nrergebnis=4\n", $this->cfg());
        self::assertStringContainsString("rremis=3\n", $this->cfg());
        self::assertStringContainsString('value="4" onChange="dolmoedit()" disabled>', $html);
    }

    public function testTippordner(): void
    {
        $this->seite(['save' => '1', 'show' => '1', 'xdirtipp' => 'meine\\tipps']);

        self::assertStringContainsString("dirtipp=meine/tipps/\n", $this->cfg());

        $this->seite(['save' => '1', 'show' => '1']);
        self::assertStringContainsString("dirtipp=meine/tipps/\n", $this->cfg(), 'ohne Feld der bisherige');
        self::assertStringContainsString("krit1=0\n", $this->cfg(), 'bis 4.5 krit1=tipps/');
    }

    public function testLigen(): void
    {
        $html = (string) $this->seite(['show' => '12']);
        self::assertStringContainsString('value="bula" onClick="dolmoedit()">Bundesliga<br>', $html);
        self::assertStringContainsString('value="cup"checked onClick="dolmoedit()">T5071<br>', $html, 'ohne Namen');

        $this->seite(['save' => '1', 'show' => '12', 'xtipperligen' => ['bula', 'cup']]);
        self::assertStringContainsString("immeralle=0\nligenzutippen=bula,cup\n", $this->cfg());

        $html = (string) $this->seite(['save' => '1', 'show' => '12', 'ximmeralle' => 'on', 'xtipperligen' => ['bula']]);
        self::assertStringContainsString("immeralle=1\nligenzutippen=\n", $this->cfg());
        self::assertStringContainsString('value="bula"checked onClick="dolmoedit()" disabled>', $html);
    }

    public function testUnbekannteSeite(): void
    {
        $html = (string) $this->seite(['show' => 'x"y']);

        self::assertStringNotContainsString('name="xtippmodus"', $html);
        self::assertStringContainsString('<input type="hidden" name="show" value="x&quot;y">', $html, 'maskiert');
    }
}
