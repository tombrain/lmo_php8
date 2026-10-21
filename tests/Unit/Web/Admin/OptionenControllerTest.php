<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Web\Admin\OptionenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Optionen der Installation: die drei Seiten und das Speichern ihrer Werte.
 */
class OptionenControllerTest extends TestCase
{
    /** Samstag, 15.03.2025 13:00 in Europe/Berlin */
    private const JETZT = 1742040000;

    private const WERTE = [
        'deflang' => 'Deutsch', 'timezone' => 'Europe/Berlin', 'dirliga' => 'ligen/', 'deftime' => '15:00',
        'defdateformat' => 'd.m.Y H:i', 'aadr' => 'a@b.de', 'liga_sort' => 'file_date', 'liga_sort_direction' => 'desc',
        'tabpkt' => '1', 'tabonres' => '2', 'backlink' => '1', 'archivlink' => '0', 'calctime' => '0', 'einsavehtml' => '1',
        'einsprachwahl' => '1', 'einspieler' => '0', 'eintippspiel' => '1', 'einspielfrei' => '0', 'einzutore' => '0',
        'einzutoretab' => '1', 'einzustats' => '0', 'tipp' => ['a' => '1'],
    ];

    private string $ordner;

    private string $zeitzone;

    protected function setUp(): void
    {
        $this->zeitzone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->ordner = sys_get_temp_dir() . '/lmo-adminoptionen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/lang', 0777, true);
        mkdir($this->ordner . '/tipp');
        touch($this->ordner . '/lang/lang-Deutsch.txt');
        touch($this->ordner . '/lang/lang-English.txt');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zeitzone);
        array_map('unlink', array_merge(glob($this->ordner . '/lang/*') ?: [], array_filter([$this->ordner . '/cfg.txt', $this->ordner . '/tipp/cfg.txt'], 'is_file')));
        rmdir($this->ordner . '/lang');
        rmdir($this->ordner . '/tipp');
        rmdir($this->ordner);
    }

    /** @param array<string, mixed> $anfrage */
    private function seite(array $anfrage): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'options'] + $anfrage),
            '',
            null,
            new Konfiguration(self::WERTE),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            new Addons(),
            ['date' => [5 => 'Samstag', 12 => 'Sa']],
            new Sitzung(['lmouserok' => 2])
        );
        $controller = new OptionenController($this->ordner, self::JETZT);
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    /** @return array<string, string> */
    private function gespeichert(): array
    {
        return array_map('strval', (array) parse_ini_file($this->ordner . '/cfg.txt', false, INI_SCANNER_RAW));
    }

    public function testAllgemein(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<td align="right">T99</td>', $html, 'Seite 0 ohne Link');
        self::assertStringContainsString('show=1">T226</a>', $html);
        self::assertStringContainsString('<option selected>Deutsch</option>', $html);
        self::assertStringContainsString('<option>English</option>', $html);
        self::assertStringContainsString('<img src="IMG/Deutsch.svg" height="16">', $html);
        self::assertStringContainsString('<option value="Europe/Berlin" selected>Berlin</option>', $html);
        self::assertStringContainsString('<option value="d.m.Y H:i" selected>15.03.2025 13:00</option>', $html);
        self::assertStringContainsString('<option value="l, d.m. H:i">Samstag, 15.03. 13:00</option>', $html, 'Namen in der Sprache');
        self::assertStringContainsString('<input type="radio" name="xdefdateselect" value="">', $html, 'kein eigenes Format');
        self::assertStringContainsString('D = Sa<br>', $html);
        self::assertStringContainsString('<option value="file_date" selected>', $html);
        self::assertStringContainsString('value="desc" checked>', $html);
        self::assertStringNotContainsString('xtabpkt', $html);
        self::assertFileDoesNotExist($this->ordner . '/cfg.txt', 'ohne save nichts geschrieben');
    }

    public function testTabelleUndSchalter(): void
    {
        $tabelle = $this->seite(['show' => '1']);
        self::assertStringContainsString('<option value="1" selected>T230</option>', $tabelle);
        self::assertStringContainsString('<option value="2" selected>T235</option>', $tabelle);

        $schalter = $this->seite(['show' => '2']);
        self::assertStringContainsString('name="xbacklink" onChange="dolmoedit()" checked>', $schalter);
        self::assertStringContainsString('name="xarchivlink" onChange="dolmoedit()">', $schalter);
        self::assertStringContainsString('name="xeinzutoretab" onChange="dolmoedit()" checked>&nbsp;T491', $schalter);
    }

    public function testSpeichernAllgemein(): void
    {
        $html = $this->seite(['save' => '1', 'show' => '0', 'xdirliga' => ' daten\\ligen ', 'xdeflang' => ' English ', 'xdeftime' => '9:5',
            'xdefdateselect' => '', 'xdefdateformat' => 'd.m. H:i', 'xdefdateformat2' => 'Y-m-d', 'xtimezone' => 'Europe/Vienna', 'xadr' => 'x@y.de']);

        self::assertStringStartsWith(Meldung::html('IMG', 'T138'), $html);
        self::assertStringContainsString('value="a@b.de"', $html, 'wie bis 4.5 noch die alten Werte');
        $cfg = $this->gespeichert();
        self::assertSame(array_keys(array_diff_key(self::WERTE, ['tipp' => 0])), array_keys($cfg), 'alle Schluessel in ihrer Reihenfolge');
        self::assertSame(['daten/ligen/', 'English', '09:05', 'Y-m-d', 'Europe/Vienna', 'x@y.de', 'liga_name', 'asc'],
            [$cfg['dirliga'], $cfg['deflang'], $cfg['deftime'], $cfg['defdateformat'], $cfg['timezone'], $cfg['aadr'], $cfg['liga_sort'], $cfg['liga_sort_direction']]);
        self::assertSame('1', $cfg['tabpkt'], 'andere Seiten bleiben');
        self::assertStringEqualsFile($this->ordner . '/tipp/cfg.txt', "a=1\n");

        $this->seite(['save' => '1', 'show' => '0', 'xdirliga' => '', 'xdefdateselect' => '1', 'xdefdateformat' => 'd.m. H:i', 'xneu' => '1']);
        $cfg = $this->gespeichert();
        self::assertSame(['./', 'Deutsch', '15:30', 'd.m. H:i'], [$cfg['dirliga'], $cfg['deflang'], $cfg['deftime'], $cfg['defdateformat']], 'Vorgaben und Auswahl');
    }

    public function testSpeichernSchalter(): void
    {
        $this->seite(['save' => '1', 'show' => '2', 'xarchivlink' => 'on', 'xeinspieler' => 'on']);
        $cfg = $this->gespeichert();

        self::assertSame(['0', '1', '1', '0'], [$cfg['backlink'], $cfg['archivlink'], $cfg['einspieler'], $cfg['eintippspiel']], 'ohne Haken aus');
        self::assertSame('2', $cfg['tabonres'], 'andere Seiten bleiben');
    }

    public function testZeitzonen(): void
    {
        $zonen = OptionenController::zeitzonen();

        self::assertSame('Africa', array_key_first($zonen));
        self::assertSame('New York', $zonen['America']['America/New_York']);
        self::assertArrayHasKey('America/Argentina', $zonen['America'], 'nur die ersten beiden Teile');
        self::assertArrayNotHasKey('UTC', $zonen);
    }
}
