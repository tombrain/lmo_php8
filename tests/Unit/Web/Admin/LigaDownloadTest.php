<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\LigaDownload;
use Lmo\Web\Admin\Ligendateien;
use PHPUnit\Framework\TestCase;

/**
 * Ligen herunterladen: eine Liga als .l98, alle als Zip, Rechte.
 */
class LigaDownloadTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-ligadownload-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner . 'ligen', 0777, true);
        mkdir($this->ordner . 'output');
        foreach (['b.l98' => 'B', 'a.l98' => 'A', 'c.L98' => 'C', 'notiz.txt' => 'N'] as $datei => $inhalt) {
            file_put_contents($this->ordner . 'ligen/' . $datei, $inhalt);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /**
     * @param array<string, string> $anfrage
     * @return array{kopf: list<string>, inhalt: string}|null
     */
    private function antwort(array $anfrage, int $rolle = 2): ?array
    {
        return (new LigaDownload($this->ordner . 'ligen/', $this->ordner . 'output/', [314 => 'Fehler'], static fn (string $datei): bool => $datei !== 'b.l98' || $rolle === 2, 'IMG'))
            ->antwort($anfrage + ['action' => 'admin', 'todo' => 'download'], $rolle);
    }

    public function testLigendateien(): void
    {
        self::assertSame(['a.l98', 'b.l98', 'c.L98'], Ligendateien::in($this->ordner . 'ligen/'), 'sortiert, Endung ohne Ruecksicht auf Gross und klein');
    }

    public function testEineLiga(): void
    {
        self::assertSame(['kopf' => ['Content-Type: text/x-lmo4', 'Content-Disposition: attachment; filename="b.l98"'], 'inhalt' => 'B'], $this->antwort(['down' => '2']));
        self::assertNull($this->antwort(['down' => '2'], 1), 'fremde Liga des Hilfsadmins');
        self::assertNull($this->antwort(['down' => '9']), 'keine Liga mit der Nummer');
    }

    public function testAlleLigen(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }
        $antwort = $this->antwort(['down' => '-1'], 1);
        self::assertNotNull($antwort);
        self::assertSame(['Content-Type: application/zip', 'Content-Disposition: attachment; filename="ligen.zip"'], $antwort['kopf']);
        file_put_contents($this->ordner . 'x.zip', $antwort['inhalt']);
        $zip = new \ZipArchive();
        $zip->open($this->ordner . 'x.zip');
        $namen = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $namen[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        self::assertSame(['a.l98', 'c.L98'], $namen, 'der Hilfsadmin nur mit seinen Ligen');
        self::assertFileDoesNotExist($this->ordner . 'output/ligen.zip', 'das Zip ist wieder weg');
    }

    public function testNichts(): void
    {
        self::assertNull($this->antwort(['down' => '1'], 0), 'ohne Anmeldung');
        self::assertNull($this->antwort(['down' => '1', 'todo' => 'x']));
        self::assertNull($this->antwort(['down' => '0']));
        self::assertNull($this->antwort([]));
    }
}
