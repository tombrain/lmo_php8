<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Einsichtsdatei;
use PHPUnit\Framework\TestCase;

/**
 * Die Tipp-Einsicht eines Spieltags in einsicht/<liga>_<st>.ein.
 */
class EinsichtsdateiTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-einsichtsdatei-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner . 'einsicht', 0777, true);
        file_put_contents($this->ordner . 'liga_anna.tip', "[Round1]\n@2@\nGA1=2\nGB1=1\nNT1=x\n[Round2]\nGA1=0\nGB1=0\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    private function einsicht(int $st): string
    {
        return (string) file_get_contents($this->ordner . 'einsicht/liga_' . $st . '.ein');
    }

    public function testTippsMitJoker(): void
    {
        self::assertTrue((new Einsichtsdatei($this->ordner, true))->erstelle('liga', 1, 1, 9));
        self::assertSame("\n[anna]\n@2@\nGA1=2\nGB1=1\n", $this->einsicht(1), 'nur Tore');

        (new Einsichtsdatei($this->ordner, true))->erstelle('liga', 2, 1, 9);
        self::assertSame("\n[anna]\n@2@\nGA1=0\nGB1=0\n", $this->einsicht(2), 'der Joker gilt bis zur naechsten @-Zeile (wie bis 4.5)');

        (new Einsichtsdatei($this->ordner, false))->erstelle('liga', 2, 1, 9);
        self::assertSame("\n[anna]\nGA1=0\nGB1=0\n", $this->einsicht(2), 'ohne Jokertipp');
    }

    public function testVonBis(): void
    {
        file_put_contents($this->ordner . 'einsicht/liga_1.ein', "\n[weg]\nGA1=5\n\n[anna]\n@1@\nGA1=7\nGB1=7\n");

        (new Einsichtsdatei($this->ordner, true))->erstelle('liga', 1, 2, 2);
        self::assertSame("[anna]\n@1@\nGA1=7\nGB1=7\n", $this->einsicht(1), 'anna wie sie war, weg hat keine Tippdatei mehr');
    }
}
