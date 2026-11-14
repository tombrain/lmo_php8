<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Start;
use PHPUnit\Framework\TestCase;

/**
 * Was init.php beim Start liest: Konfiguration, Zeitzone, Sprachen.
 */
class StartTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-start-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipp', 0777, true);
        mkdir($this->ordner . '/leer');
        mkdir($this->ordner . '/mini');
        file_put_contents($this->ordner . '/cfg.txt', "dirliga=ligen/\nfarbe=\"a;b\"\n");
        file_put_contents($this->ordner . '/tipp/cfg.txt', "dirtipp=tipps/\nwert=\"a;b\"\nohne=a;b\n");
        file_put_contents($this->ordner . '/mini/cfg.txt', "x=1\n");
        file_put_contents($this->ordner . '/nicht-ordner', 'x');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    public function testKonfiguration(): void
    {
        self::assertSame(['dirliga' => 'ligen/', 'farbe' => 'a;b'], Start::hauptkonfiguration($this->ordner . '/cfg.txt'));
        self::assertNull(Start::hauptkonfiguration($this->ordner . '/fehlt.txt'), 'ohne Datei');
        self::assertSame(['mini' => ['x' => '1'], 'tipp' => ['dirtipp' => 'tipps/', 'wert' => 'a;b', 'ohne' => 'a']], Start::addonKonfigurationen($this->ordner), 'alphabetisch, roh gelesen (ohne Anfuehrungszeichen endet der Wert am ;), nur Ordner mit cfg.txt');
    }

    public function testZeitzone(): void
    {
        self::assertSame('Europe/Vienna', Start::zeitzone('Europe/Vienna'));
        self::assertSame(date_default_timezone_get(), Start::zeitzone(''), 'ohne Einstellung die des Servers');
        self::assertSame(date_default_timezone_get(), Start::zeitzone(null));
    }

    public function testSprachen(): void
    {
        self::assertSame(['English', 'Deutsch', false], Start::sprachen('English', 'Deutsch'));
        self::assertSame(['Deutsch', null, false], Start::sprachen('Klingonisch', null), 'unbekannte Standardsprache: Deutsch');
        self::assertSame(['English', 'English', true], Start::sprachen('English', '../x'), 'unbekannte des Besuchers: die Standardsprache');
        self::assertSame(['Deutsch', 'Deutsch', true], Start::sprachen(null, ['x']));
        self::assertArrayHasKey('Українська', Start::SPRACHEN);
    }
}
