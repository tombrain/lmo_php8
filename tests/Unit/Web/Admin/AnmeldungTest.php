<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\Anmeldung;
use PHPUnit\Framework\TestCase;

/**
 * Anmeldung in der Administration: Kennwoerter, Umstellung auf den aktuellen Hash, Konto.
 */
class AnmeldungTest extends TestCase
{
    private string $datei;

    protected function setUp(): void
    {
        $this->datei = sys_get_temp_dir() . '/lmo-anmeldung-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($this->datei, "<?php exit(); ?>\nadmin|lmo|2|||\nhilfe|" . password_hash('geheim', PASSWORD_BCRYPT) . "|1|basic,bula|1\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->datei)) {
            unlink($this->datei);
        }
    }

    public function testKlartextWirdUmgestellt(): void
    {
        $anmeldung = new Anmeldung($this->datei);

        self::assertSame(['rang' => '2', 'ligen' => '', 'erweitert' => ''], $anmeldung->meldeAn('admin', 'lmo'));
        $zeilen = file($this->datei, FILE_IGNORE_NEW_LINES) ?: [];
        self::assertStringStartsNotWith('admin|lmo|', $zeilen[1], 'das Kennwort steht nicht mehr im Klartext da');
        self::assertTrue(password_verify('lmo', explode('|', $zeilen[1])[1]));
        self::assertNotNull($anmeldung->meldeAn('admin', 'lmo'), 'danach mit dem Hash');
        self::assertSame('<?php exit(); ?>', $zeilen[0], 'die Sperre bleibt');
    }

    public function testHilfsadmin(): void
    {
        self::assertSame(['rang' => '1', 'ligen' => 'basic,bula', 'erweitert' => '1'], (new Anmeldung($this->datei))->meldeAn('hilfe', 'geheim'));
    }

    public function testFalsch(): void
    {
        $anmeldung = new Anmeldung($this->datei);
        $vorher = file_get_contents($this->datei);

        self::assertNull($anmeldung->meldeAn('admin', 'falsch'));
        self::assertNull($anmeldung->meldeAn('niemand', 'lmo'));
        self::assertNull($anmeldung->meldeAn('', ''));
        self::assertSame($vorher, file_get_contents($this->datei), 'nichts umgestellt');
        self::assertNull((new Anmeldung($this->datei . '.fehlt'))->meldeAn('admin', 'lmo'), 'ohne Datei');
    }
}
