<?php

namespace Lmo\Tests\Unit\Classlib;

final class SpieltagTest extends ClasslibTestCase
{
    /** @var \team[] */
    private array $teams;

    protected function setUp(): void
    {
        $this->teams = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $i => $name) {
            $this->teams[$i + 1] = new \team($name, $name, $i + 1);
        }
    }

    private function partie(int $spNr, int $heim, int $gast, $zeit = ''): \partie
    {
        return new \partie($spNr, $zeit, '', $this->teams[$heim], $this->teams[$gast], -1, -1);
    }

    private function spieltag(): \spieltag
    {
        $spieltag = new \spieltag(1, '', '');
        foreach ([[1, 1, 2, 3000], [2, 3, 4, 1000], [3, 5, 6, 2000]] as [$nr, $heim, $gast, $zeit]) {
            $partie = $this->partie($nr, $heim, $gast, $zeit);
            $spieltag->addPartie($partie);
        }
        return $spieltag;
    }

    private static function nummern(array $partien): array
    {
        return array_map(static fn (\partie $p): int => $p->spNr, $partien);
    }

    public function testPartien(): void
    {
        $spieltag = $this->spieltag();

        self::assertSame(3, $spieltag->partienCount());
        self::assertSame([1, 2, 3], self::nummern($spieltag->getPartien()));
    }

    public function testGetPartienNachDatum(): void
    {
        $spieltag = $this->spieltag();

        self::assertSame([2, 3, 1], self::nummern($spieltag->getPartien('datum')));
        self::assertSame([1, 3, 2], self::nummern($spieltag->getPartien('datum', 'DESC')));
    }

    public function testModus(): void
    {
        $spieltag = new \spieltag(1, '', '');
        self::assertSame(0, $spieltag->getModus());

        $spieltag->setModus(2);
        self::assertSame(2, $spieltag->getModus());
    }

    public function testPartieForNumber(): void
    {
        $spieltag = $this->spieltag();

        self::assertSame(2, $spieltag->partieForNumber(2)->spNr);
        self::assertNull($spieltag->partieForNumber(0));
        self::assertNull($spieltag->partieForNumber(4));
    }

    public function testPartieForTeams(): void
    {
        $spieltag = $this->spieltag();

        self::assertSame(2, $spieltag->partieForTeams(3, 4)->spNr);
        self::assertNull($spieltag->partieForTeams(4, 3));
        self::assertNull($spieltag->partieForTeams(1, 4));
        // Die Suche darf die Teamnummern nicht veraendern
        self::assertSame([1, 2, 3, 4, 5, 6], array_map(static fn (\team $t): int => $t->nr, array_values($this->teams)));
    }

    public function testPartieForTeamNames(): void
    {
        $spieltag = $this->spieltag();

        self::assertSame(3, $spieltag->partieForTeamNames('E', 'F')->spNr);
        self::assertNull($spieltag->partieForTeamNames('F', 'E'));
    }

    public function testRemovePartie(): void
    {
        $spieltag = $this->spieltag();
        $zweite = $spieltag->partieForNumber(2);

        self::assertTrue($spieltag->removePartie($zweite));
        self::assertSame([1, 3], self::nummern($spieltag->partien));
        self::assertSame(3, $spieltag->partieForNumber(2)->spNr);

        $fremd = $this->partie(9, 1, 6);
        self::assertFalse($spieltag->removePartie($fremd));
        self::assertSame(2, $spieltag->partienCount());
    }

    public function testDatumsangaben(): void
    {
        $von = mktime(0, 0, 0, 8, 23, 2024);
        $bis = mktime(0, 0, 0, 8, 25, 2024);

        $beide = new \spieltag(1, $von, $bis);
        self::assertSame('23.08.2024 - 25.08.2024', $beide->vonBisString());
        self::assertSame('23.08.2024', $beide->vonString());
        self::assertSame('25.08.2024', $beide->bisString());

        self::assertSame('23.08.2024', (new \spieltag(1, $von, ''))->vonBisString());
        self::assertSame('25.08.2024', (new \spieltag(1, '', $bis))->vonBisString());

        $keins = new \spieltag(1, '', '');
        self::assertSame('', $keins->vonBisString());
        self::assertSame('', $keins->vonString());
        self::assertSame('', $keins->bisString());
    }

    public function testShowDetails(): void
    {
        $spieltag = new \spieltag(3, mktime(0, 0, 0, 8, 23, 2024), '');
        $partie = $this->partie(1, 1, 2, mktime(15, 30, 0, 8, 23, 2024));
        $spieltag->addPartie($partie);

        $this->expectOutputString("\n3. Spieltag (23.08.2024)\nA - B Anpfiff: 15:30Uhr Ergebnis:-1 - -1\n\n");
        $spieltag->showDetails();
    }
}
