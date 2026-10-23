<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Autoloader;
use PHPUnit\Framework\TestCase;

/**
 * Klassen der Addons aus ihrem Ordner: Lmo\Addon\<Addon>\<Klasse> in addon/<addon>/src/<Klasse>.php.
 */
class AutoloaderTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-autoload-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/brain/src/Teil', 0777, true);
        file_put_contents($this->ordner . '/brain/src/Brain.php', '<?php namespace Lmo\Addon\Brain; final class Brain { public const OK = "geladen"; }');
        file_put_contents($this->ordner . '/brain/src/Teil/Unten.php', '<?php');
    }

    protected function tearDown(): void
    {
        unlink($this->ordner . '/brain/src/Teil/Unten.php');
        unlink($this->ordner . '/brain/src/Brain.php');
        rmdir($this->ordner . '/brain/src/Teil');
        rmdir($this->ordner . '/brain/src');
        rmdir($this->ordner . '/brain');
        rmdir($this->ordner);
    }

    public function testDateiEinerKlasse(): void
    {
        self::assertSame($this->ordner . '/brain/src/Brain.php', Autoloader::datei($this->ordner, 'Lmo\Addon\Brain\Brain'), 'Ordnername klein');
        self::assertSame($this->ordner . '/brain/src/Teil/Unten.php', Autoloader::datei($this->ordner, 'Lmo\Addon\Brain\Teil\Unten'), 'Unternamensraum als Unterordner');
        self::assertNull(Autoloader::datei($this->ordner, 'Lmo\Addon\Brain\Fehlt'));
        self::assertNull(Autoloader::datei($this->ordner, 'Lmo\Addon\Addons'), 'Klasse des Kerns');
        self::assertNull(Autoloader::datei($this->ordner, 'Lmo\Web\Brain\Brain'), 'anderer Namensraum');
    }

    public function testLaedtDieKlasse(): void
    {
        Autoloader::registriere($this->ordner);
        Autoloader::registriere($this->ordner);

        self::assertSame('geladen', constant('Lmo\Addon\Brain\Brain::OK'));
    }

    public function testMitgeliefertesAddon(): void
    {
        self::assertTrue(class_exists(\Lmo\Addon\Ticker\Ticker::class), 'tests/bootstrap.php registriert addon/');
    }
}
