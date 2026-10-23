<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Datenordner;
use PHPUnit\Framework\TestCase;

/**
 * Der Datenordner eines Addons: Umzug vom alten Ort, einmal und ganz oder gar nicht.
 */
class DatenordnerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-datenordner-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/addon/demo/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/addon/demo/tipps/auswert/a.aus', 'A');
        file_put_contents($this->ordner . '/addon/demo/tipper.txt', 'T');
        file_put_contents($this->ordner . '/addon/demo/addon.php', '<?php');
    }

    protected function tearDown(): void
    {
        $loesche = static function (string $pfad) use (&$loesche): void {
            foreach (array_diff(scandir($pfad) ?: [], ['.', '..']) as $eintrag) {
                is_dir($pfad . '/' . $eintrag) ? $loesche($pfad . '/' . $eintrag) : unlink($pfad . '/' . $eintrag);
            }
            rmdir($pfad);
        };
        $loesche($this->ordner);
    }

    public function testEintraegeZiehenUm(): void
    {
        $neu = $this->ordner . '/daten/demo';

        self::assertSame($neu, Datenordner::umziehen($neu, $this->ordner . '/addon/demo', ['tipps/', 'tipper.txt', 'fehlt.txt']));
        self::assertStringEqualsFile($neu . '/tipps/auswert/a.aus', 'A');
        self::assertStringEqualsFile($neu . '/tipper.txt', 'T');
        self::assertFileDoesNotExist($this->ordner . '/addon/demo/tipper.txt');
        self::assertFileExists($this->ordner . '/addon/demo/addon.php', 'nur die genannten Eintraege');

        file_put_contents($this->ordner . '/addon/demo/tipper.txt', 'neu am alten Ort');
        self::assertSame($neu, Datenordner::umziehen($neu, $this->ordner . '/addon/demo', ['tipper.txt']), 'nur einmal');
        self::assertStringEqualsFile($neu . '/tipper.txt', 'T');
    }

    public function testGanzerOrdner(): void
    {
        $neu = $this->ordner . '/daten/demo';

        self::assertSame($neu, Datenordner::umziehen($neu, $this->ordner . '/addon/demo/tipps'));
        self::assertStringEqualsFile($neu . '/auswert/a.aus', 'A');
        self::assertSame([], array_values(array_diff(scandir($this->ordner . '/addon/demo/tipps') ?: [], ['.', '..'])));
    }

    public function testOhneAlteDatenNeuerOrdner(): void
    {
        $neu = $this->ordner . '/daten/leer';

        self::assertSame($neu, Datenordner::umziehen($neu, $this->ordner . '/addon/leer'));
        self::assertDirectoryExists($neu);
    }

    public function testScheitertDerUmzugBleibtAllesAmAltenOrt(): void
    {
        $neu = $this->ordner . '/daten/demo';
        $alt = $this->ordner . '/addon/demo';
        // rename() scheitert: im neuen Ordner fehlt tipps/auswert/
        $umzug = static fn (): string => Datenordner::umziehen($neu, $alt, ['tipper.txt', 'tipps/auswert/a.aus']);

        self::assertSame($alt, $umzug());
        self::assertStringEqualsFile($alt . '/tipper.txt', 'T', 'zurueck verschoben');
        self::assertFileExists($alt . '/tipps/auswert/a.aus');
        self::assertDirectoryDoesNotExist($neu, 'der leere neue Ordner ist wieder weg');
    }
}
