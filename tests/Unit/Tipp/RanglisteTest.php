<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Rangliste;
use PHPUnit\Framework\TestCase;

/**
 * Die Rangliste der Tipper und Teams aus einer Auswertung.
 */
class RanglisteTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-rangliste-' . bin2hex(random_bytes(4)) . '.aus';
        file_put_contents($this->pfad, "[anna]\nTeam=Rot\nName=Anna\nEmail=a@x\nSG1=3\nP11=1\nTP1=5\nSG2=3\nTP2=2\n\n"
            . "[bert]\nTeam=Blau\nSG1=3\nP11=1\nTP1=5\nSG2=2\nTP2=4\n\n"
            . "[cora]\nTeam=Rot\nSG1=2\nTP1=1\nSG2=3\nTP2=6\n\n"
            . "[dora]\nTeam=\nSG1=1\nTP1=0\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    /** @param array{0: mixed, 1: mixed, 2: mixed} $kriterien */
    private function lies(bool $teams = false, string $modus = 'bis', bool $mitStand = false, int $bis = 2, string $gewicht = 'absolut', array $kriterien = [0, 1, 2], bool $siege = true): Rangliste
    {
        $rangliste = Rangliste::lies($this->pfad, $teams, $modus, $mitStand, $bis, 2, '1', $gewicht, $kriterien, true, $siege);
        self::assertNotNull($rangliste);

        return $rangliste;
    }

    /** @return list<string> */
    private static function reihenfolge(Rangliste $r, string $feld = 'nick'): array
    {
        return array_map(static fn (int $x): string => (string) $r->eintraege[$r->eintragAuf($x)][$feld], array_keys($r->schluessel));
    }

    public function testTipper(): void
    {
        $r = $this->lies();

        self::assertSame(['bert', 'cora', 'anna', 'dora'], self::reihenfolge($r), 'bei 7 Punkten entscheidet die Quote: cora 1.40 vor anna 1.17');
        $anna = $r->eintraege[0];
        self::assertSame(['Anna', 'a@x', 'Rot', 6, 7], [$anna['name'], $anna['email'], $anna['team'], $anna['spiele'], $anna['punkte']]);
        self::assertSame(117.0, $anna['quote']);
        self::assertSame(1, $anna['p'][1]);
        self::assertSame(1, $r->eintraege[2]['siege'], 'Spieltag 2 gewinnt cora');
        self::assertSame(1, $r->eintraege[1]['siege'], 'Spieltag 1 teilen sich anna und bert');
        self::assertSame(1, $r->eintraege[0]['siege']);
        self::assertSame([1 => 1, 2 => 2, 0 => 3, 3 => 4], $r->plaetze());
        self::assertFalse($r->gleichAuf(1));
    }

    public function testBisNurUndGewicht(): void
    {
        self::assertSame(['anna', 'bert', 'cora', 'dora'], self::reihenfolge($this->lies(bis: 1)), 'nach Spieltag 1: Gleichstand, dann der hintere Index');
        self::assertTrue($this->lies(bis: 1)->gleichAuf(1));
        self::assertSame(['cora', 'bert', 'anna', 'dora'], self::reihenfolge($this->lies(modus: 'nur', mitStand: true)), 'nur Spieltag 2');
        self::assertSame(['bert', 'cora', 'anna', 'dora'], self::reihenfolge($this->lies(modus: 'nur')), 'nur ohne Stand: alle Spieltage');
        self::assertSame(['anna', 'bert', 'cora', 'dora'], self::reihenfolge($this->lies(gewicht: 'spiele')), 'bei 5 Spielen entscheiden die Punkte');
        self::assertSame(['bert', 'cora', 'anna', 'dora'], self::reihenfolge($this->lies(gewicht: 'relativ')));
        self::assertSame(['anna', 'bert', 'cora', 'dora'], self::reihenfolge($this->lies(modus: 'egal')), 'unbekannter Modus: nichts zaehlt');
    }

    public function testTeams(): void
    {
        $r = $this->lies(teams: true);

        self::assertSame(['Rot', 'Blau'], self::reihenfolge($r, 'team'));
        self::assertSame(['tipper' => 2, 'spiele' => 11, 'punkte' => 14], array_intersect_key($r->eintraege[0], ['tipper' => 0, 'spiele' => 0, 'punkte' => 0]));
    }

    public function testGesamtwertungUndOhneDatei(): void
    {
        file_put_contents($this->pfad, "[Options]\nAnzLigen=3\n[anna]\nSG3=1\nTP3=2\n");
        $r = Rangliste::lies($this->pfad, false, 'bis', false, 0, 0, '1', 'absolut', [0, 1, 2], false, false);

        self::assertNotNull($r);
        self::assertSame(3, $r->bis, 'so viele Ligen');
        self::assertSame(2, $r->eintraege[0]['punkte']);
        self::assertNull(Rangliste::lies($this->pfad . '.fehlt', false, 'bis', false, 0, 0, '1', 'absolut', [0, 1, 2], false, false));
    }
}
