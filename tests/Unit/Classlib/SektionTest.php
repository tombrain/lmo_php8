<?php

namespace Lmo\Tests\Unit\Classlib;

/** team, sektion und optionsSektion */
final class SektionTest extends ClasslibTestCase
{
    public function testTeamDefaults(): void
    {
        $team = new \team('FC Bayern München', 'FCB', 1, 'Bayern');

        self::assertSame('FC Bayern München', $team->name);
        self::assertSame('FCB', $team->kurz);
        self::assertSame('Bayern', $team->mittel);
        self::assertSame(1, $team->nr);
        self::assertSame(['SP' => 0, 'SM' => 0, 'TOR1' => 0, 'TOR2' => 0, 'STDA' => 0, 'URL' => '', 'NOT' => ''], $team->keyValues);
    }

    public function testTeamKeyValues(): void
    {
        $team = new \team('A');
        $team->addKeyValue('URL', 'http://a.example');
        $team->addKeyValue('SP', 3);

        self::assertSame('http://a.example', $team->valueForKey('URL'));
        self::assertSame(3, $team->valueForKey('SP'));
    }

    public function testSektion(): void
    {
        $sektion = new \sektion('Round1');
        $sektion->setKeyValue('D1', '01.08.2024');
        $sektion->addKeyValue('D2', '02.08.2024');
        $sektion->setKeyValue('D1', '03.08.2024');

        self::assertSame('[Round1]', $sektion->sektionName());
        self::assertSame('03.08.2024', $sektion->valueForKey('D1'));
        self::assertSame(['D1' => '03.08.2024', 'D2' => '02.08.2024'], $sektion->keyValues);
    }

    public function testSektionHtmlOutput(): void
    {
        $sektion = new \sektion('News');
        $sektion->setKeyValue('a', 'b');

        $this->expectOutputString('<BR>[News]<BR>a = b');
        $sektion->HTMLoutput();
    }

    public function testOptionsDefaults(): void
    {
        $options = new \optionsSektion();

        self::assertSame('[Options]', $options->sektionName());
        self::assertSame(3, $options->keyValues['PointsForWin']);
        self::assertSame(1, $options->keyValues['PointsForDraw']);
        self::assertSame(0, $options->keyValues['PointsForLost']);
        self::assertSame('d.m.Y H:i', $options->keyValues['DatF']);
    }

    public function testOptionsDetailsOverrideDefaults(): void
    {
        $options = new \optionsSektion('', ['PointsForWin' => 2, 'Eigene' => 'x']);

        self::assertSame(2, $options->keyValues['PointsForWin']);
        self::assertSame('x', $options->keyValues['Eigene']);
        self::assertSame(1, $options->keyValues['PointsForDraw']);
    }

    public function testOptionsTakeCountsFromLeague(): void
    {
        $liga = self::league(['A', 'B', 'C', 'D'], [
            [[1, 2, 1, 0], [3, 4, 1, 0]],
            [[2, 3, 1, 0]],
        ]);
        $liga->aktSpTag = 2;

        $options = new \optionsSektion($liga, ['Name' => 'wird ueberschrieben']);

        self::assertSame('Testliga', $options->keyValues['Name']);
        self::assertSame(2, $options->keyValues['Actual']);
        self::assertSame(4, $options->keyValues['Teams']);
        self::assertSame(2, $options->keyValues['Rounds']);
        self::assertSame(2, $options->keyValues['Matches']);
    }

    public function testOptionsRecognizeLeagueSubclasses(): void
    {
        $liga = self::league(['A', 'B'], [[[1, 2, 1, 0]]], [], 'ligaHandball');

        $options = new \optionsSektion($liga);

        self::assertSame(2, $options->keyValues['Teams']);
    }
}
