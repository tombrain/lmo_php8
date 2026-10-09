<?php

namespace Lmo\Tests\Unit\Classlib;

/**
 * liga::calcTable(), sortTable() und der direkte Vergleich.
 *
 * Grundliga (Punkte 3/1/0):
 *   Spieltag 1: A-B 2:0, C-D 2:1
 *   Spieltag 2: B-C 3:1, D-A 0:1
 * Endstand: A 6 (3:0), B 3 (3:3), C 3 (3:4), D 0 (1:3)
 */
final class LigaTableTest extends ClasslibTestCase
{
    private const ROUNDS = [
        [[1, 2, 2, 0], [3, 4, 2, 1]],
        [[2, 3, 3, 1], [4, 1, 0, 1]],
    ];

    private static function liga(array $options = [], array $teamKeys = []): \liga
    {
        $liga = self::league(['A', 'B', 'C', 'D'], self::ROUNDS, $options);
        foreach ($teamKeys as $nr => $keys) {
            foreach ($keys as $key => $value) {
                $liga->teamForNumber($nr)->addKeyValue($key, $value);
            }
        }
        return $liga;
    }

    public function testTableAfterAllRounds(): void
    {
        $table = self::liga()->calcTable(2);

        self::assertSame(['A', 'B', 'C', 'D'], self::order($table));
        $a = self::row($table, 'A');
        self::assertSame(1, $a['pos']);
        self::assertSame([2, 2, 0, 0], [$a['spiele'], $a['s'], $a['u'], $a['n']]);
        self::assertSame([3, 0, 3, 6, 0], [$a['pTor'], $a['mTor'], $a['dTor'], $a['pPkt'], $a['mPkt']]);
        $b = self::row($table, 'B');
        self::assertSame([2, 1, 0, 1], [$b['spiele'], $b['s'], $b['u'], $b['n']]);
        self::assertSame([3, 3, 0, 3, 3], [$b['pTor'], $b['mTor'], $b['dTor'], $b['pPkt'], $b['mPkt']]);
        self::assertSame([1, 2, 3, 4], array_column($table, 'pos'));
    }

    public function testTableForFirstRound(): void
    {
        self::assertSame(['A', 'C', 'D', 'B'], self::order(self::liga()->calcTable(1)));
    }

    public function testRoundZeroUsesCurrentRound(): void
    {
        self::assertSame(['A', 'C', 'D', 'B'], self::order(self::liga(['Actual' => 1])->calcTable(0)));
    }

    public function testHomeTable(): void
    {
        $table = self::liga()->calcTable(2, 'heim');

        // B und A je 3 Punkte und +2 Tore, B hat mehr Tore geschossen
        self::assertSame(['B', 'A', 'C', 'D'], self::order($table));
        $a = self::row($table, 'A');
        self::assertSame([1, 2, 0, 3], [$a['spiele'], $a['pTor'], $a['mTor'], $a['pPkt']]);
    }

    public function testAwayTable(): void
    {
        $table = self::liga()->calcTable(2, 'gast');

        self::assertSame(['A', 'D', 'C', 'B'], self::order($table));
        $a = self::row($table, 'A');
        self::assertSame([1, 1, 0, 3], [$a['spiele'], $a['pTor'], $a['mTor'], $a['pPkt']]);
    }

    public function testFirstAndSecondHalfOfSeason(): void
    {
        $liga = self::liga();

        self::assertSame(['A', 'C', 'D', 'B'], self::order($liga->calcTable(2, 'hin')));
        self::assertSame(['B', 'A', 'D', 'C'], self::order($liga->calcTable(2, 'rueck')));
    }

    public function testPositionPerRound(): void
    {
        $table = self::liga()->calcTable(2, 'all', true);

        self::assertSame([1, 1], self::row($table, 'A')['possp']);
        self::assertSame([4, 2], self::row($table, 'B')['possp']);
        self::assertSame([2, 3], self::row($table, 'C')['possp']);
        self::assertSame([3, 4], self::row($table, 'D')['possp']);
    }

    public function testUnplayedGamesDoNotCount(): void
    {
        $liga = self::league(['A', 'B'], [[[1, 2, -1, -1]]]);
        $table = $liga->calcTable(1);

        foreach ($table as $row) {
            self::assertSame([0, 0, 0, 0], [$row['spiele'], $row['pTor'], $row['mTor'], $row['pPkt']]);
        }
    }

    public function testCupRoundsAreSkipped(): void
    {
        $liga = self::liga();
        $liga->SpieltagForNumber(2)->setModus(2);

        self::assertSame(['A', 'C', 'D', 'B'], self::order($liga->calcTable(2)));
    }

    public function testPenaltyPointsAndGoals(): void
    {
        $table = self::liga([], [2 => ['SP' => 4], 3 => ['TOR1' => 1]])->calcTable(2);

        self::assertSame(['A', 'C', 'D', 'B'], self::order($table));
        self::assertSame(-1, self::row($table, 'B')['pPkt']);
        self::assertSame(2, self::row($table, 'C')['pTor']);
    }

    public function testPenaltyStartsAtGivenRound(): void
    {
        $liga = self::liga([], [2 => ['SP' => 4, 'STDA' => 2]]);

        self::assertSame(0, self::row($liga->calcTable(1), 'B')['pPkt']);
        self::assertSame(-1, self::row($liga->calcTable(2), 'B')['pPkt']);
    }

