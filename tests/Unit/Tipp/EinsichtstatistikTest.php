<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Einsichtstatistik;
use PHPUnit\Framework\TestCase;

/**
 * Was alle Tipper fuer einen Spieltag getippt haben.
 */
class EinsichtstatistikTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-einsicht-' . bin2hex(random_bytes(4)) . '.ein';
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    public function testLiga(): void
    {
        file_put_contents($this->pfad, "\n[anna]\n@1@\nGA1=2\nGB1=1\nGA2=-1\nGB2=-1\n\n[bert]\nGA1=1\nGB1=1\nGA2=0\nGB2=3\n\n[carla]\nGA1=0\nGB1=2\n");
        $statistik = Einsichtstatistik::lies($this->pfad, false);

        self::assertNotNull($statistik);
        self::assertSame(['t1' => 1, 't0' => 1, 't2' => 1, 'a' => 3, 'b' => 4, 'anz' => 3], $statistik->spiel(0));
        self::assertSame(['t1' => 0, 't0' => 0, 't2' => 1, 'a' => 0, 'b' => 3, 'anz' => 1], $statistik->spiel(1), 'kein Tipp zaehlt nicht');
        self::assertSame(['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0], $statistik->spiel(5));
        self::assertNull(Einsichtstatistik::lies($this->pfad . '.fehlt', false));
    }

    public function testPokal(): void
    {
        file_put_contents($this->pfad, "[anna]\nGA11=1\nGB11=0\nGA12=2\nGB12=2\n");
        $statistik = Einsichtstatistik::lies($this->pfad, true);

        self::assertNotNull($statistik);
        self::assertSame(1, $statistik->spiel(0, 0)['t1']);
        self::assertSame(1, $statistik->spiel(0, 1)['t0']);
    }
}
