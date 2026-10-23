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

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2, string $liga = self::LIGA): string
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
        $controller = new SpieltageZuordnenController(
            function (Liga $liga, string $datei, string $st): array {
                $this->gespeichert[] = [$liga, $datei, $st];

                return ['GESPEICHERT ', $liga];
            },
            static fn (Liga $liga): array => ['', $liga]
        );
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
        $controller = new SpieltageZuordnenController(static fn (Liga $l): array => ['', $l], static fn (Liga $l): array => ['', $l]);

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
        self::assertSame([], $this->gespeichert, 'ohne Aenderung nichts gespeichert');
    }

    public function testVerschieben(): void
    {
        $html = $this->seite(['sp_1_1_2' => '2', 'sp_2_3_1' => '1', 'xyz_1_2_3' => '1']);

        self::assertStringContainsString('<input type="hidden" name="st" value="-10">' . "\nGESPEICHERT ", $html, 'die Meldung des Speicherns im Formular wie bisher die von writeFile()');
        [$liga, $datei, $st] = $this->gespeichert[0];
        self::assertSame(['a.l98', '0'], [$datei, $st], 'mit dem bisherigen aktuellen Spieltag');
        self::assertSame(['2', '3', '1', '2'], [$liga->spieltage[2]->partien[1]->heim, $liga->spieltage[2]->partien[1]->gast, $liga->spieltage[2]->partien[2]->heim, $liga->spieltage[2]->partien[2]->gast], 'Spieltag 2 mit der verschobenen Partie hinten');
        self::assertSame([], $liga->spieltage[1]->partien, 'Spieltag 1 ohne sie');
        self::assertSame('2', $liga->optionen['Matches'], 'Spieltag 2 hat nun zwei Partien');
        self::assertStringContainsString('T5007 Alpha - Beta T5004 1. T5005 2 . T5010 T5006.<BR>', $html, 'die gelungene Verschiebung');
        self::assertStringNotContainsString('Gamma - Alpha', $html, 'eine Partie, die es dort nicht gibt, bleibt ohne Meldung');
        self::assertStringContainsString('<td><strong>Alpha</strong></td>', $html, 'verschobene Partie fett');
        self::assertStringContainsString('/ 2 T5003', $html, 'angezeigt wird die Liga nach dem Verschieben');
    }

    public function testMehrereVerschiebungenJeEineMeldung(): void
    {
        $html = $this->seite(['sp_1_1_2' => '2', 'sp_2_2_3' => '1']);

        self::assertStringContainsString('T5007 Alpha - Beta T5004 1. T5005 2 . T5010 T5006.<BR>T5007 Beta - Gamma T5004 2. T5005 1 . T5010 T5006.<BR>', $html);
    }

    public function testLeerePlaetzeOhneZeile(): void
    {
        $html = $this->seite([], 2, str_replace("[Round2]\n", "[Round2]\nTA2=0\nTB2=0\n", str_replace('Matches=1', 'Matches=2', self::LIGA)));

        self::assertStringNotContainsString('name="sp_2__"', $html);
        self::assertStringContainsString('<strong>2. T2&nbsp;&nbsp;</strong> / 1 T5003', $html, 'zaehlen nicht mit');
    }

    public function testImPokalLeer(): void
    {
        self::assertSame('', $this->seite(['sp_1_1_2' => '2'], 2, self::POKAL), 'ein Spiel wuerde aus seiner Paarung gerissen');
        self::assertSame([], $this->gespeichert);
    }

    public function testWertungAmGruenenTischAlsNullZuNull(): void
    {
        $html = $this->seite([], 2, str_replace("GA1=-1\nGB1=-1", "GA1=-2\nGB1=0", self::LIGA));

        self::assertStringContainsString("<td align='right' >0</td>", $html);
    }
}
