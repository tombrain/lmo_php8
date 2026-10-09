<?php

namespace Lmo\Tests\Unit\Classlib;

/** Aufbau und Nachschlagen in der Liga (ohne Tabellenberechnung) */
final class LigaTest extends ClasslibTestCase
{
    /** 4 Teams, 2 Spieltage; Anstoss in Spieltag 2 frueher als in Spieltag 1 */
    private static function liga(array $options = []): \liga
    {
        return self::league(['Alpha', 'Beta', 'Gamma', 'Delta'], [
            [[1, 2, 2, 0, 0, 3000], [3, 4, 2, 1, 0, 4000]],
            [[2, 3, 3, 1, 0, 1000], [4, 1, 0, 1, 0, 2000]],
        ], $options);
    }

    private static function games(array $games): array
    {
        return array_map(static fn (array $g): string => $g['spTag'] . ':' . $g['partie']->heim->name . '-' . $g['partie']->gast->name, $games);
    }

    public function testTeams(): void
    {
        $liga = self::liga();

        self::assertSame(4, $liga->teamCount());
        self::assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $liga->teamNames());
        self::assertSame(3, $liga->teamForName('Gamma')->nr);
        self::assertNull($liga->teamForName('gamma'));
        self::assertSame('Beta', $liga->teamForNumber(2)->name);
        self::assertNull($liga->teamForNumber(0));
        self::assertNull($liga->teamForNumber(5));
    }

    public function testPartien(): void
    {
        $liga = self::liga();

        self::assertSame(4, $liga->partienCount());
        self::assertSame('Gamma', $liga->partieForNumber(2)->heim->name);
        self::assertNull($liga->partieForNumber(0));
        self::assertNull($liga->partieForNumber(5));
    }

    public function testAllPartieForTeams(): void
    {
        $liga = self::league(['A', 'B', 'C'], [
            [[1, 2, 1, 0]],
            [[2, 1, 2, 2]],
            [[1, 3, 0, 0]],
        ]);
        $a = $liga->teamForNumber(1);
        $b = $liga->teamForNumber(2);
        $c = $liga->teamForNumber(3);

        $hin = $liga->allPartieForTeams($a, $b);
        self::assertCount(1, $hin);
        self::assertSame(1, $hin[0]->hTore);

        $beide = $liga->allPartieForTeams($a, $b, true);
        self::assertCount(2, $beide);

        self::assertNull($liga->allPartieForTeams($c, $b, true));
    }

    public function testPartieForTeams(): void
    {
        $liga = self::liga();
        $alpha = $liga->teamForNumber(1);
        $beta = $liga->teamForNumber(2);
        $gamma = $liga->teamForNumber(3);

        self::assertSame(2, $liga->partieForTeams($alpha, $beta)->hTore);
        self::assertNull($liga->partieForTeams($alpha, $gamma));
    }

    public function testPartieForTeamNamesIgnoresCase(): void
    {
        $liga = self::liga();

        self::assertSame(3, $liga->partieForTeamNames('beta', 'GAMMA')->hTore);
        self::assertNull($liga->partieForTeamNames('Gamma', 'Beta'));
    }

    public function testGamesSorted(): void
    {
        $liga = self::liga();

        self::assertSame(['1:Alpha-Beta', '1:Gamma-Delta', '2:Beta-Gamma', '2:Delta-Alpha'], self::games($liga->gamesSorted()));
        self::assertSame(['2:Delta-Alpha', '2:Beta-Gamma', '1:Gamma-Delta', '1:Alpha-Beta'], self::games($liga->gamesSorted(true, SORT_DESC)));
        self::assertSame(['2:Beta-Gamma', '2:Delta-Alpha', '1:Alpha-Beta', '1:Gamma-Delta'], self::games($liga->gamesSorted(false)));
    }

    public function testGamesSortedForTeam(): void
    {
        $liga = self::liga(['favTeam' => 4]);
        $alpha = $liga->teamForNumber(1);

        self::assertSame(['1:Alpha-Beta', '2:Delta-Alpha'], self::games($liga->gamesSorted(true, SORT_ASC, $alpha)));
        self::assertSame(['1:Alpha-Beta', '2:Delta-Alpha'], self::games($liga->gamesSortedForTeam($alpha)));
        // ohne Team: Lieblingsteam aus den Optionen
        self::assertSame(['1:Gamma-Delta', '2:Delta-Alpha'], self::games($liga->gamesSortedForTeam()));
    }

    public function testGamesSortedWithoutGames(): void
    {
        $liga = self::league(['A', 'B'], []);

        self::assertSame([], $liga->gamesSorted());
    }

    public function testSpieltage(): void
    {
        $liga = self::liga();

        self::assertSame(2, $liga->spieltageCount());
        self::assertSame(1, $liga->SpieltagForNumber(1)->nr);
        self::assertSame(2, $liga->SpieltagForNumber(2)->nr);
        // groessere Nummern liefern den letzten Spieltag
        self::assertSame(2, $liga->SpieltagForNumber(9)->nr);
    }

    public function testAktuellerSpieltag(): void
    {
        $liga = self::liga(['Actual' => 1]);

        self::assertSame(1, $liga->aktuellerSpieltag()->nr);
    }

    public function testLigaDatumAsString(): void
    {
        $liga = self::liga(['DatF' => 'd.m.Y']);
        $liga->ligaDatum = mktime(12, 0, 0, 5, 17, 2025);

        self::assertSame('17.05.2025', $liga->ligaDatumAsString());
        self::assertSame('2025-05-17 12:00', $liga->ligaDatumAsString('Y-m-d H:i'));
    }

    public function testGetIniDataTakesValueOut(): void
    {
        $liga = new \liga();
        $data = ['D1' => '01.08.2024', 'MO' => '2', 1 => ['GA' => '3', 'GB' => '1']];

        self::assertSame('01.08.2024', $liga->getIniData('D1', $data));
        self::assertSame('', $liga->getIniData('D1', $data));
        self::assertSame('3', $liga->getIniData('GA', $data, 1));
        self::assertSame(['MO' => '2', 1 => ['GB' => '1']], $data);
    }

    public function testGetIniDataWithoutArray(): void
    {
        $liga = new \liga();
        $none = null;

        self::assertSame('', $liga->getIniData('D1', $none));
    }

    public function testShowDetails(): void
    {
        $liga = self::league(['A', 'B'], [[[1, 2, 1, 0]]]);

        $this->expectOutputString("LigaName = Testliga\n\n1. Spieltag ()\nA - B Anpfiff: Uhr Ergebnis:1 - 0\n\n");
        $liga->showDetails();
    }
}
