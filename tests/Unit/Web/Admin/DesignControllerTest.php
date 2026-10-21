<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\DesignController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Das Design: Seiten, Felder und das Speichern der Werte einer Seite.
 */
class DesignControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-admindesign-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/*') ?: []);
        rmdir($this->ordner);
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'design'] + $anfrage),
            '',
            null,
            new Konfiguration(['deflang' => 'Deutsch', 'lmo_main_background1' => '#fff', 'lmo_main_color1' => '#000',
                'lmo_kreuz_fontsize1' => '9pt', 'lmo_main_underline1' => 'underline', 'lmo_middle_underline1' => '']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => 2])
        );
        $controller = new DesignController($this->ordner);
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testSeiten(): void
    {
        $html = $this->seite([]);
        self::assertStringContainsString('<script src="/lmo/js/colorpicker.js"', $html);
        self::assertStringContainsString('<td align="right">T423</td>', $html);
        self::assertStringContainsString('show=6">T462</a>', $html);
        self::assertStringContainsString('name="xlmo_main_background1" id="lmo_main_background1input" size="7" maxlength="7" value="#fff"', $html);
        self::assertStringContainsString('name="xlmo_main_border1" size="25" maxlength="25" value=""', $html, 'fehlende Werte leer');

        $seite5 = $this->seite(['show' => '5']);
        self::assertStringContainsString('name="xlmo_main_underline1" checked onClick', $seite5);
        self::assertStringContainsString('name="xlmo_middle_underline1" onClick', $seite5);
        self::assertFileDoesNotExist($this->ordner . '/cfg.txt', 'ohne save nichts geschrieben');
    }

    public function testSpeichern(): void
    {
        $html = $this->seite(['save' => '1', 'xlmo_main_background1' => '#abc', 'xlmo_main_border1' => '1px', 'xlmo_kreuz_fontsize1' => '12pt']);

        self::assertStringStartsWith(Meldung::html('IMG', 'T138'), $html);
        self::assertStringContainsString('value="#abc"', $html, 'mit den neuen Werten');
        self::assertStringEqualsFile($this->ordner . '/cfg.txt',
            "deflang=Deutsch\nlmo_main_background1=#abc\nlmo_main_color1=#000\nlmo_kreuz_fontsize1=9pt\nlmo_main_underline1=underline\nlmo_middle_underline1=\n",
            'nur Felder der Seite, keine neuen Schluessel');

        $this->seite(['save' => '1', 'show' => '5', 'xlmo_middle_underline1' => 'on']);
        self::assertStringEqualsFile($this->ordner . '/cfg.txt',
            "deflang=Deutsch\nlmo_main_background1=#fff\nlmo_main_color1=#000\nlmo_kreuz_fontsize1=9pt\nlmo_main_underline1=\nlmo_middle_underline1=underline\n",
            'Unterstreichen ohne Haken aus');
    }
}
