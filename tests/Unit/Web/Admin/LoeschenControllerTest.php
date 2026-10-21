<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\LoeschenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Eine Liga loeschen: die Auswahl und das Loeschen, nur fuer den Hauptadmin.
 */
class LoeschenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminloeschen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen/archiv', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\n");
        file_put_contents($this->ordner . '/ligen/ohne.l98', "[Options]\n");
        file_put_contents($this->ordner . '/ligen/archiv/alt.l98', "[Options]\nName=Alt\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(array_merge(glob($this->ordner . '/ligen/*') ?: [], glob($this->ordner . '/ligen/archiv/*') ?: []), 'is_file'));
        rmdir($this->ordner . '/ligen/archiv');
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    /** @param array<string, string> $parameter */
    private function kontext(array $parameter = [], int $rolle = 2): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'admin', 'todo' => 'delete']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
    }

    public function testAuswahl(): void
    {
        $controller = new LoeschenController();
        self::assertFalse($controller->zustaendig($this->kontext(rolle: 1)), 'nur fuer den Hauptadmin');
        $html = $controller->zeige($this->kontext())->html;

        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=delete&amp;del=1&amp;dfile=ligen/bula.l98"', $html);
        self::assertStringContainsString('>T507 1</a>', $html, 'ohne Namen');
        self::assertStringNotContainsString('alt.l98', $html, 'nur der Ligenordner');
    }

    public function testLoeschen(): void
    {
        $html = (new LoeschenController())->zeige($this->kontext(['del' => '1', 'dfile' => 'ligen/bula.l98']))->html;

        self::assertFileDoesNotExist($this->ordner . '/ligen/bula.l98');
        self::assertStringStartsWith(Meldung::html('IMG', 'ligen/bula.l98 T297'), $html);
        self::assertStringNotContainsString('dfile=ligen/bula.l98', $html, 'nicht mehr in der Liste');

        $fehlt = (new LoeschenController())->zeige($this->kontext(['del' => '1', 'dfile' => 'ligen/gibtsnicht.l98']))->html;
        self::assertStringStartsWith(Meldung::html('IMG', 'ligen/gibtsnicht.l98 T298', true), $fehlt);
    }

    public function testMeldung(): void
    {
        self::assertSame('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> gut</p>', Meldung::html('IMG', 'gut'));
        self::assertSame('<p class="error"><img src="IMG/wrong.svg" border="0" width="20" alt=""> schlecht</p>', Meldung::html('IMG', 'schlecht', true));
    }
}
