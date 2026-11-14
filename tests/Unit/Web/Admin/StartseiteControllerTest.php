<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Addon\Tipp\TippAddon;
use Lmo\Web\Admin\StartseiteController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Startseite der Administration: Aufgaben je nach Rolle, die Ligen zum Oeffnen und die Punkte
 * der Addons.
 */
class StartseiteControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminstart-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\nActual=3\n");
        file_put_contents($this->ordner . '/ligen/fremd.l98', "[Options]\nName=Fremde Liga\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    /** @param array<string, string> $parameter */
    private function kontext(int $rolle, array $parameter = []): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'admin']),
            '',
            null,
            new Konfiguration(['eintippspiel' => '1', 'dirliga' => 'ligen/', 'liga_sort' => 'file_name', 'liga_sort_direction' => 'asc', 'defdateformat' => 'd.m.Y']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            new Addons([new TippAddon($this->ordner)]),
            ['tipp' => $texte],
            new Sitzung(['lmouserok' => $rolle])
        );
    }

    private static function controller(): StartseiteController
    {
        return new StartseiteController(static fn (string $datei): bool => $datei != 'fremd.l98');
    }

    public function testHauptadmin(): void
    {
        $kontext = $this->kontext(2);
        self::assertTrue(self::controller()->zustaendig($kontext));
        $html = self::controller()->zeige($kontext)->html;

        self::assertStringContainsString('<h1>T92</h1>', $html);
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=new">T93</a>', $html);
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=tipp">T115</a>', $html, 'Punkt des Tippspiels');
        self::assertStringContainsString('/lmoadmin.php?todo=&amp;file=bula.l98', $html, 'Liga zum Oeffnen');
        self::assertSame('file_name', $kontext->sitzung->text('liga_sort'), 'Sortierung aus der Konfiguration gemerkt');
    }

    public function testHilfsadmin(): void
    {
        $kontext = $this->kontext(1, ['liga_sort' => 'liga_name']);
        $html = self::controller()->zeige($kontext)->html;

        self::assertStringNotContainsString('todo=new', $html);
        self::assertStringNotContainsString('todo=tipp', $html);
        self::assertStringContainsString('todo=download', $html);
        self::assertStringNotContainsString('fremd.l98', $html, 'nur die eigenen Ligen');
        self::assertSame('liga_name', $kontext->sitzung->text('liga_sort'), 'Sortierung aus der Adresse gemerkt');
    }

    public function testZustaendig(): void
    {
        self::assertFalse(self::controller()->zustaendig($this->kontext(2, ['todo' => 'open'])));
        self::assertFalse(self::controller()->zustaendig($this->kontext(0)), 'ohne Anmeldung');
    }
}
