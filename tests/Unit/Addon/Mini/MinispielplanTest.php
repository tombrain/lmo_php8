<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon\Mini;

use Lmo\Addon\Mini\Minispielplan;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Bilder;
use PHPUnit\Framework\TestCase;

/**
 * Der Mini-Spielplan: naechstes Spiel, Spiel davor, bisherige Ergebnisse und Bilanz.
 */
class MinispielplanTest extends TestCase
{
    // A-B 2:1 (1000), C-A 1:1 (2000), A-C offen (5000); Lieblingsmannschaft A
    private const LIGA = "[Options]\nName=L\nType=0\nTeams=3\nRounds=3\nMatches=1\nfavTeam=1\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n\n"
        . "[Team1]\nSP=0\n\n[Team2]\nSP=0\n\n[Team3]\nSP=0\n\n"
        . "[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\nAT1=1000\n\n[Round2]\nTA1=3\nTB1=1\nGA1=1\nGB1=1\nAT1=2000\n\n[Round3]\nTA1=1\nTB1=3\nGA1=-1\nGB1=-1\nAT1=5000\n";

    private string $datei;

    protected function setUp(): void
    {
        $this->datei = sys_get_temp_dir() . '/lmo-minispielplan-' . bin2hex(random_bytes(4)) . '.l98';
        file_put_contents($this->datei, self::LIGA);
        touch($this->datei, (int) mktime(12, 0, 0, 3, 1, 2025));
    }

    protected function tearDown(): void
    {
        unlink($this->datei);
    }

    /** @return array{abbruch: string|null, werte: array<string, string>, bloecke: array<string, list<array<string, string>>>} */
    private function werte(?string $a, ?string $b, string $liga = self::LIGA, bool $mitArchiv = false): array
    {
        $text = ['mini' => array_map(static fn (int $i): string => 'M' . $i, range(0, 17))];
        $archiv = static function () use ($mitArchiv): array {
            if (!$mitArchiv) {
                throw new \LogicException('nur mit Spiel gefragt');
            }

            return [];
        };

        return (new Minispielplan($text, new Bilder(sys_get_temp_dir() . '/lmo-keine-bilder', 'IMG')))
            ->werte(Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), $this->datei, $a, $b, $archiv, true, 120, 3000);
    }

    public function testNaechstesSpiel(): void
    {
        $w = $this->werte(null, null, mitArchiv: true);

        self::assertNull($w['abbruch']);
        self::assertSame(['M1', 'Alpha', 'Gamma', 'M15: 0, M16: 0, M17: 33', 'M14: 01.03.2025'], [$w['werte']['gameTxt'], $w['werte']['homeName'], $w['werte']['guestName'], $w['werte']['countDown'], $w['werte']['ligaDatum']], 'Lieblingsmannschaft A gegen C, in 2000 Sekunden');
        self::assertArrayNotHasKey('homeTore', $w['werte'], 'vor dem Spiel keine Tore');
        self::assertSame([['Gamma', 'Alpha', '1', '1', 'M7']], array_map(static fn (array $z): array => [$z['previous_homeName'], $z['previous_guestName'], $z['previous_hTore'], $z['previous_gTore'], $z['previous_gameTxt']], $w['bloecke']['previous']), 'das Spiel davor');
        self::assertSame([['M12', 'draw', '1', '1']], array_map(static fn (array $z): array => [$z['where'], $z['class'], $z['hTore'], $z['gTore']], $w['bloecke']['matches']), 'bisher: 1:1 auswaerts, das offene Spiel nicht');
        self::assertSame(['1', '0', '1', '0', '0', '109'], [$w['werte']['matchCount'], $w['werte']['winCount'], $w['werte']['drawCount'], $w['werte']['lostCount'], $w['werte']['winWidth'], $w['werte']['drawWidth']]);
    }

    public function testPaarung(): void
    {
        $w = $this->werte('1', '2', mitArchiv: true);

        self::assertSame(['M3', 'Alpha', 'Beta'], [$w['werte']['gameTxt'], $w['werte']['homeName'], $w['werte']['guestName']], 'das Spiel von a (zu Hause) gegen b');
        self::assertSame([['M13', 'win', '2', '1']], array_map(static fn (array $z): array => [$z['where'], $z['class'], $z['hTore'], $z['gTore']], $w['bloecke']['matches']), 'zu Hause 2:1 gewonnen');
        self::assertSame([], $w['bloecke']['previous']);
        self::assertArrayNotHasKey('homeName', $this->werte('2', '1')['werte'], 'b zu Hause gegen a: kein Spiel (nur Heimspiele von a)');
    }

    public function testSaisonZuEnde(): void
    {
        $w = $this->werte(null, null, str_replace("GA1=-1\nGB1=-1\nAT1=5000", "GA1=3\nGB1=0\nAT1=2500", self::LIGA), true);

        self::assertSame(['M2', '3', '0'], [$w['werte']['gameTxt'], $w['werte']['homeTore'], $w['werte']['guestTore']], 'kein Spiel mehr: das letzte mit Ergebnis');
        self::assertSame([], $w['bloecke']['previous']);
    }

    public function testOhneMannschaft(): void
    {
        self::assertSame('M8', $this->werte(null, null, str_replace('favTeam=1', 'favTeam=0', self::LIGA))['abbruch'], 'weder a noch Lieblingsmannschaft');
    }
}
