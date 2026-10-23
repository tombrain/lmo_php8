<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Viewer;

use Lmo\Addon\Viewer\OptionenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Der Assistent des Viewers in der Administration: Einstellungen, Ligen, Mannschaften, Speichern.
 */
class OptionenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-viewer-' . uniqid();
        foreach (['ligen', 'template/viewer', 'config/viewer'] as $unter) {
            mkdir($this->ordner . '/' . $unter, 0777, true);
        }
        file_put_contents($this->ordner . '/ligen/a.l98', "[Options]\nName=Liga A\nTeams=4\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n4=Delta\n");
        file_put_contents($this->ordner . '/ligen/b.l98', "[Options]\nName=\nTeams=2\n\n[Teams]\n1=Eins\n2=Zwei\n");
        file_put_contents($this->ordner . '/template/viewer/standard.tpl.php', '');
        file_put_contents($this->ordner . '/template/viewer/klein.tpl.php', '');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): string
    {
        $text = [2 => 'Spieltag', 138 => 'Gespeichert', 145 => 'ST', 507 => 'Liga ohne Namen', 567 => 'Fehler'];
        $t = [];
        for ($i = 0; $i <= 100; $i++) {
            $t[$i] = 'V' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'vieweroptions']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/']),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['viewer' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new OptionenController('/addon/viewer', $this->ordner . '/template/viewer', $this->ordner . '/config/viewer', '/js');
        if (!$controller->zustaendig($kontext)) {
            return '(nicht zustaendig)';
        }

        return $controller->zeige($kontext)->html;
    }

    public function testNurHauptadmin(): void
    {
        self::assertSame('(nicht zustaendig)', $this->seite([], 1));
    }

    public function testEinstellungen(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<script src="/js/viewer.js"', $html);
        self::assertStringContainsString('name="formular1"', $html);
        self::assertMatchesRegularExpression('~<option>klein</option>\s*<option>standard</option>~', $html, 'Vorlagen sortiert');
        self::assertStringContainsString('name="spieltagtext" size="10" value="ST"', $html);
        self::assertStringContainsString('action="/lmoadmin.php?action=admin&todo=vieweroptions"', $html);
    }

    public function testLigen(): void
    {
        $html = $this->seite(['formular1' => '1', 'dateiname' => 'meine', 'modus' => '2', 'anzahl_tage_plus' => '5x', 'heute_highlight' => 'on', 'titelzeile' => 'Titel']);

        self::assertStringContainsString('<input type="checkbox" name="c1" value="c1"> Liga A<br>', $html);
        self::assertStringContainsString('<input type="checkbox" name="c2" value="c2"> Liga ohne Namen 1<br>', $html);
        self::assertStringContainsString('name="zaehler" value="3"', $html);
        self::assertStringContainsString('name="dateiname" value="meine"', $html);
        self::assertStringContainsString('name="config_array" value="modus=2;anzahl_tage_plus=5;anzahl_tage_minus=;anzahl_spieltage_vor=;anzahl_spieltage_zurueck=;datumsformat=d.m.Y;heute_highlight=1;tabelle_verlinken=;spielberichte_neues_fenster=;mannschaftshomepages_verlinken=;mannschaftsnamen=;titelzeile=Titel;anstosstermin=;template=standard;uhrzeitformat=H:i;tordummy=_;tabellensymbol=tabelle.gif;spielberichtesymbol=bericht.gif;notizsymbol=notiz.gif;spieltagtext=;cache_refresh=;favteam_highlight="', $html);
    }

    public function testAnfuehrungszeichenBleibenErhalten(): void
    {
        $html = $this->seite(['formular1' => '1', 'dateiname' => 'meine', 'modus' => '1', 'titelzeile' => 'Die "Ansicht"']);

        self::assertStringContainsString('titelzeile=Die &quot;Ansicht&quot;;', $html, 'maskiert im versteckten Feld (bis 4.5 endete es am ersten Anfuehrungszeichen)');
        self::assertStringContainsString('name="dateiname" value="meine"', $html);

        $gespeichert = $this->seite(['formular3' => '1', 'zaehler' => '0', 'dateiname' => 'meine', 'config_array' => 'modus=1']);
        self::assertStringContainsString("&lt;?php \n\$multi=&#039;meine&#039;;", $gespeichert, 'auch der Code zum Einbinden');
    }

    public function testMannschaften(): void
    {
        $html = $this->seite(['formular2' => '1', 'B2' => 'x', 'zaehler' => '3', 'c1' => 'c1', 'dateiname' => 'meine', 'config_array' => 'modus=1']);

        self::assertStringContainsString('<h1>Liga A</h1>', $html);
        self::assertStringContainsString('<input type="checkbox" name="t0" value="a.l98[1]">Alpha</td>', $html);
        self::assertStringContainsString('<input type="checkbox" name="t3" value="a.l98[4]">Delta</td>', $html);
        self::assertStringNotContainsString('Eins', $html);
        self::assertStringContainsString('name="zaehler" value="4"', $html);
    }

    public function testNameBleibtImOrdner(): void
    {
        self::assertTrue(OptionenController::gueltigerName('Meine Ansicht ü-1'));
        foreach (['', '../x', 'a/b', 'a\\b', '.versteckt'] as $name) {
            self::assertFalse(OptionenController::gueltigerName($name), $name);
        }

        $html = $this->seite(['formular3' => '1', 'zaehler' => '0', 'dateiname' => '../../lmo', 'config_array' => 'modus=1']);
        self::assertStringContainsString('Fehler: ../../lmo', $html);
        self::assertStringContainsString('name="formular1"', $html, 'zurueck zu den Einstellungen');
        self::assertFileDoesNotExist($this->ordner . '/lmo.view');
    }

    public function testSpeichern(): void
    {
        $html = $this->seite(['formular3' => '1', 'zaehler' => '4', 'dateiname' => 'meine', 'config_array' => 'modus=1;titelzeile=T', 't0' => 'a.l98[1]', 't2' => 'a.l98[3]', 't3' => 'b.l98[2]']);

        self::assertStringContainsString('Gespeichert', $html);
        self::assertStringContainsString("\$multi=&#039;meine&#039;;\ninclude(&#039;/addon/viewer/viewer.php&#039;);", $html);
        self::assertSame("[config]\nmodus=1\ntitelzeile=T\n[Viewer Ligen]\nliga1=a.l98\na.l98_1=1\na.l98_2=3\nliga2=b.l98\nb.l98_1=2\n", file_get_contents($this->ordner . '/config/viewer/meine.view'));
    }
}
