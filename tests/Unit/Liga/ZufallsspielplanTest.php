<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Liga;

use Lmo\Liga\Zufallsspielplan;
use PHPUnit\Framework\TestCase;

/**
 * Der ausgeloste Spielplan: jeder gegen jeden in Hin- und Rueckrunde, bei ungerader Zahl spielfrei.
 */
class ZufallsspielplanTest extends TestCase
{
    public function testOhneMischenFesteReihenfolge(): void
    {
        // der Zufall waehlt immer die eigene Stelle: die Plaetze bleiben die Mannschaften 1 bis 4
        $plan = Zufallsspielplan::paarungen(4, static fn (int $von, int $bis): int => $bis);

        // Spieltag 1: der feste Platz (4) daheim gegen Platz 0 (1), die beweglichen 3 gegen 2
        self::assertSame([[3, 2], [4, 1]], $plan[0]);
        // Spieltag 2: der feste Platz auswaerts, die anderen einen Platz weiter
        self::assertSame([[1, 3], [2, 4]], $plan[1]);
        self::assertSame([[1, 4], [2, 3]], $plan[3], 'die Rueckrunde mit getauschtem Heimrecht');
    }

    /** @return list<array{int}> */
    public static function groessen(): array
    {
        return [[4], [6], [7], [18]];
    }

    /** @dataProvider groessen */
    public function testJederGegenJedenEinmalDaheim(int $teams): void
    {
        $plan = Zufallsspielplan::paarungen($teams);
        $spieler = $teams + $teams % 2;

        self::assertCount(($spieler - 1) * 2, $plan, 'Hin- und Rueckrunde');
        $paare = [];
        foreach ($plan as $spieltag) {
            $heute = [];
            foreach ($spieltag as [$heim, $gast]) {
                self::assertNotSame($heim, $gast);
                self::assertGreaterThanOrEqual(1, min($heim, $gast));
                self::assertLessThanOrEqual($teams, max($heim, $gast), 'der Platzhalter spielt nicht mit');
                $heute[] = $heim;
                $heute[] = $gast;
                $paare[] = $heim . '-' . $gast;
            }
            self::assertSame(count($heute), count(array_unique($heute)), 'jede Mannschaft hoechstens einmal am Spieltag');
            self::assertCount(intdiv($teams, 2), $spieltag);
        }
        self::assertCount($teams * ($teams - 1), array_unique($paare), 'jede Paarung genau einmal je Heimrecht');
    }
}
