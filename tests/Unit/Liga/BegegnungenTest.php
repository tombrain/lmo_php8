<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Liga;

use Lmo\Liga\Begegnung;
use Lmo\Liga\Begegnungen;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use PHPUnit\Framework\TestCase;

/**
 * Die Spiele einer Liga fuer Mini-Spielplan und Viewer und ihre Ausgabe.
 */
class BegegnungenTest extends TestCase
{
    private const LIGA = "[Options]\nName=X\nType=0\nTeams=3\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma 2\n\n[Teamk]\n1=ALP\n\n"
        . "[Team1]\nSP=0\n\n[Team2]\nSP=0\n\n[Team3]\nSP=0\n\n"
        . "[Round1]\nTA1=1\nTB1=2\nGA1=20\nGB1=10\nAT1=200\nNT1=Notiz\nBE1=http://b\nSP1=2\n\n"
        . "[Round2]\nTA1=2\nTB1=1\nGA1=-1\nGB1=-1\nAT1=100\nTA2=3\nTB2=1\nGA2=-2\nGB2=-1\nAT2=\n";

    private static function begegnungen(): Begegnungen
    {
        return Begegnungen::aus(Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", self::LIGA))));
    }

    public function testMannschaften(): void
    {
        $b = self::begegnungen();

        self::assertSame([1, 3, null, null], [$b->mannschaft('1'), $b->mannschaft(3), $b->mannschaft(4), $b->mannschaft(null)]);
        self::assertSame(2, $b->mannschaftNamens('Beta'));
        self::assertNull($b->mannschaftNamens('beta'), 'genau so geschrieben');
        self::assertSame(['Alpha', 'Beta', 'Gamma 2'], $b->namen());
        self::assertSame('ALP', $b->daten(1)->kurz);
    }

    public function testSpiele(): void
    {
        $b = self::begegnungen();

        self::assertSame(['100', '200'], array_map(static fn (Begegnung $s): string => $s->zeit, $b->nachZeit(2)), 'nach dem Anstoss');
        self::assertCount(1, $b->zwischen(1, 2, false));
        self::assertCount(2, $b->zwischen(1, 2, true), 'mit getauschtem Heimrecht');
        self::assertSame(['Gamma 2'], Begegnungen::aehnlicheNamen($b->namen(), 'gamma'), 'ohne Ziffern und Leerzeichen');
        self::assertSame(3, $b->anzahl());
        self::assertSame(['', '100', '200'], array_map(static fn (Begegnung $s): string => $s->zeit, $b->nachZeit()), 'alle; ohne Anstoss vorn (wie bis 4.5)');
        self::assertSame(['', '100'], array_map(static fn (Begegnung $s): string => $s->zeit, $b->spieltag(2, true)), 'nach Datum');
        self::assertSame(['100', ''], array_map(static fn (Begegnung $s): string => $s->zeit, $b->spieltag(2)), 'wie in der Datei');
    }

    public function testAusgabe(): void
    {
        [$erstes, $offen, $gruen] = self::begegnungen()->spiele;

        self::assertSame(['2', '1', 1], [$erstes->toreHeimText('_', 10), $erstes->toreGastText('_', 10), $erstes->wertung()], 'mit Torfaktor');
        self::assertSame(['http://b', 'nV'], [$erstes->bericht, $erstes->spielendeText([0 => 'nV', 1 => 'iE'])]);
        self::assertSame(['_', '_', -1], [$offen->toreHeimText(), $offen->toreGastText(), $offen->wertung()]);
        self::assertSame(['0*', '_', 1], [$gruen->toreHeimText(), $gruen->toreGastText(), $gruen->wertung()], 'am gruenen Tisch');
        self::assertSame('', $gruen->datum(), 'ohne Anstoss');
        self::assertSame(date('d.m.Y', 200), $erstes->datum());
    }
}
