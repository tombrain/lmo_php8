<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AnstehendesSpiel;
use Lmo\Addon\Tipp\Tippuebersicht;
use Lmo\Addon\Tipp\Tippzeit;
use Lmo\Addon\Tipp\UebersichtSpiel;
use Lmo\Liga\Rohdaten\Liga;
use PHPUnit\Framework\TestCase;

/**
 * Die Tippuebersicht: Spiele mehrerer Ligen sammeln (je Spiel ein AnstehendesSpiel mit den Werten
 * seiner Liga) und die Tipps in Tippdatei und Einsicht zurueckschreiben.
 */
class TippuebersichtTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-uebersicht-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    /** @return list<UebersichtSpiel> */
    private static function sammle(bool $jokertipp = false): array
    {
        return Tippuebersicht::sammle(
            Liga::lies(__DIR__ . '/fixtures/tippliga.l98'),
            'tippliga',
            __DIR__ . '/fixtures/tippliga_tester.tip',
            1,
            PHP_INT_MAX,
            false,
            '15:30',
            $jokertipp,
            new Tippzeit(0, false, '15:30', -1),
            array_fill(0, 600, '')
        );
    }

    public function testSammeltSpieleUndTipps(): void
    {
        $spiele = self::sammle();

        self::assertCount(2, $spiele);
        self::assertSame([['tippliga', '1', '1'], ['tippliga', '1', '2']], array_map(static fn (UebersichtSpiel $s): array => [$s->liga, $s->spieltag, $s->nr], $spiele));
        [$erstes, $zweites] = array_map(static fn (UebersichtSpiel $s): AnstehendesSpiel => $s->spiel, $spiele);
        // was die Anzeige von der Liga wissen muss, traegt jedes Spiel
        self::assertSame(['Tippliga', '0', 2], [$erstes->titel, $erstes->typ, $erstes->runden]);
        self::assertSame([false, true, false, true, 1], [$erstes->ohneUnentschieden, $erstes->mitSpieltagsdatum, $erstes->mitAnstosszeit, $erstes->mitSpielbericht, $erstes->torfaktor]);
        self::assertFalse($erstes->mitZusatz, 'die Liga nennt Spez nicht');
        self::assertSame(['01.03.2025', '02.03.2025', 1], [$zweites->ersterTag, $zweites->letzterTag, $zweites->spieleJePaarung]);
        // ein "|" im Namen oder in der Notiz gehoert zum Wert
        self::assertSame(['Alpha', '<strong>B | C</strong>'], [$erstes->heim, $erstes->gast], 'Lieblingsmannschaft ist hervorgehoben');
        self::assertSame(['Gamma', 'Alpha'], [$zweites->heim, $zweites->gast]);
        // jedes Spiel hat seine eigene Notiz
        self::assertSame(['Abbruch | Nebel', ''], [$erstes->notiz, $zweites->notiz]);
        self::assertSame(['&nbsp;', '&nbsp;'], [$erstes->zusatz, $zweites->zusatz]);
        self::assertSame(['2', '1', 0], [$erstes->toreHeim, $erstes->toreGast, $erstes->wertung]);
        self::assertSame(['_', '_'], [$zweites->toreHeim, $zweites->toreGast]);
        self::assertSame(['1740826800', '1740834000'], [$erstes->anstoss, $zweites->anstoss]);
        self::assertSame([['3', '1'], ['_', '_']], [[$spiele[0]->tippa, $spiele[0]->tippb], [$spiele[1]->tippa, $spiele[1]->tippb]]);
        self::assertSame(['', ''], [$spiele[0]->joker, $spiele[1]->joker], 'ohne Jokertipp kein Joker');
        self::assertSame([0, 0], array_map(static fn (UebersichtSpiel $s): int|string => $s->joker, self::sammle(true)), 'Jokertipp ohne Joker in der Datei');
    }

    public function testZeitraum(): void
    {
        $spiele = Tippuebersicht::sammle(Liga::lies(__DIR__ . '/fixtures/tippliga.l98'), 'tippliga', __DIR__ . '/fixtures/tippliga_tester.tip', 1740830000, PHP_INT_MAX, false, '', false, new Tippzeit(0, false, '', -1), []);

        self::assertSame(['2'], array_map(static fn (UebersichtSpiel $s): string => $s->nr, $spiele), 'nur das spaetere Spiel');
    }

    private static function spiel(string $spieltag, string $nr, int|string $a, int|string $b, int|string $joker = ''): UebersichtSpiel
    {
        return new UebersichtSpiel(new AnstehendesSpiel(), 'liga', $spieltag, $nr, $a, $b, $joker);
    }

    public function testTippdatei(): void
    {
        file_put_contents($this->pfad, "[Round1]\n@3@\nGA1=2\nGB1=1\nGA3=0\nGB3=0\n[Round2]\n@1@\nGA1=1\nGB1=1\n");

        self::assertTrue(Tippuebersicht::speichereTippdatei($this->pfad, [self::spiel('1', '1', 4, 0, 2), self::spiel('1', '2', '_', ''), self::spiel('3', '1', 1, 2, '')]));
        self::assertSame(
            "[Round1]\n@2@\nGA1=4\nGB1=0\nGA2=-1\nGB2=-1\nGA3=0\nGB3=0\n[Round2]\n@1@\nGA1=1\nGB1=1\n[Round3]\nGA1=1\nGB1=2\n",
            file_get_contents($this->pfad),
            'neue Tipps im Spieltag, die anderen bleiben, ein neuer Spieltag am Ende'
        );

        file_put_contents($this->pfad, "[Round1]\n@3@\nGA3=0\nGB3=0\n");
        Tippuebersicht::speichereTippdatei($this->pfad, [self::spiel('1', '1', 1, 1, 0)]);
        self::assertSame("[Round1]\n@3@\nGA1=1\nGB1=1\nGA3=0\nGB3=0\n", file_get_contents($this->pfad), 'ohne neuen Joker bleibt der alte');
    }

    public function testEinsicht(): void
    {
        file_put_contents($this->pfad, "[tester]\nGA1=1\nGB1=1\nGA5=2\nGB5=2\n\n[anna]\nGA1=0\nGB1=2\n");

        self::assertTrue(Tippuebersicht::speichereEinsicht($this->pfad, 'tester', [self::spiel('1', '1', 3, 1, 1)]));
        self::assertSame("\n[anna]\nGA1=0\nGB1=2\n[tester]\n@1@\nGA1=3\nGB1=1\nGA5=2\nGB5=2\n", file_get_contents($this->pfad), 'der eigene Abschnitt ans Ende, andere Tipps bleiben');

        unlink($this->pfad);
        Tippuebersicht::speichereEinsicht($this->pfad, 'neu', [self::spiel('1', '2', '', '')]);
        self::assertSame("\n[neu]\nGA2=-1\nGB2=-1\n", file_get_contents($this->pfad), 'ohne Datei ein neuer Abschnitt');
    }
}
