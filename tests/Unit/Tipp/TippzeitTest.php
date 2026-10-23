<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tippzeit;
use PHPUnit\Framework\TestCase;

/**
 * Bis wann ein Spiel getippt werden kann.
 */
class TippzeitTest extends TestCase
{
    private int $jetzt;

    protected function setUp(): void
    {
        $this->jetzt = (int) mktime(12, 0, 0, 3, 15, 2025);
    }

    public function testBisZumAnstossAbzueglichTippBis(): void
    {
        $zeit = new Tippzeit(30, false, '', -1);
        $anstoss = $this->jetzt + 3600;

        self::assertTrue($zeit->aenderbar($anstoss, '', '', 5, 1, 0, $this->jetzt));
        self::assertFalse($zeit->aenderbar($this->jetzt + 30 * 60, '', '', 5, 1, 0, $this->jetzt), 'genau tippBis vor dem Anstoss ist zu spaet');
        self::assertTrue($zeit->aenderbar($this->jetzt + 31 * 60, '', '', 5, 1, 0, $this->jetzt));
        self::assertFalse($zeit->aenderbar($this->jetzt + 31 * 60, '', '', 5, 1, 1, $this->jetzt), 'eine Minute Sicherheitsabstand beim Speichern');
        self::assertSame($anstoss - 1800, $zeit->bis($anstoss, '', ''));
    }

    public function testOhneAnstossDerTagDesSpieltags(): void
    {
        $mitternacht = new Tippzeit(0, false, '15:30', -1);
        $mitUhrzeit = new Tippzeit(0, true, '15:30', -1);

        self::assertSame((int) mktime(0, 0, 0, 3, 16, 2025), $mitternacht->bis(0, '16.03.2025', '17.03.2025'), 'erster Tag, 0 Uhr');
        self::assertSame((int) mktime(15, 30, 0, 3, 16, 2025), $mitUhrzeit->bis(0, '16.03.2025', '17.03.2025'), 'Uhrzeit fuer Spiele ohne Anstoss');
        self::assertSame((int) mktime(15, 30, 0, 3, 17, 2025), $mitUhrzeit->bis(0, '', '17.03.2025'), 'ohne ersten den letzten Tag');
        self::assertNull($mitUhrzeit->bis(0, '', ''));
        self::assertSame((int) mktime(0, 0, 0, 3, 16, 2025), (new Tippzeit(0, true, '', -1))->bis(0, '16.03.2025', ''), 'ohne Uhrzeit 0 Uhr');

        self::assertTrue($mitUhrzeit->aenderbar(0, '15.03.2025', '', 5, 1, 0, $this->jetzt), 'heute um 15:30 noch offen');
        self::assertFalse($mitternacht->aenderbar(0, '15.03.2025', '', 5, 1, 0, $this->jetzt), 'heute um 0 Uhr schon vorbei');
        self::assertFalse($mitternacht->aenderbar(0, '', '', 5, 1, 0, $this->jetzt), 'ohne Termin nicht tippbar');
    }

    public function testSpieltageImVoraus(): void
    {
        $zeit = new Tippzeit(0, false, '', 2);
        $anstoss = $this->jetzt + 86400;

        self::assertTrue($zeit->aenderbar($anstoss, '', '', 7, 5, 0, $this->jetzt), 'bis zwei Spieltage nach dem aktuellen');
        self::assertFalse($zeit->aenderbar($anstoss, '', '', 8, 5, 0, $this->jetzt));
        self::assertTrue($zeit->aenderbar($anstoss, '', '', '7', 5, 0, $this->jetzt), 'Spieltag aus der Anfrage');
        self::assertTrue($zeit->aenderbar($anstoss, '', '', null, 5, 0, $this->jetzt), 'ohne Spieltag');
        self::assertTrue((new Tippzeit(0, false, '', -1))->aenderbar($anstoss, '', '', 30, 5, 0, $this->jetzt), '-1: alle Spieltage');
    }
}
