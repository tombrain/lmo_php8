<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Closure;
use Lmo\Web\Admin\HochladenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Eine Liga hochladen: das Formular, die Datei im Ligenordner, ein freier Name und falsche Dateien.
 */
class HochladenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminhochladen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    private function kontext(int $rolle = 2): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'upload']),
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

    /** @param array{tmp: string, name: string}|null $datei */
    private static function controller(?array $datei, ?Closure $verschiebe = null): HochladenController
    {
        return new HochladenController(static fn (): ?array => $datei, $verschiebe ?? static fn (string $von, string $nach): bool => copy($von, $nach));
    }

    public function testFormular(): void
    {
        self::assertFalse(self::controller(null)->zustaendig($this->kontext(1)), 'nur fuer den Hauptadmin');
        $html = self::controller(null)->zeige($this->kontext())->html;

        self::assertStringContainsString('<h1>T299</h1>', $html);
        self::assertStringContainsString('<form enctype="multipart/form-data" action="/lmoadmin.php" method="post">', $html);
        self::assertStringContainsString('<input type="file" name="userfile">', $html);
    }

    public function testHochladen(): void
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'lmo');
        file_put_contents($tmp, "[Options]\nName=Neu\n");
        file_put_contents($this->ordner . '/ligen/neu.l98', 'schon da');

        $html = self::controller(['tmp' => $tmp, 'name' => 'neu.l98'])->zeige($this->kontext())->html;

        self::assertStringStartsWith(Meldung::html('IMG', 'T303:<br>ligen/1_neu.l98'), $html, 'freier Name');
        self::assertStringEqualsFile($this->ordner . '/ligen/1_neu.l98', "[Options]\nName=Neu\n");
        self::assertStringEqualsFile($this->ordner . '/ligen/neu.l98', 'schon da');
        self::assertStringContainsString('<input type="file" name="userfile">', $html, 'danach wieder das Formular');
        unlink($tmp);
    }

    public function testFalscheDatei(): void
    {
        $falsch = self::controller(['tmp' => '/gibtsnicht', 'name' => 'liga.txt'])->zeige($this->kontext())->html;
        self::assertSame(Meldung::html('IMG', 'T304', true), $falsch, 'nur die Meldung');

        $nichtVerschoben = self::controller(['tmp' => '/gibtsnicht', 'name' => 'liga.l98'], static fn (string $von, string $nach): bool => false)->zeige($this->kontext())->html;
        self::assertStringStartsWith(Meldung::html('IMG', 'T304', true), $nichtVerschoben);
        self::assertStringContainsString('<input type="file"', $nichtVerschoben);
    }
}
