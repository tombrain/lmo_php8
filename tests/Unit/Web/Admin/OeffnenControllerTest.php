<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\OeffnenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Eine Liga oeffnen: die Ligen eines Ordners, seine Unterordner und der Weg zurueck.
 */
class OeffnenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminoeffnen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen/archiv/2020', 0777, true);
        mkdir($this->ordner . '/ligen/Bezirk');
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\n");
        file_put_contents($this->ordner . '/ligen/archiv/alt.l98', "[Options]\nName=Alte Liga\n");
        file_put_contents($this->ordner . '/ligen/archiv/dir-descr.txt', 'Ältere Spielzeiten');
    }

    protected function tearDown(): void
    {
        foreach (['/ligen/archiv/2020', '/ligen/archiv', '/ligen/Bezirk', '/ligen'] as $unter) {
            array_map('unlink', array_filter(glob($this->ordner . $unter . '/*') ?: [], 'is_file'));
            rmdir($this->ordner . $unter);
        }
        rmdir($this->ordner);
    }

    /** @param array<string, string> $parameter */
    private function seite(array $parameter): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($parameter + ['action' => 'admin', 'todo' => 'open']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'liga_sort' => 'file_name', 'liga_sort_direction' => 'asc', 'defdateformat' => 'd.m.Y']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => 1])
        );
        $controller = new OeffnenController(static fn (string $datei): bool => true);
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testLigenordner(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<h1>T294</h1>', $html);
        self::assertStringContainsString('/lmoadmin.php?todo=open&amp;file=bula.l98', $html);
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=open&amp;subdir=archiv/">archiv</a></td><td><small>&Auml;ltere Spielzeiten</small>', $html, 'Beschreibung wie bisher mit htmlentities');
        self::assertLessThan(strpos($html, '>Bezirk<') ?: 0, strpos($html, '>archiv<') ?: PHP_INT_MAX, 'natuerlich sortiert, ohne Gross- und Kleinschreibung');
        self::assertStringNotContainsString('T562', $html, 'kein Weg zurueck');
    }

    public function testUnterordner(): void
    {
        $html = $this->seite(['subdir' => '../archiv/']);

        self::assertStringContainsString('/lmoadmin.php?todo=open&amp;file=archiv/alt.l98', $html, 'ohne "../"; die Liga im Unterordner');
        self::assertStringContainsString('subdir=archiv/2020/">2020</a>', $html);
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=open&amp;subdir=./">T5 T562</a>', $html, 'zurueck wie bisher ueber dirname()');
    }
}
