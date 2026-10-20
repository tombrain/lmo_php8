<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Fieberkurve;
use PHPUnit\Framework\TestCase;

/**
 * Die Werte der Fieberkurve: Punkte und Platz je Spieltag.
 */
class FieberkurveTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-fieber-' . bin2hex(random_bytes(4)) . '.aus';
        file_put_contents($this->pfad, "[anna]\nSG1=3\nTP1=5\nSG2=3\nTP2=2\n\n[bert]\nSG1=3\nTP1=5\nSG2=2\nTP2=4\n\n[cora]\nSG1=2\nTP1=1\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    public function testPunkte(): void
    {
        $kurve = Fieberkurve::lies($this->pfad, 3, false, [0, 1, 2], false, false);

        self::assertNotNull($kurve);
        self::assertSame(['anna', 'bert', 'cora'], $kurve->nicks);
        self::assertSame(['5', '2', '', ''], $kurve->punkte[0], 'wie in der Datei, ohne Eintrag leer, einer mehr als Spieltage');
        self::assertSame([], $kurve->plaetze);
        self::assertNull(Fieberkurve::lies($this->pfad . '.fehlt', 3, false, [0, 1, 2], false, false));
    }

    public function testPlaetze(): void
    {
        $kurve = Fieberkurve::lies($this->pfad, 3, true, [-1, -1, -1], false, false);

        self::assertNotNull($kurve);
        self::assertSame([1, 2, '', ''], $kurve->plaetze[0], 'Spieltag 1 punktgleich mit bert: beide Erster');
        self::assertSame([1, 1, '', ''], $kurve->plaetze[1]);
        self::assertSame([3, '', '', ''], $kurve->plaetze[2], 'ohne getippte Spiele kein Platz');
    }

    public function testKriterienUndSpieltagssiege(): void
    {
        // bei gleichen Punkten entscheiden weniger getippte Spiele (krit 0)
        file_put_contents($this->pfad, "[anna]\nSG1=3\nTP1=5\n\n[bert]\nSG1=2\nTP1=5\n");
        self::assertSame([2, ''], array_slice(Fieberkurve::lies($this->pfad, 1, true, [0, -1, -1], false, false)?->plaetze[0] ?? [], 0, 2));

        // mit Spieltagssiegen als Kriterium (6) teilen sich beide den Sieg und bleiben gleich
        $siege = Fieberkurve::lies($this->pfad, 1, true, [6, -1, -1], false, true);
        self::assertNotNull($siege);
        self::assertSame(1, $siege->plaetze[0][0]);
        self::assertSame(1, $siege->plaetze[1][0]);
    }
}