    public function testMinusPointPenaltyOnlyWithTwoPointColumns(): void
    {
        self::assertSame(3, self::row(self::liga([], [2 => ['SM' => 2]])->calcTable(2), 'B')['mPkt']);
        self::assertSame(1, self::row(self::liga(['MinusPoints' => 2], [2 => ['SM' => 2]])->calcTable(2), 'B')['mPkt']);
    }

    public function testKegelSortsByGoalsBeforeDifference(): void
    {
        $rounds = [[[1, 2, 5, 4], [3, 4, 2, 0]]];

        self::assertSame(['C', 'A', 'B', 'D'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds)->calcTable(1)));
        self::assertSame(['A', 'C', 'B', 'D'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds, ['Kegel' => 1])->calcTable(1)));
    }

    public function testPointsAfterExtraTimeAndPenalties(): void
    {
        $liga = self::league(['A', 'B', 'C', 'D'], [[[1, 2, 2, 1, 2], [3, 4, 1, 0, 1]]], [
            'XtraS' => 2, 'XtraU' => 1, 'XtraV' => 1,
            'SpezS' => 2, 'SpezU' => 1, 'SpezV' => 0,
        ]);
        $table = $liga->calcTable(1);

        self::assertSame([2, 1], [self::row($table, 'A')['pPkt'], self::row($table, 'A')['mPkt']]);
        self::assertSame([1, 2], [self::row($table, 'B')['pPkt'], self::row($table, 'B')['mPkt']]);
        self::assertSame(2, self::row($table, 'C')['pPkt']);
        self::assertSame(0, self::row($table, 'D')['pPkt']);
    }

    public function testGreenTable(): void
    {
        $table = self::league(['A', 'B', 'C', 'D'], [[[1, 2, -2, 0], [3, 4, 0, -2]]])->calcTable(1);

        $a = self::row($table, 'A');
        self::assertSame([1, 1, 3, 0], [$a['spiele'], $a['s'], $a['pPkt'], $a['pTor']]);
        $b = self::row($table, 'B');
        self::assertSame([1, 1, 0], [$b['spiele'], $b['n'], $b['pPkt']]);
        self::assertSame(3, self::row($table, 'D')['pPkt']);
        self::assertSame(0, self::row($table, 'C')['pPkt']);
    }

    /**
     * A und B je 3 Punkte, A hat die bessere Tordifferenz, B gewann das direkte Duell.
     *   Spieltag 1: A-B 0:1, C-D 0:0
     *   Spieltag 2: A-C 5:0, B-D 0:1
     */
    public function testDirectComparisonAtTop(): void
    {
        $rounds = [[[1, 2, 0, 1], [3, 4, 0, 0]], [[1, 3, 5, 0], [2, 4, 0, 1]]];

        self::assertSame(['D', 'A', 'B', 'C'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds)->calcTable(2)));
        self::assertSame(['D', 'B', 'A', 'C'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds, ['Direct' => 1])->calcTable(2)));
    }

    /**
     * Wie oben, aber A und B stehen punktgleich am Tabellenende.
     *   Spieltag 1: A-B 0:1, C-D 2:2
     *   Spieltag 2: A-C 1:0, B-D 0:5
     *   Spieltag 3: C-B 4:0, D-A 3:0
     */
    public function testDirectComparisonAtBottom(): void
    {
        $rounds = [[[1, 2, 0, 1], [3, 4, 2, 2]], [[1, 3, 1, 0], [2, 4, 0, 5]], [[3, 2, 4, 0], [4, 1, 3, 0]]];

        self::assertSame(['D', 'C', 'A', 'B'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds)->calcTable(3)));
        self::assertSame(['D', 'C', 'B', 'A'], self::order(self::league(['A', 'B', 'C', 'D'], $rounds, ['Direct' => 1])->calcTable(3)));
    }

    public function testDirectComparisonTableHomeWinAtGreenTable(): void
    {
        $liga = self::league(['A', 'B'], [[[1, 2, -2, 0]]], ['PointsForLost' => 1]);
        $table = $liga->calcTableforTeams([1 => $liga->teamForNumber(1), 2 => $liga->teamForNumber(2)]);

        self::assertSame([3, 1], [self::row($table, 'A')['pPkt'], self::row($table, 'A')['mPkt']]);
        self::assertSame([1, 3], [self::row($table, 'B')['pPkt'], self::row($table, 'B')['mPkt']]);
    }

    public function testDirectComparisonTableAwayWinAtGreenTable(): void
    {
        $liga = self::league(['A', 'B'], [[[1, 2, 0, -2]]], ['PointsForLost' => 1]);
        $table = $liga->calcTableforTeams([1 => $liga->teamForNumber(1), 2 => $liga->teamForNumber(2)]);

        self::assertSame([1, 3], [self::row($table, 'A')['pPkt'], self::row($table, 'A')['mPkt']]);
        self::assertSame([3, 1], [self::row($table, 'B')['pPkt'], self::row($table, 'B')['mPkt']]);
    }

    public function testDirectComparisonTableIgnoresOtherTeams(): void
    {
        $rounds = [[[1, 2, 0, 1], [3, 4, 0, 0]], [[1, 3, 5, 0], [2, 4, 0, 1]]];
        $liga = self::league(['A', 'B', 'C', 'D'], $rounds);
        $table = $liga->calcTableforTeams([1 => $liga->teamForNumber(1), 2 => $liga->teamForNumber(2)]);

        self::assertSame(['B', 'A'], self::order($table));
        self::assertSame([1, 1, 0, 3], [self::row($table, 'B')['spiele'], self::row($table, 'B')['pTor'], self::row($table, 'B')['mTor'], self::row($table, 'B')['pPkt']]);
    }
}
