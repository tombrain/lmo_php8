<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use DivisionByZeroError;
use Lmo\Addon\Spieler\Formel;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Formeln der Spielerstatistik ohne eval(): dieselben Ergebnisse wie bisher.
 */
class SpielerFormelTest extends TestCase
{
    private const SPALTEN = [0 => 'Name', 1 => 'Tore', 2 => 'Spiele', 3 => 'Tor', 4 => 'Quote'];
    private const ZEILE = [0 => 'Müller', 1 => '6', 2 => '4', 3 => '2', 4 => '0'];

    private static function rechne(string $formel): float
    {
        $rechnung = Formel::aus($formel, self::SPALTEN, 4);
        self::assertNotNull($rechnung, $formel);

        return $rechnung->rechne(self::ZEILE);
    }

    public function testRechnen(): void
    {
        self::assertSame(1.5, self::rechne('Tore/Spiele'));
        self::assertSame(1.5, self::rechne('tore / spiele'), 'Gross- und Kleinschreibung egal, Leerzeichen erlaubt');
        self::assertSame(14.0, self::rechne('Tore+Spiele*2'), 'Punkt vor Strich');
        self::assertSame(20.0, self::rechne('(Tore+Spiele)*2'));
        self::assertSame(-2.0, self::rechne('-Tor'));
        self::assertSame(4.0, self::rechne('Tore - -Tor + -4'));
        self::assertSame(0.67, self::rechne('Tor/3'), 'auf zwei Stellen');
        self::assertSame(2.5, self::rechne('5./2'));
        self::assertSame(0.25, self::rechne('.5/2'));
        self::assertSame(2.0, self::rechne('Tor'), 'Tor ist nicht der Anfang von Tore: laengste Namen zuerst');
    }

    public function testMaxUndMin(): void
    {
        self::assertSame(6.0, self::rechne('MAX(Tore,Spiele,Tor)'));
        self::assertSame(4.0, self::rechne('min(Tore, Spiele)*1'));
        $this->expectException(UnexpectedValueException::class);
        self::rechne('MAX(Tore)');
    }

    public function testUngueltigeFormel(): void
    {
        self::assertNull(Formel::aus("file_put_contents('x', 'y')", self::SPALTEN, 4), 'anderer Text');
        self::assertNull(Formel::aus('MAX(Tore,MIN(Spiele,Tor))', self::SPALTEN, 4), 'MAX und MIN zusammen wie bisher nicht');
        self::assertNull(Formel::aus('Quote*2', self::SPALTEN, 4), 'die eigene Spalte zaehlt nicht');
    }

    public function testDivisionDurchNull(): void
    {
        $this->expectException(DivisionByZeroError::class);
        self::rechne('Tore/0');
    }

    /** @return array<string, array{string}> */
    public static function unvollstaendig(): array
    {
        return ['offen' => ['Tore/'], 'Klammer' => ['(Tore'], 'leer' => [''], 'Komma' => ['Tore,2'], 'Name' => ['Name*2'], 'zwei Zahlen' => ['2 3']];
    }

    /** @dataProvider unvollstaendig */
    public function testUnvollstaendigOderKeineZahl(string $formel): void
    {
        $this->expectException(UnexpectedValueException::class);
        self::rechne($formel);
    }
}
