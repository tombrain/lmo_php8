<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Liga;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spielplan;
use PHPUnit\Framework\TestCase;

/**
 * Aenderungen am Spielplan in den Rohdaten: Rueckrunde und Verschieben, gezaehlt wie die Classlib.
 */
class SpielplanTest extends TestCase
{
    private static function liga(string $runden): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", "[Options]\nType=0\nTeams=4\n" . $runden)));
    }

    /** @return list<string> "Heim-Gast" je Partie eines Spieltags */
    private static function paarungen(Liga $liga, int $spieltag): array
    {
        $paarungen = [];
        foreach ($liga->spieltage[$spieltag]->partien ?? [] as $nr => $partie) {
            $paarungen[] = $nr . ':' . $partie->heim . '-' . $partie->gast;
        }

        return $paarungen;
    }

    public function testRueckrunde(): void
    {
        $liga = self::liga("Rounds=4\nMatches=2\n\n[Round1]\nTA1=1\nTB1=2\nGA1=3\nGB1=0\nTA2=3\nTB2=4\n\n[Round2]\nTA1=1\nTB1=3\nTA2=2\nTB2=4\n\n[Round3]\nD1=01.03.2025\nHS=0201\nTA1=4\nTB1=4\nTA2=1\nTB2=1\nTA3=2\nTB3=2\n");

        self::assertTrue(Spielplan::rueckrunde($liga));
        self::assertSame(['1:1-2', '2:3-4'], self::paarungen($liga, 1), 'Hinrunde bleibt');
        self::assertSame(['1:2-1', '2:4-3'], self::paarungen($liga, 3), 'getauschtes Heimrecht, alte Partien fallen weg');
        self::assertSame(['1:3-1', '2:4-2'], self::paarungen($liga, 4), 'neu angelegt');
        self::assertSame(['-1', '-1', ''], [$liga->spieltage[3]->partien[1]->spiele[1]['GA'], $liga->spieltage[3]->partien[1]->spiele[1]['GB'], $liga->spieltage[3]->partien[1]->spiele[1]['AT']], 'ohne Ergebnis und Anstoss');
        self::assertSame(['01.03.2025', '0201'], [$liga->spieltage[3]->werte['D1'], $liga->spieltage[3]->werte['HS']], 'Tage und Tabellenkorrektur bleiben');
    }

    public function testRueckrundeNurBeiGeraderAnzahl(): void
    {
        $liga = self::liga("Rounds=3\nMatches=1\n\n[Round1]\nTA1=1\nTB1=2\n");

        self::assertFalse(Spielplan::rueckrunde($liga));
        self::assertSame([], self::paarungen($liga, 2));
    }

    public function testVerschieben(): void
    {
        $liga = self::liga("Rounds=2\nMatches=2\n\n[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\nTA2=3\nTB2=4\n\n[Round2]\nTA1=1\nTB1=3\nTA2=2\nTB2=4\n");

        self::assertTrue(Spielplan::verschiebe($liga, 1, 2, 1, 2));
        self::assertSame(['1:3-4'], self::paarungen($liga, 1), 'die folgenden ruecken auf');
        self::assertSame(['1:1-3', '2:2-4', '3:1-2'], self::paarungen($liga, 2), 'hinten angehaengt');
        self::assertSame('2', $liga->spieltage[2]->partien[3]->spiele[1]['GA'], 'mit Ergebnis');
        self::assertSame('3', $liga->optionen['Matches'], 'Spieltag 2 hat nun drei Partien');
    }

    public function testVerschiebenOhnePartieOderSpieltag(): void
    {
        $liga = self::liga("Rounds=2\nMatches=1\n\n[Round1]\nTA1=1\nTB1=2\n\n[Round2]\nTA1=3\nTB1=4\n");

        self::assertFalse(Spielplan::verschiebe($liga, 1, 2, 2, 1), 'Heim und Gast vertauscht');
        self::assertFalse(Spielplan::verschiebe($liga, 1, 3, 1, 2), 'Spieltag 3 gibt es nicht');
        self::assertFalse(Spielplan::verschiebe($liga, 1, 1, 1, 2), 'derselbe Spieltag');
        self::assertSame(['1:1-2'], self::paarungen($liga, 1));
        self::assertSame('1', $liga->optionen['Matches']);
    }

    public function testAufDenErstenLeerenPlatz(): void
    {
        $liga = self::liga("Rounds=2\nMatches=3\n\n[Round1]\nTA1=1\nTB1=2\nTA2=0\nTB2=0\nTA3=0\nTB3=0\n\n[Round2]\nTA1=3\nTB1=4\nTA2=0\nTB2=0\nTA3=0\nTB3=0\n");

        self::assertTrue(Spielplan::verschiebe($liga, 1, 2, 1, 2));
        self::assertSame(['1:3-4', '2:1-2', '3:0-0'], self::paarungen($liga, 2), 'statt hinter die leeren Plaetze');
        self::assertSame(['1:0-0', '2:0-0'], self::paarungen($liga, 1));
        self::assertTrue(Spielplan::verschiebe($liga, 2, 1, 1, 2));
        self::assertSame(['1:1-2', '2:0-0'], self::paarungen($liga, 1), 'und zurueck');
        self::assertSame('3', $liga->optionen['Matches'], 'Hin und Her laesst die Anzahl, wie sie ist');
    }
}
