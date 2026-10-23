<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\MannschaftenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Mannschaften einer Liga: Formular, Speichern, Hinzufuegen und Entfernen.
 */
class MannschaftenControllerTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga & Co\nType=0\nTeams=5\nRounds=8\nMatches=2\nMinusPoints=2\n\n"
        . "[Teams]\n1=Borussia Dortmund\n2=Schalke\n3=Bayern\n4=Werder\n5=HSV\n\n"
        . "[Team1]\nSP=-3\nNOT=Notiz mit Ü\n\n[Teamk]\n1=BVB\n";

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, string $liga = self::LIGA, array $konfiguration = []): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $texte[5001] = 'T5001';
        $texte[5002] = 'T5002';
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98', 'st' => '-2']),
            '',
            new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), ''),
            new Konfiguration($konfiguration),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(
                static fn (string $d, string $n, string $a): string => '[klein ' . $n . ']',
                wappenGross: static fn (string $d, string $n, string $a): string => '[gross ' . $n . ']'
            ),
            sitzung: new Sitzung(['lmouserok' => 2])
        );
        $controller = new MannschaftenController(function (Liga $liga, string $datei, string $st): array {
            $this->gespeichert[] = [$liga, $datei, $st];

            return ['GESPEICHERT ', $liga];
        });
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testFormular(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<h1>Liga & Co</h1>', $html, 'der Name wie bisher unmaskiert');
        self::assertStringContainsString('name="xteams1" size="32" maxlength="64" value="Borussia Dortmund"', $html);
        self::assertStringContainsString('name="xteamk1" size="5" maxlength="5" value="BVB"', $html);
        self::assertStringContainsString('name="xstrafp1" size="1" maxlength="4" value="3"', $html, 'Strafe mit umgekehrtem Vorzeichen');
        self::assertStringContainsString('name="xstrafm1"', $html, 'mit Minuspunkten');
        self::assertStringContainsString('value="Notiz mit &Uuml;"', $html, 'Notiz wie bisher mit htmlentities');
        self::assertStringNotContainsString("document.getElementById('n1')", $html, 'eine Notiz ist sichtbar');
        self::assertStringContainsString("document.getElementById('n2')", $html, 'leere Notiz nur als +');
        self::assertStringContainsString("onclick=\"return dteamlmolink(this.href,'Schalke');\"", $html);
        self::assertStringContainsString('[klein Bayern]', $html);
        self::assertStringContainsString('[gross Bayern]', $html);
        self::assertStringNotContainsString('st=-3', $html, 'ohne Aenderung kein Link zur Anzahl');
        self::assertSame([], $this->gespeichert);
    }

    public function testPokal(): void
    {
        $html = $this->seite([], "[Options]\nName=Pokal\nType=1\nTeams=4\n\n[Teams]\n1=A\n2=B\n3=C\n4=D\n");

        self::assertStringNotContainsString('xstrafp', $html);
        self::assertStringNotContainsString('xteamn', $html, 'keine Notiz');
        self::assertStringNotContainsString('dteamlmolink', $html, 'nicht entfernen');
        self::assertStringContainsString('<td class="nobr" colspan="2" align="right">', $html);
    }

    public function testSpeichern(): void
    {
        $html = $this->seite(['save' => '1', 'xteams1' => ' Borussia Dortmund 09 ', 'xteamm1' => '', 'xteamk1' => '', 'xstrafp1' => '2', 'xteamu1' => ' http://bvb.de ',
            'xteams2' => '', 'xteamm2' => 'S04', 'xteamk2' => 'S']);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        $liga = $this->gespeichert[0][0];
        $bvb = $liga->mannschaft(1);
        self::assertSame(['Borussia Dortmund 09', 'Dortmund', 'Dortm'], [$bvb->name, $bvb->mittel, $bvb->kurz], 'Mittel- und Kurzname aus dem laengsten Teil');
        self::assertSame('-2', $bvb->werte['SP'] ?? null);
        self::assertSame('http://bvb.de', $bvb->werte['URL'] ?? null);
        self::assertSame('Schalke', $liga->mannschaft(2)->name, 'leer gelassen: der bisherige Name');
        self::assertStringNotContainsString('st=-3', $html, 'ohne Torstatistik kein Link zur Anzahl');

        self::assertStringNotContainsString('st=-3" onclick', $this->seite(['save' => '1'], self::LIGA, ['einzutore' => '1']), 'auch nicht mit Torstatistik (bis 4.5 ein Seiteneffekt von lmo-zustat.php)');
    }

    public function testHinzufuegenUndEntfernen(): void
    {
        $neu = $this->seite(['team' => '-1']);
        self::assertSame(6, $this->gespeichert[0][0]->einstellungen()->mannschaften());
        self::assertStringContainsString('st=-3" onclick="return chklmolink();" title="T339">T338</a>', $neu, 'der Weg zur Anzahl der Spieltage');

        $this->seite(['team' => '2']);
        self::assertSame(4, $this->gespeichert[1][0]->einstellungen()->mannschaften());
        self::assertSame('Bayern', $this->gespeichert[1][0]->mannschaft(2)->name, 'die folgenden ruecken nach');

        $vier = "[Options]\nName=Vier\nType=0\nTeams=4\n\n[Teams]\n1=A\n2=B\n3=C\n4=D\n";
        $this->seite(['team' => '1'], $vier);
        self::assertCount(2, $this->gespeichert, 'eine Liga behaelt mindestens vier Mannschaften');
    }
}
