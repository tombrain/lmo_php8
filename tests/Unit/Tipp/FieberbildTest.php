<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Fieberbild;
use PHPUnit\Framework\TestCase;

/**
 * Das Bild der Tipp-Fieberkurve.
 */
class FieberbildTest extends TestCase
{
    /**
     * @param array<string, string> $werte
     * @param array<string, string|null> $farben
     */
    private static function bild(array $werte, array $farben = []): \GdImage
    {
        $bild = imagecreatefromstring(Fieberbild::png($werte, $farben));
        self::assertInstanceOf(\GdImage::class, $bild);

        return $bild;
    }

    private static function farbe(\GdImage $bild, int $x, int $y): string
    {
        $f = imagecolorsforindex($bild, imagecolorat($bild, $x, $y));

        return sprintf('#%02x%02x%02x', $f['red'], $f['green'], $f['blue']);
    }

    public function testGroesseUndHintergrund(): void
    {
        $bild = self::bild(['pganz' => '1', 'max' => '6', 'pgst' => '4', 'pgplatz1' => '1,6,,3,0', 'kmodus' => '1'], ['lmo_inner_background1' => '#ffe']);

        // 6 Zeilen zu 12 Pixel, 4 Spieltage zu 12 Pixel und die Raender
        self::assertSame([95, 131], [imagesx($bild), imagesy($bild)]);
        self::assertSame('#ffffee', self::farbe($bild, 0, 0), 'Hintergrund aus dem Design, #rgb');
    }

    public function testPlaetzeMitRangfarbenUndLinie(): void
    {
        $bild = self::bild(['pganz' => '1', 'pgteam1' => 'a', 'max' => '3', 'pgst' => '3', 'pgplatz1' => '1,1,1,0', 'kmodus' => '2'],
            ['lmo_tabelle_background1' => '#102030']);

        self::assertSame('#102030', self::farbe($bild, 32, 31), 'Platz 1 in der Farbe des Meisters');
        self::assertSame('#0000ff', self::farbe($bild, 48, 34), 'die Linie auf Platz 1');
    }

    public function testFarbeInAndererFormIstSchwarz(): void
    {
        $bild = self::bild(['max' => '2', 'pgst' => '1', 'kmodus' => '1'], ['lmo_inner_background1' => 'rot']);

        self::assertSame('#000000', self::farbe($bild, 0, 0), 'wie bis 4.5');
    }
}
