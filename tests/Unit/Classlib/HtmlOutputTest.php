<?php

namespace Lmo\Tests\Unit\Classlib;

/** html_output.php: Logos suchen. Fixture: fixtures/img/teams/small/Alpha.png (2x1 Pixel) */
final class HtmlOutputTest extends ClasslibTestCase
{
    private const ALPHA = "<img src='http://lmo.test/img/teams/small/Alpha.png' width=\"2\" height=\"1\" ";

    public function testIconFound(): void
    {
        self::assertSame(self::ALPHA . '> ', HTML_icon('Alpha', 'teams'));
        self::assertSame(self::ALPHA . "alt='Alpha'> ", HTML_icon('Alpha', 'teams', 'small', "alt='Alpha'"));
    }

    /** @dataProvider aehnlicheNamen */
    public function testIconFoundForSimilarName(string $name): void
    {
        self::assertSame(self::ALPHA . '> ', HTML_icon($name, 'teams'));
    }

    public static function aehnlicheNamen(): array
    {
        return [
            'Sonderzeichen' => ['Al-pha'],
            'Schraegstrich' => ['Al/pha'],
            'Mannschaftsnummer' => ['Alpha 2'],
            'roemische Ziffer' => ['Alpha II'],
        ];
    }

    public function testIconNotFound(): void
    {
        self::assertSame('kein Logo', HTML_icon('Beta', 'teams', 'small', '', 'kein Logo'));
        self::assertSame('kein Logo', HTML_icon('Alpha', 'teams', 'big', '', 'kein Logo'));
    }

    public function testAlternativeTextDoesNotHideLaterImageTypes(): void
    {
        // Alpha gibt es nur als .png, CLASSLIB_IMG_TYPES prueft vorher .gif und .jpg
        self::assertSame(self::ALPHA . '> ', HTML_icon('Alpha', 'teams', 'small', '', 'kein Logo'));
    }

    public function testDeprecatedWrappers(): void
    {
        self::assertSame(self::ALPHA . '> ', HTML_smallTeamIcon('ligen/egal.l98', 'Alpha'));
        self::assertSame('-', HTML_bigTeamIcon('ligen/egal.l98', 'Alpha', '', '-'));
        self::assertSame('-', HTML_smallLigaIcon('ligen/Alpha.l98', '', '-'));
        self::assertSame('-', HTML_bigLigaIcon('ligen/Alpha.l98', '', '-'));
        self::assertSame('-', HTML_smallSpielerIcon('Alpha', '', '-'));
        self::assertSame('-', HTML_bigSpielerIcon('Alpha', '', '-'));
    }
}
