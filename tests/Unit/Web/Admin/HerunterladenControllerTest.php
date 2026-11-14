<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\HerunterladenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Ligen herunterladen oder per E-Mail schicken: die beiden Listen je nach Rolle.
 */
class HerunterladenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminherunter-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/ligen/a.l98', "[Options]\nName=Erste\n");
        file_put_contents($this->ordner . '/ligen/b.l98', "[Options]\n");
        file_put_contents($this->ordner . '/ligen/c.l98', "[Options]\nName=Dritte\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    /** @param array<string, int|string> $sitzung */
    private function seite(array $sitzung): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'download']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'aadr' => 'admin@example.org']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung($sitzung)
        );
        $controller = new HerunterladenController();
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testHauptadmin(): void
    {
        $html = $this->seite(['lmouserok' => 2]);

        self::assertStringContainsString('<a href="/lmo/lmo-admindownload.php?action=admin&amp;todo=download&amp;down=1">Erste</a>', $html);
        self::assertStringContainsString('down=2">T223 1</a>', $html, 'ohne Namen beim Herunterladen');
        self::assertStringContainsString("down=2\" onclick=\"emllmolink(this.href,'admin@example.org');return false;\">T507 1</a>", $html, 'ohne Namen beim Schicken');
        self::assertStringContainsString('down=-1"><strong>T402</strong>', $html, 'alle herunterladen');
        self::assertStringContainsString("down=-1\" onclick=\"emllmolink(this.href,'admin@example.org');return false;\"><strong>T401</strong>", $html, 'alle schicken');
    }

    public function testHilfsadmin(): void
    {
        $html = $this->seite(['lmouserok' => 1, 'lmouserfile' => 'c']);

        self::assertStringContainsString('down=3">Dritte</a>', $html, 'die Nummer in der ganzen Liste');
        self::assertStringNotContainsString('Erste', $html);
        self::assertStringNotContainsString('down=-1', $html, 'nicht alle');

        self::assertStringContainsString('<li>[T223]</li>', $this->seite(['lmouserok' => 1, 'lmouserfile' => 'keine']), 'keine eigenen Ligen');
    }
}
