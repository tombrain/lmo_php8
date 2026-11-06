<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Vereinswertung;
use PHPUnit\Framework\TestCase;

/**
 * Die Tipp-Punkte je Mannschaft aus den Auswertungen der Tipper.
 */
class VereinswertungTest extends TestCase
{
    /** @var list<string> */
    private array $pfade = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->pfade);
    }

    private function datei(string $inhalt): string
    {
        $pfad = sys_get_temp_dir() . '/lmo-verein-' . bin2hex(random_bytes(4)) . '.ver';
        file_put_contents($pfad, $inhalt);
        $this->pfade[] = $pfad;

        return $pfad;
    }

    public function testBisSpieltag(): void
    {
        $pfad = $this->datei("[1]\nSG1=1\nTP1=2\nSG2=1\nTP2=0\n[2]\nSG1=2\nTP1=1\n[3]\n");

        $erster = Vereinswertung::lies([$pfad], 1, 2, true);
        self::assertSame([1, 2, 3], $erster->reihenfolge);
        self::assertSame(['spiele' => 1, 'punkte' => 2], $erster->werte[1]);
        self::assertSame(['spiele' => 0, 'punkte' => 0], $erster->werte[3], 'ohne Tipps');

        $alle = Vereinswertung::lies([$pfad], 0, 2, true);
        self::assertSame(['spiele' => 2, 'punkte' => 2], $alle->werte[1], 'unter 1: alle Spieltage');
        self::assertSame(1, $alle->platz(1));
        self::assertSame(0, $alle->platz(9));
    }

    public function testMehrereTipperUndQuote(): void
    {
        $wertung = Vereinswertung::lies([$this->datei("[1]\nSG1=1\nTP1=1\n[2]\nSG1=1\nTP1=0\n"), $this->datei("[1]\nSG1=1\nTP1=0\n[2]\nSG1=1\nTP1=2\n")], 1, 1, true);

        self::assertSame([2, 1], $wertung->reihenfolge);
        self::assertSame(['spiele' => 2, 'punkte' => 2], $wertung->werte[2]);
        self::assertSame('1.00', Vereinswertung::quote(2, 2, true));
        self::assertSame('100.00', Vereinswertung::quote(2, 2, false), 'Tendenz-Modus in Prozent');
        self::assertSame('0', Vereinswertung::quote(0, 0, true));
    }
}
