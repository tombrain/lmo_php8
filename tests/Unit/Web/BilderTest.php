<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Bilder;
use PHPUnit\Framework\TestCase;

/**
 * Bilder zu einem Namen, gesucht wie bisher mit HTML_icon() der Classlib.
 */
class BilderTest extends TestCase
{
    /** ein GIF mit 3 x 2 Bildpunkten */
    private const GIF = "GIF89a\x03\x00\x02\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x03\x00\x02\x00\x00\x02\x02\x84\x51\x00\x3b";

    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-bilder-' . uniqid();
        foreach (['teams/small', 'teams/big', 'liga/small'] as $unter) {
            mkdir($this->ordner . '/' . $unter, 0777, true);
        }
        file_put_contents($this->ordner . '/teams/small/FC Alpha.gif', self::GIF);
        file_put_contents($this->ordner . '/teams/small/Beta.png', self::GIF);
        file_put_contents($this->ordner . '/teams/small/Gamma.gif', self::GIF);
        file_put_contents($this->ordner . '/liga/small/bula.jpg', self::GIF);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    public function testBildMitGroesse(): void
    {
        $bilder = new Bilder($this->ordner, '/img');

        self::assertSame("<img src='/img/teams/small/FC%20Alpha.gif' width=\"3\" height=\"2\" alt=\"\"> ", $bilder->bild('FC Alpha', 'teams', 'small', 'alt=""'));
        self::assertSame("<img src='/img/teams/small/Beta.png' width=\"3\" height=\"2\" > ", $bilder->bild('Beta', 'teams'), 'die Endungen der Reihe nach');
    }

    public function testNameVereinfacht(): void
    {
        $bilder = new Bilder($this->ordner, '/img');

        self::assertStringContainsString("/teams/small/Beta.png'", $bilder->bild('Be/ta', 'teams'), 'ohne Schraegstrich');
        self::assertStringContainsString("/teams/small/Gamma.gif'", $bilder->bild('Gam-ma!', 'teams'), 'nur Buchstaben und Ziffern');
        self::assertStringContainsString("/teams/small/Gamma.gif'", $bilder->bild('Gamma II', 'teams'), 'ohne Zusatz am Ende');
    }

    public function testOhneBild(): void
    {
        $bilder = new Bilder($this->ordner, '/img');

        self::assertSame('Ersatz', $bilder->bild('Delta', 'teams', 'small', '', 'Ersatz'));
        self::assertSame('', $bilder->bild('Delta', 'teams'));
        self::assertSame('Delta', $bilder->bild('Delta', 'teams', 'small', " alt='Delta'"), 'der Text aus den Attributen wie bisher');
        self::assertSame('', $bilder->bild('FC Alpha', 'teams', 'big'), 'andere Groesse');
    }

    public function testLigaSymbol(): void
    {
        $bilder = new Bilder($this->ordner, '/img');

        self::assertStringContainsString("/liga/small/bula.jpg'", $bilder->ligaSymbol('unter/bula.l98'));
        self::assertSame('', $bilder->ligaSymbol('ohnepunkt'), 'ohne Endung kein Name');
    }
}
