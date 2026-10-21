<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Konfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Die Konfiguration aendern und schreiben: Hauptdatei und je Addon eine cfg.txt.
 */
class KonfigurationTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-konfiguration-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipp', 0777, true);
        file_put_contents($this->ordner . '/cfg.txt', "deflang=Deutsch\ntimezone=Europe/Berlin\n");
        file_put_contents($this->ordner . '/tipp/cfg.txt', "rangliste=1\nmail=\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', [$this->ordner . '/tipp/cfg.txt', $this->ordner . '/cfg.txt']);
        rmdir($this->ordner . '/tipp');
        rmdir($this->ordner);
    }

    public function testMit(): void
    {
        $konfiguration = new Konfiguration(['deflang' => 'Deutsch', 'tipp' => ['rangliste' => '1', 'mail' => '']]);
        $neu = $konfiguration->mit(['deflang' => 'English', 'tipp' => ['mail' => 'a@b.de']]);

        self::assertSame(['deflang' => 'English', 'tipp' => ['rangliste' => '1', 'mail' => 'a@b.de']], $neu->alle());
        self::assertSame(['rangliste' => '1', 'mail' => 'a@b.de'], $neu->addon('tipp'), 'die Reihenfolge der Schluessel bleibt');
        self::assertSame('Deutsch', $konfiguration->wert('deflang'), 'das Original bleibt');
    }

    public function testSpeichereUnveraendert(): void
    {
        $vorher = [file_get_contents($this->ordner . '/cfg.txt'), file_get_contents($this->ordner . '/tipp/cfg.txt')];

        self::assertTrue(Konfiguration::lies($this->ordner)->speichere($this->ordner));
        self::assertSame($vorher, [file_get_contents($this->ordner . '/cfg.txt'), file_get_contents($this->ordner . '/tipp/cfg.txt')]);
    }

    public function testSpeichereGeaendert(): void
    {
        $konfiguration = Konfiguration::lies($this->ordner)->mit(['deflang' => 'English', 'timezone' => '', 'tipp' => ['mail' => 'a@b.de']]);

        self::assertTrue($konfiguration->speichere($this->ordner));
        self::assertStringEqualsFile($this->ordner . '/cfg.txt', "deflang=English\ntimezone=" . date_default_timezone_get() . "\n", 'leere Zeitzone: die eingestellte');
        self::assertStringEqualsFile($this->ordner . '/tipp/cfg.txt', "rangliste=1\nmail=a@b.de\n");
    }

    public function testSpeichereFehler(): void
    {
        self::assertFalse((new Konfiguration(['gibtsnicht' => ['a' => '1']]))->speichere($this->ordner), 'Addon ohne Ordner');
    }
}
