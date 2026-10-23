<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\SpieltageZuordnenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Partien anderen Spieltagen zuordnen: die Anzeige wie die Classlib die Liga liest und das
 * Verschieben ueber die Bruecke.
 */
class SpieltageZuordnenControllerTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga & Co\nType=0\nTeams=3\nRounds=2\nMatches=1\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n\n[Round1]\nD1=01.02.2025\nD2=02.02.2025\nTA1=1\nTB1=2\nGA1=2\nGB1=1\nAT1=1738422000\n\n[Round2]\nTA1=2\nTB1=3\nGA1=-1\nGB1=-1\n";

    private const POKAL = "[Options]\nName=Pokal\nType=1\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=Alpha\n2=Beta\n\n[Round1]\nMO=2\nTA1=1\nTB1=2\n";

    /** @var list<array{string, list<array{int, int, int, int}>}> was verschoben werden sollte */
    private array $wuensche = [];

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2, string $liga = self::LIGA, ?Liga $danach = null): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        for ($i = 5001; $i <= 5035; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98', 'st' => '-10']),
            '',
            new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), ''),
            new Konfiguration([]),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            new Addons(),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new SpieltageZuordnenController(function (string $datei, array $wuensche) use ($danach): array {
            $this->wuensche[] = [$datei, $wuensche];

            return ['AUSGABE ', [[1, 2, 1, 2, 'Alpha', 'Beta'], [2, 1, 2, 3, 'Beta', 'Gamma']], $danach];
        });
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        $kontext = static fn (string $st): Kontext => new Kontext(
            new Anfrage(['todo' => 'edit', 'st' => $st]),
            '',
            null,
            new Konfiguration([]),
            [],
            '/lmoadmin.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => 2])
        );
        $controller = new SpieltageZuordnenController(static fn (): array => ['', [], null]);

        self::assertTrue($controller->zustaendig($kontext('-10')));
        self::assertFalse($controller->zustaendig($kontext('1')));
        self::assertSame('', $controller->zeige($kontext('-10'))->html, 'ohne Liga leer');
        self::assertSame('', $this->seite([], 1), 'Hilfsadmins sehen nichts');
    }

    public function testAnzeigeDerLiga(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<strong>1. T2&nbsp;&nbsp;01.02.2025 - 02.02.2025</strong> / 1 T5003', $html);
        self::assertStringContainsString('<td>' . date('d.m.Y H:i', 1738422000) . '</td>', $html);
        self::assertStringContainsString('name="sp_1_1_2"', $html);
        self::assertStringContainsString('<option value=2 selected>2. T2</option>', $html, 'im zweiten Spieltag dieser gewaehlt');
        self::assertStringContainsString("<td align='right' >__</td>", $html, 'nicht gespielt');
        self::assertMatchesRegularExpression('~<td>Gamma</td>\s*<td align=\'right\' colspan=3>T5008</td>~', $html, 'spielfrei am ersten Spieltag');
        self::assertMatchesRegularExpression('~<td>Alpha</td>\s*<td align=\'right\' colspan=3>T5008</td>~', $html, 'spielfrei am zweiten Spieltag');
        self::assertSame([], $this->wuensche, 'ohne Aenderung nichts verschoben');
    }

    public function testVerschieben(): void
    {
        $danach = Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", "[Options]\nType=0\nTeams=3\nRounds=2\nMatches=2\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n\n[Round1]\nD1=\n\n[Round2]\nTA1=2\nTB1=3\nTA2=1\nTB2=2\n")));
        $html = $this->seite(['sp_1_1_2' => '2', 'sp_2_2_3' => '2', 'xyz_1_2_3' => '1'], 2, self::LIGA, $danach);

        self::assertSame([['a.l98', [[1, 2, 1, 2]]]], $this->wuensche, 'nur Auswahlen mit anderem Spieltag');
        self::assertStringContainsString('AUSGABE ', $html);
        self::assertStringContainsString('T5007 Alpha - Beta T5004 1. T5005 2 . T5010 T5006.<BR>T5007 Beta - Gamma T5004 2. T5005 1 . T5010 T5006.<BR>', $html, 'jede Verschiebung');
        self::assertStringContainsString('<td><strong>Alpha</strong></td>', $html, 'verschobene Partie fett');
        self::assertStringContainsString('/ 2 T5003', $html, 'angezeigt wird die Liga nach dem Verschieben');
        self::assertStringContainsString('/ 0 T5003', $html);
    }

    public function testImPokalLeer(): void
    {
        self::assertSame('', $this->seite(['sp_1_1_2' => '2'], 2, self::POKAL), 'ein Spiel wuerde aus seiner Paarung gerissen');
        self::assertSame([], $this->wuensche);
    }

    public function testWertungAmGruenenTischAlsNullZuNull(): void
    {
        $html = $this->seite([], 2, str_replace("GA1=-1\nGB1=-1", "GA1=-2\nGB1=0", self::LIGA));

        self::assertStringContainsString("<td align='right' >0</td>", $html);
    }
}
