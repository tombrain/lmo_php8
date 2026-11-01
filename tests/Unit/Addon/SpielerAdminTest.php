<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Spieler\AdminController;
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
 * Die Pflege der Spielerstatistik in der Administration: Rechte, Spieler, Spalten, Werte, Formeln
 * und die Einstellungen der Liga.
 */
class SpielerAdminTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-spieleradmin-' . uniqid();
        mkdir($this->ordner . '/daten', 0777, true);
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/ligen/a.l98', '');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /**
     * @param array<string, string> $anfrage
     * @param array<string, string> $addon Konfiguration des Addons
     */
    private function seite(array $anfrage, int $rolle = 2, array $addon = []): string
    {
        $t = [];
        for ($i = 0; $i <= 61; $i++) {
            $t[$i] = 'S' . $i;
        }
        $t[2] = 'Name';
        $t[25] = 'Verein';
        $t[43] = '___';
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        foreach ([5001, 5002] as $nr) {
            $text[$nr] = 'T' . $nr;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Teams=2', '[Teams]', '1=Alpha', '2=Beta']));
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98', 'st' => '-4']),
            '',
            new GeladeneLiga('a.l98', $liga, ''),
            new Konfiguration(['dirliga' => 'ligen/', 'einspieler' => '1', 'spieler' => $addon + ['adminbereich_standard_sortierung' => '0', 'adminbereich_hilfsadmin_zulassen' => '1', 'adminbereich_hilfsadmin_fuer_spalten' => '0']]),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['spieler' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminController($this->ordner . '/daten');
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    private function stat(): string
    {
        return (string) file_get_contents($this->ordner . '/daten/a.stat');
    }

    public function testRechte(): void
    {
        self::assertStringContainsString('S33', $this->seite([], 1, ['adminbereich_hilfsadmin_zulassen' => '0']), 'Hilfsadmin nicht zugelassen');
        self::assertFileDoesNotExist($this->ordner . '/daten/a.stat');

        $hilfe = $this->seite([], 1);
        self::assertStringContainsString('name="option" value="addplayer"', $hilfe);
        self::assertStringNotContainsString('value="addcolumn"', $hilfe, 'Spalten nur mit Recht darauf');
        self::assertStringNotContainsString('adminbereich_hilfsadmin_zulassen', $hilfe, 'diese Einstellung nur fuer den Hauptadmin');
        self::assertSame("Name\n", $this->stat(), 'leere Statistik angelegt');
    }

    public function testSpielerSpaltenUndWerte(): void
    {
        $this->seite(['option' => 'addplayer', 'wert' => 'Müller']);
        $this->seite(['option' => 'addcolumn', 'wert' => 'Tore', 'type' => '0']);
        $this->seite(['option' => 'addcolumn', 'wert' => 'Verein', 'type' => '___']);
        $this->seite(['option' => 'addplayer', 'wert' => 'Ahrens']);
        self::assertSame("Name#Tore#Verein\nMüller#0#___\nAhrens#0#___\n", $this->stat());

        $html = $this->seite(['option' => 'statupdate', 'data0|1' => '7', 'data1|2' => 'Beta', 'spalten1' => 'Treffer']);
        self::assertSame("Name#Treffer#Verein\nMüller#7#___\nAhrens#0#Beta\n", $this->stat());
        self::assertStringContainsString('<option value="0">M&uuml;ller</option>', $html, 'Namen maskiert wie bisher');
        self::assertStringContainsString('size="7"', $html, 'Feldgroesse in Bytes (Müller)');
        self::assertMatchesRegularExpression('~<select name="data1\|2" size="1">\s*<option>___</option>\s*<option>Alpha</option>\s*<option selected>Beta</option>~', $html);

        $absteigend = $this->seite(['sort' => '1', 'sort_direction' => 'desc']);
        self::assertLessThan(strpos($absteigend, 'value="M&uuml;ller"'), strpos($absteigend, 'value="Ahrens"'), 'aufsteigend nach Treffern');
    }

    public function testFormel(): void
    {
        $this->seite(['option' => 'addplayer', 'wert' => 'Müller']);
        $this->seite(['option' => 'addcolumn', 'wert' => 'Tore', 'type' => '0']);
        $this->seite(['option' => 'addcolumn', 'wert' => 'Doppelt', 'type' => 'F']);
        $this->seite(['option' => 'statupdate', 'data0|1' => '4', 'formel_str2' => 'tore*2']);
        self::assertSame("Name#Tore#Doppelt*_*-*\n0#0#tore*2\nMüller#4#8\n", $this->stat());

        $this->seite(['option' => 'statupdate', 'formel_str2' => "file_put_contents('x', 'y')"]);
        self::assertStringContainsString("Müller#4#S55\n", $this->stat(), 'andere Zeichen: Fehlertext, nichts ausgefuehrt');
        $this->seite(['option' => 'statupdate', 'formel_str2' => 'tore/']);
        self::assertStringContainsString("Müller#4#S55\n", $this->stat(), 'unvollstaendig: Fehlertext');
        $this->seite(['option' => 'statupdate', 'formel_str2' => 'tore/0']);
        self::assertStringContainsString("Müller#4#0\n", $this->stat(), 'durch 0: 0');

        $this->seite(['option' => 'delcolumn', 'wert' => '2']);
        self::assertSame("Name#Tore\nMüller#4\n", $this->stat());
    }

    public function testSpielerLoeschen(): void
    {
        $this->seite(['option' => 'addplayer', 'wert' => 'A']);
        $this->seite(['option' => 'addplayer', 'wert' => 'B']);
        $this->seite(['option' => 'delplayer', 'wert' => '0']);

        self::assertSame("Name\nB\n", $this->stat());
        self::assertStringContainsString('S4', $this->seite(['option' => 'addplayer', 'wert' => '']), 'ohne Namen ein Hinweis');
    }

    public function testEinstellungenSpeichern(): void
    {
        $html = $this->seite(['option' => 'saveconfig', 'anzeige_pro_seite' => '5', 'standard_sortierung' => '1', 'standard_richtung' => '0', 'adminbereich_standard_sortierung' => '0', 'ligalink' => 'Statistik', 'nullwerte_anzeigen' => 'on', 'adminbereich_hilfsadmin_zulassen' => 'on']);

        self::assertSame("S21=1\nS13=0\nS40=0\nS22=5\nS23=1\nS24=0\nS50=0\nS31=1\nS46=0\nS41=Statistik", file_get_contents($this->ordner . '/daten/a.cfg'));
        self::assertStringContainsString('name="ligalink" onChange="mark(this)" value="Statistik" size="9"', $html);

        $this->seite(['option' => 'saveconfig', 'anzeige_pro_seite' => '3', 'ligalink' => 'X'], 1);
        // wie bisher: Vorsortierung Admin kommt mit ihrem Zeilenumbruch aus der Datei zurueck
        self::assertSame("S21=1\nS13=0\nS40=0\n\nS22=3\nS23=0\nS24=0\nS50=0\nS41=X", file_get_contents($this->ordner . '/daten/a.cfg'), 'Hilfsadmin schreibt seine Rechte nicht');
    }
}
