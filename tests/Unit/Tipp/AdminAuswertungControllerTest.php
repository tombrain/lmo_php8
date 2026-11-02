<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AdminAuswertungController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Auswertung des Tippspiels in der Administration: getippte Ligen, Auswahl der Spieltage,
 * von/bis der Tipper und die Auswertung nach alter Art ueber die Bruecke.
 */
class AdminAuswertungControllerTest extends TestCase
{
    private string $ordner;

    /** @var list<array{string, array<string, mixed>}> eingebundene Dateien nach alter Art */
    private array $eingebunden = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tippadmin-' . uniqid();
        mkdir($this->ordner . '/ligen', 0777, true);
        mkdir($this->ordner . '/daten/tipps', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\nType=0\nTeams=4\nRounds=3\nActual=2\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=\nType=1\nTeams=8\nRounds=3\nActual=1\n");
        file_put_contents($this->ordner . '/ligen/nicht.l98', "[Options]\nName=Ohne Tipps\nTeams=2\nRounds=1\n");
        foreach (['bula_anna.tip', 'bula_bert.tip', 'Bula_cleo.tip', 'cup_anna.tip', 'bula_anna.txt'] as $datei) {
            file_put_contents($this->ordner . '/daten/tipps/' . $datei, '');
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): ?string
    {
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        $t = [];
        for ($i = 0; $i <= 300; $i++) {
            $t[$i] = 'P' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tipp']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'tipp' => ['ligenzutippen' => 'bula,cup', 'immeralle' => '0', 'gesamt' => '1', 'dirtipp' => 'tipps/']]),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminAuswertungController('/addon/tipp', $this->ordner . '/daten', function (string $datei, array $variablen): string {
            $this->eingebunden[] = [$datei, $variablen];

            return 'AUSGEWERTET ';
        });

        return $controller->zustaendig($kontext) ? $controller->zeige($kontext)->html : null;
    }

    public function testNurHauptadmin(): void
    {
        self::assertNull($this->seite([], 1));
    }

    public function testLigen(): void
    {
        $html = (string) $this->seite([]);

        self::assertStringContainsString('<td align="center">P63 </td>', $html, 'Menue: die Auswertung ohne Link');
        self::assertStringContainsString("<a href='/lmoadmin.php?action=admin&amp;todo=tippemail'", $html);
        self::assertSame(2, substr_count($html, '>Bundesliga</td>'), 'je einmal fuer Auswertung und Einsicht');
        self::assertSame(2, substr_count($html, '>T5071</td>'), 'Pokal ohne Namen');
        self::assertStringNotContainsString('Ohne Tipps', $html, 'nur getippte Ligen');
        self::assertMatchesRegularExpression('~name="liga" value="bula">\s*<select class="lmo-formular-input" name="st">\s*<option value="0">P228</option>\s*<option value="1">1. T2</option>\s*<option value="2" selected>2. T2</option>~', $html, 'Auswertung: alle Spieltage, der aktuelle gewaehlt');
        self::assertMatchesRegularExpression('~name="liga" value="cup">\s*<select class="lmo-formular-input" name="st">\s*<option value="1" selected>T372</option>\s*<option value="2">T373</option>\s*<option value="3">T374</option>~', $html, 'Pokal: Runden mit Namen, ohne "alle"');
        self::assertStringContainsString('name="ende" size="2" maxlength="4" value="3"', $html, 'bis: Tippdateien der Liga');
        self::assertStringContainsString('name="save" value="4"', $html, 'Gesamtwertung');
        self::assertSame([], $this->eingebunden);
    }

    public function testAuswerten(): void
    {
        mkdir($this->ordner . '/daten/tipps/auswert');
        $html = (string) $this->seite(['save' => '2', 'liga' => 'bula', 'st' => '3', 'start' => '2', 'ende' => '2']);

        self::assertStringStartsWith('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> P0: P29 <var>bula</var> P65</p>', $html);
        self::assertSame([], $this->eingebunden, 'nicht mehr nach alter Art');
        self::assertFileExists($this->ordner . '/daten/tipps/auswert/bula.aus');
        self::assertMatchesRegularExpression('~name="save" value="2">\s*<input type="hidden" name="liga" value="bula">.*?<option value="3" selected>.*?name="start" size="2" maxlength="4" value="2">.*?name="ende" size="2" maxlength="4" value="2">~s', $html, 'die gewaehlten Werte bleiben stehen');
        self::assertMatchesRegularExpression('~name="save" value="3">\s*<input type="hidden" name="liga" value="bula">.*?<option value="2" selected>~s', $html, 'nicht in der Einsicht');
    }

    public function testEinsichtUndGesamt(): void
    {
        $this->seite(['save' => '3', 'liga' => 'cup']);
        $this->seite(['save' => '4']);
        $this->seite(['save' => '5']);

        self::assertSame(['/addon/tipp/lmo-tippsaveeinsicht.php', '/addon/tipp/lmo-tippsavewertgesamt.php'], array_column($this->eingebunden, 0));
    }
}
