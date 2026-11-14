<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tippabgabe;
use Lmo\Addon\Tipp\Tipprunde;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use PHPUnit\Framework\TestCase;

/**
 * Die Tipps aus dem Formular der Tippabgabe.
 */
class TippabgabeTest extends TestCase
{
    /** Liga mit drei Paarungen; Spiel 2 hat schon angefangen (Anstoss 100). */
    private static function liga(): Tipprunde
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Teams=6', '[Round1]', 'TA1=1', 'TB1=2', 'AT1=5000', 'TA2=3', 'TB2=4', 'AT2=100', 'TA3=5', 'TB3=6', 'AT3=5000']));

        return Tipprunde::fuer($liga, 1);
    }

    private static function offen(): \Closure
    {
        return static fn (int $anstoss): bool => $anstoss != 100;
    }

    /** @param array<string, string> $formular */
    private static function abgabe(array $formular, int $tippmodus = 1, string $bisherJoker = ''): Tippabgabe
    {
        return Tippabgabe::aus(new Anfrage($formular), self::liga(), [['_', '_'], ['1', '1'], ['_', '_']], $bisherJoker, $tippmodus, true, self::offen());
    }

    public function testErgebnisse(): void
    {
        $abgabe = self::abgabe(['xtippa0' => ' 2 ', 'xtippb0' => '1', 'xtippa1' => '5', 'xtippb1' => '5', 'xtippa2' => '_', 'xtippb2' => '3', 'xjokerspiel' => '3']);

        self::assertSame([[2, 1], ['1', '1'], [-1, 3]], $abgabe->tipps, 'Spiel 2 laeuft schon: alter Tipp; leere Heimseite, gueltige Gastseite wie bis 4.5');
        self::assertSame('3', $abgabe->joker);
        self::assertFalse($abgabe->jokerGesperrt);
        self::assertSame([[-1, -1], ['1', '1'], [0, 0]], self::abgabe(['xtippa0' => '2', 'xtippb0' => '-1', 'xtippa2' => 'x', 'xtippb2' => 'y'])->tipps, 'ungueltige Gastseite: kein Tipp; Text wird 0');
    }

    public function testTendenz(): void
    {
        self::assertSame([['1', '0'], ['1', '1'], ['0', '0']], self::abgabe(['xtipp0' => '1', 'xtipp1' => '2', 'xtipp2' => '3'], 0)->tipps);
        self::assertSame([['0', '1'], ['1', '1'], ['-1', '-1']], self::abgabe(['xtipp0' => '2'], 0)->tipps);
    }

    public function testJokerAufLaufendemSpielBleibt(): void
    {
        $abgabe = self::abgabe(['xjokerspiel' => '1'], 1, '2');
        self::assertSame('2', $abgabe->joker);
        self::assertTrue($abgabe->jokerGesperrt);

        self::assertSame('3', self::abgabe(['xjokerspiel' => '3'], 1, '1')->joker, 'vom offenen Spiel weg geht');
        self::assertNull(Tippabgabe::aus(new Anfrage(['xjokerspiel' => '3']), self::liga(), [], '', 1, false, self::offen())->joker, 'ohne Jokertipp');
    }

    public function testPokal(): void
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Type=1', 'Teams=4', '[Round1]', 'MO=2', 'TA1=1', 'TB1=2', 'AT11=5000', 'AT12=100', 'TA2=3', 'TB2=4', 'AT21=5000', 'AT22=5000']));
        $runde = Tipprunde::fuer($liga, 1);
        $bisher = [[['_', '_'], ['0', '0']], [['_', '_'], ['_', '_']]];

        $abgabe = Tippabgabe::aus(new Anfrage(['xtippa00' => '1', 'xtippb00' => '0', 'xtippa01' => '9', 'xtippb01' => '9', 'xtippa11' => '2', 'xtippb11' => '2']), $runde, $bisher, '', 1, false, self::offen());

        self::assertSame([[[1, 0], ['0', '0']], [[-1, -1], [2, 2]]], $abgabe->tipps);
    }
}
