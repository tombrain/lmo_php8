<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Punkte;
use PHPUnit\Framework\TestCase;

/**
 * Punkte fuer einen Tipp nach den Regeln des Tippspiels.
 */
class PunkteTest extends TestCase
{
    private const NV = 'n.V.';
    private const IE = 'i.E.';

    /** @param array<string, string> $abweichend */
    private static function regeln(array $abweichend = []): Punkte
    {
        return Punkte::ausKonfiguration($abweichend + [
            'tippmodus' => '1', 'entscheidungnv' => '0', 'entscheidungie' => '0', 'rergebnis' => '5', 'rtendenzdiff' => '3',
            'rtendenz' => '2', 'rtor' => '1', 'rtendenztor' => '0', 'rtendenzremis' => '0', 'rremis' => '0', 'gtpunkte' => '0',
        ]);
    }

    /**
     * @return array{0:int|float, 1:array<int, int|float>}
     */
    private static function punkte(Punkte $regeln, string $tipp, string $ergebnis, int $wertung = 0, string $zusatz = '', int|float $joker = 1, bool $nichtWerten = false): array
    {
        [$th, $tg] = array_map('intval', explode(':', $tipp));
        [$eh, $eg] = array_map('intval', explode(':', $ergebnis));

        return $regeln->fuer($th, $tg, $eh, $eg, $wertung, $zusatz, self::NV, self::IE, $joker, $nichtWerten);
    }

    public function testErgebnisTippmodus(): void
    {
        $regeln = self::regeln();

        self::assertSame([5, [1 => 1]], self::punkte($regeln, '2:1', '2:1'), 'richtiges Ergebnis');
        self::assertSame([3, [2 => 1]], self::punkte($regeln, '3:2', '2:1'), 'Tordifferenz');
        self::assertSame([3, [2 => 1]], self::punkte($regeln, '0:0', '1:1'), 'Remis: Tordifferenz');
        self::assertSame([2, [3 => 1]], self::punkte($regeln, '3:0', '2:1'), 'Tendenz');
        self::assertSame([1, [4 => 1]], self::punkte($regeln, '2:3', '2:1'), 'Toranzahl einer Mannschaft');
        self::assertSame([0, []], self::punkte($regeln, '0:3', '2:1'));
        self::assertSame([-2, []], self::punkte($regeln, '2:1', '2:1', nichtWerten: true), 'Spiel nicht werten');
    }

    public function testWeitereRegeln(): void
    {
        self::assertSame([2, [3 => 1]], self::punkte(self::regeln(['rtendenzremis' => '1']), '0:0', '1:1'), 'Remis als Tendenz');
        self::assertSame([3, [3 => 1, 4 => 1]], self::punkte(self::regeln(['rtendenztor' => '1']), '3:1', '2:1'), 'Tendenz und Toranzahl');
        self::assertSame([7, [1 => 1, 5 => 1]], self::punkte(self::regeln(['rremis' => '2']), '1:1', '1:1'), 'Bonus fuer ein richtiges Remis');
        self::assertSame([5, [3 => 1, 5 => 1]], self::punkte(self::regeln(['rremis' => '3', 'rtendenzremis' => '1']), '0:0', '1:1'));
        self::assertSame([10, [1 => 1, 6 => 5]], self::punkte(self::regeln(), '2:1', '2:1', joker: 2), 'Joker verdoppelt');
        self::assertSame([7.5, [1 => 1, 6 => 2.5]], self::punkte(self::regeln(), '2:1', '2:1', joker: 1.5));
        self::assertSame([0, []], self::punkte(self::regeln(), '0:3', '2:1', joker: 2), 'Joker ohne Punkte');
        self::assertSame([2.5, [3 => 1]], self::punkte(self::regeln(['rtendenz' => '2.5']), '3:0', '2:1'), 'Punkte mit Komma');
    }

    public function testVerlaengerungUndElfmeterschiessen(): void
    {
        $regeln = self::regeln(['entscheidungnv' => '1', 'entscheidungie' => '1']);

        self::assertSame([3, [2 => 1]], self::punkte($regeln, '1:1', '2:1', 0, self::NV), 'Remis getippt: nach Verlaengerung entschieden');
        self::assertSame([0, []], self::punkte($regeln, '2:1', '2:1', 0, self::NV), 'Sieger getippt zaehlt nicht');
        self::assertSame([2, [3 => 1]], self::punkte(self::regeln(['entscheidungie' => '1', 'rtendenzremis' => '1']), '0:0', '5:4', 0, self::IE));
        self::assertSame([5, [1 => 1]], self::punkte(self::regeln(), '2:1', '2:1', 0, self::NV), 'ohne die Regel normal gewertet');
    }

    public function testGruenerTisch(): void
    {
        self::assertSame([3, [2 => 1]], self::punkte(self::regeln(), '2:0', '0:0', 1), 'Sieg Heim, als Tordifferenz');
        self::assertSame([2, [3 => 1]], self::punkte(self::regeln(['gtpunkte' => '1']), '0:2', '0:0', 2), 'Sieg Gast, als Tendenz');
        self::assertSame([0, []], self::punkte(self::regeln(), '0:2', '0:0', 1));
        self::assertSame([3, [2 => 1]], self::punkte(self::regeln(), '1:1', '0:0', 3), 'beidseitiges Ergebnis');
        self::assertSame([-1, []], self::punkte(self::regeln(['gtpunkte' => '2']), '2:0', '0:0', 1), 'nicht werten');
        self::assertSame([-1, []], self::punkte(self::regeln(), '2:0', '0:0', 4), 'unbekannte Wertung');
    }

    public function testTendenzTippmodus(): void
    {
        $regeln = self::regeln(['tippmodus' => '0', 'entscheidungnv' => '1']);

        self::assertSame([1, []], self::punkte($regeln, '1:0', '3:1'));
        self::assertSame([1, []], self::punkte($regeln, '0:0', '2:2'));
        self::assertSame([0, []], self::punkte($regeln, '0:1', '3:1'));
        self::assertSame([1, []], self::punkte($regeln, '0:0', '3:2', 0, self::NV), 'Remis nach Verlaengerung');
        self::assertSame([1, []], self::punkte($regeln, '0:1', '0:0', 2), 'gruener Tisch fuer Gast');
        self::assertSame([2, [6 => 1]], self::punkte($regeln, '1:0', '3:1', joker: 2));
        self::assertSame([-1, []], self::punkte(self::regeln(['tippmodus' => '7']), '1:0', '1:0'), 'unbekannter Tippmodus');
    }
}
