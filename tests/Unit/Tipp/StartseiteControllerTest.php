<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\StartseiteController;
use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Startseite eines angemeldeten Tippers.
 */
class StartseiteControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-start-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
        mkdir($this->ordner . '/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\nActual=5\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=Pokal\nType=1\nTeams=8\nActual=3\n");
        file_put_contents($this->ordner . '/ligen/ohne.l98', "[Options]\n");
        foreach (['bula', 'cup', 'ohne', 'weg'] as $liga) {
            touch($this->ordner . '/tipps/' . $liga . '_tester.tip', (int) mktime(12, 0, 0, 3, 10, 2025));
        }
        touch($this->ordner . '/tipps/bula_anna.tip');
        touch($this->ordner . '/tipps/auswert/bula.aus', (int) mktime(18, 30, 0, 3, 9, 2025));
    }

    protected function tearDown(): void
    {
        foreach (['/tipps/auswert/*', '/tipps/*', '/ligen/*'] as $muster) {
            array_map(static fn (string $pfad): bool => is_dir($pfad) ? rmdir($pfad) : unlink($pfad), glob($this->ordner . $muster) ?: []);
        }
        rmdir($this->ordner . '/tipps');
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    /** @param array<string, string> $tipp */
    private function seite(array $tipp, string $todo = ''): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'tipp', 'todo' => $todo]),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'defdateformat' => 'd.m.Y', 'tipp' => $tipp + ['dirtipp' => 'tipps/', 'immeralle' => '1', 'sttipp' => '1', 'viewertipp' => '1', 'viewertage' => '8', 'gesamt' => '1', 'nurgesamt' => '0', 'tipperimteam' => '-1', 'regeln' => '0']]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => ''])
        );
        $controller = new StartseiteController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testLigenUndAuswertungen(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<li><a href="/lmo.php?action=tipp&amp;todo=edit&amp;file=bula.l98">Bundesliga</a><br><small>T138 10.03.2025 / 5. T2</small></li>', $html);
        self::assertStringContainsString('file=cup.l98">Pokal</a><br><small>T138 10.03.2025 / T374</small>', $html, 'im Pokal die Runde: 3 von 3 ist das Finale');
        self::assertStringContainsString('file=ohne.l98">T5071</a>', $html, 'ohne Namen');
        self::assertStringNotContainsString('weg.l98', $html, 'Tippdatei ohne Liga');
        self::assertStringNotContainsString('anna', $html);
        self::assertStringContainsString('todo=edit&amp;file=&amp;viewermode=1"><strong>T252 8 T171</strong>', $html);
        self::assertStringContainsString('todo=wert&amp;file=bula.l98">Bundesliga</a><br><small>T83: 09.03.2025 18:30</small>', $html);
        self::assertStringContainsString('todo=wert&amp;file=&amp;all=1"><strong>T25</strong></a>', $html, 'Gesamtwertung bei mehreren Ligen');
    }

    public function testEinstellungen(): void
    {
        $nurGesamt = $this->seite(['nurgesamt' => '1']);
        self::assertStringNotContainsString('todo=wert&amp;file=bula.l98', $nurGesamt);
        self::assertStringContainsString('all=1', $nurGesamt);

        $ohneTippen = $this->seite(['sttipp' => '-1', 'viewertipp' => '0']);
        self::assertStringNotContainsString('todo=edit&amp;file=bula.l98', $ohneTippen, 'Tippabgabe abgeschaltet');
        self::assertStringNotContainsString('viewermode', $ohneTippen);

        $keine = $this->seite(['immeralle' => '0', 'ligenzutippen' => 'andere']);
        self::assertStringContainsString('<li>[T22]</li>', $keine, 'keine Liga zu tippen');
        self::assertStringNotContainsString('all=1', $keine);
    }
}
