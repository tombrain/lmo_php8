<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tipptabelle;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use PHPUnit\Framework\TestCase;

/**
 * Die Tabelle einer Liga, wie sie mit den Tipps aussaehe.
 */
class TipptabelleTest extends TestCase
{
    /** 4 Mannschaften, 2 Spieltage: 1-2, 3-4 und 1-3, 2-4 */
    private static function liga(): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege([
            '[Options]', 'Name=Testliga', 'Teams=4', 'Rounds=2', 'Matches=2', 'Actual=2', 'PointsForWin=3', 'PointsForDraw=1', 'PointsForLost=0',
            '[Teams]', '1=Alpha', '2=Beta', '3=Gamma', '4=Delta',
            '[Round1]', 'TA1=1', 'TB1=2', 'TA2=3', 'TB2=4',
            '[Round2]', 'TA1=1', 'TB1=3', 'TA2=2', 'TB2=4',
        ]));
    }

    /** @return array<int, array<int, array{0: string, 1: string}>> */
    private static function tipps(): array
    {
        return [0 => [['2', '0'], ['1', '1']], 1 => [['0', '1'], ['3', '0']]];
    }

    private static function nie(): \Closure
    {
        return static fn (Spiel $spiel, int $j): bool => false;
    }

    public function testGesamt(): void
    {
        $tabelle = Tipptabelle::rechne(self::liga(), [self::tipps()], self::nie(), 0, 2);

        self::assertSame([3, 2, 1, 4], $tabelle->reihenfolge, 'Beta vor Alpha: gleiche Quote und Differenz, mehr Tore');
        self::assertSame(['spiele' => 2, 'siege' => 1, 'unent' => 1, 'nieder' => 0, 'punkte' => 4, 'negativ' => 0, 'etore' => 2, 'atore' => 1, 'dtore' => 1, 'quote' => 200.0], $tabelle->werte[3]);
        self::assertSame(150.0, $tabelle->werte[1]['quote']);
        self::assertSame(4, $tabelle->platz(4));
        self::assertSame(2, $tabelle->bis);
    }

    public function testHeimUndBisSpieltag(): void
    {
        self::assertSame([2, 1, 3, 4], Tipptabelle::rechne(self::liga(), [self::tipps()], self::nie(), 1, 2)->reihenfolge, 'nur Heimspiele');
        self::assertSame([1, 4, 3, 2], Tipptabelle::rechne(self::liga(), [self::tipps()], self::nie(), 0, 1)->reihenfolge, 'bis Spieltag 1; bei Gleichstand die hoehere Nummer vorn');
        self::assertSame(1, Tipptabelle::rechne(self::liga(), [self::tipps()], self::nie(), 4, 2)->bis, 'die Hinrunde endet nach der Haelfte');
    }

    public function testVerborgeneSpieleUndOhneTipp(): void
    {
        $ohneSpieltag2 = Tipptabelle::rechne(self::liga(), [self::tipps()], static fn (Spiel $spiel, int $j): bool => $j == 1, 0, 2);
        self::assertSame([1, 4, 3, 2], $ohneSpieltag2->reihenfolge);

        $tipps = self::tipps();
        $tipps[1][0] = ['_', '1'];
        self::assertSame(1, Tipptabelle::rechne(self::liga(), [$tipps], self::nie(), 0, 2)->werte[1]['spiele'], 'ein halber Tipp zaehlt nicht');
    }

    public function testMehrereTipper(): void
    {
        $tabelle = Tipptabelle::rechne(self::liga(), [self::tipps(), self::tipps()], self::nie(), 0, 2);

        self::assertSame(4, $tabelle->werte[3]['spiele']);
        self::assertSame(8, $tabelle->werte[3]['punkte']);
        self::assertSame(200.0, $tabelle->werte[3]['quote']);
    }
}
