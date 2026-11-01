<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AdminEmailController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Newsletter/Reminder in der Administration: das Formular mit Tippern und getippten Ligen, der
 * Versand nach alter Art ueber die Bruecke.
 */
class AdminEmailControllerTest extends TestCase
{
    private string $ordner;

    /** @var list<array{string, array<string, mixed>}> eingebundene Dateien nach alter Art */
    private array $eingebunden = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tippemail-' . uniqid();
        mkdir($this->ordner . '/ligen', 0777, true);
        mkdir($this->ordner . '/daten', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\nType=0\nTeams=4\nRounds=3\nActual=2\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=Pokal\nType=1\nTeams=4\nActual=1\n");
        file_put_contents($this->ordner . '/ligen/nicht.l98', "[Options]\nName=Ohne Tipps\nTeams=2\nRounds=1\n");
        file_put_contents($this->ordner . '/daten/lmo-tippauth.txt', "<?php exit; ?>\n"
            . "zora|HASH|5|Zora Z|zora@example.org||||1|1|EOL\n"
            . "anna|HASH|5|Anna A|anna@example.org||||1|1|EOL\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, mixed> $anfrage */
    private function seite(array $anfrage, int $rolle = 2, string $ligenzutippen = 'bula,cup', string $erinnerung = ''): ?string
    {
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        $t = [];
        for ($i = 0; $i <= 310; $i++) {
            $t[$i] = 'P' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tippemail']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'tipp' => ['ligenzutippen' => $ligenzutippen, 'immeralle' => '0', 'tippauthtxt' => 'lmo-tippauth.txt', 'textreminder1' => $erinnerung]]),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminEmailController('/addon/tipp', $this->ordner . '/daten', function (string $datei, array $variablen): string {
            $this->eingebunden[] = [$datei, $variablen];

            return 'VERSCHICKT ';
        });

        return $controller->zustaendig($kontext) ? $controller->zeige($kontext)->html : null;
    }

    public function testNurHauptadmin(): void
    {
        self::assertNull($this->seite([], 1));
    }

    public function testFormular(): void
    {
        $html = (string) $this->seite([]);

        self::assertSame([], $this->eingebunden);
        self::assertStringContainsString('<h1>P165</h1>', $html);
        self::assertMatchesRegularExpression('/value="anna">anna \(Anna A - anna@example.org\)<\/option>\s*<option value="zora">/', $html, 'nach Nick sortiert');
        self::assertStringContainsString('name="liga1[]" value="bula.l98"', $html);
        self::assertStringContainsString('name="liganr" value="1" checked', $html, 'die erste Liga');
        self::assertStringContainsString('<option value="2" selected>2. T2</option>', $html, 'ihr aktueller Spieltag');
        self::assertStringContainsString('<option value="0">P229</option><option value="1" selected>T373</option><option value="2">T374</option>', $html, 'im Pokal Halbfinale und Finale');
        self::assertStringNotContainsString('Ohne Tipps', $html);
        self::assertMatchesRegularExpression('/name="liganr" value="0"\s+onClick/', $html, 'alle Ligen');
        self::assertStringContainsString('name="ende" size="2" maxlength="4" value="2"', $html, 'so viele Tipper');
        self::assertStringContainsString('name="tage" size="2" maxlength="2" value="4"', $html);
        self::assertStringContainsString('name="textreminder1" value="P174"', $html);
        self::assertMatchesRegularExpression('/<textarea id="message" name="message" rows="10" cols="60">P295\s*<\/textarea>/', $html);
    }

    public function testGespeicherteErinnerung(): void
    {
        $html = (string) $this->seite(['emailart' => '1', 'tipp_textreminder1' => 'aus dem Formular'], 2, 'bula', 'Bitte &quot;tippen&quot;&#10;[spiele]');

        self::assertStringContainsString('name="textreminder1" value="Bitte &quot;tippen&quot;&#10;[spiele]"', $html, 'bis 4.5 immer der vorgegebene');
        self::assertStringContainsString('cols="60">Bitte &quot;tippen&quot;&#10;[spiele]', $html);
    }

    public function testErinnerungVon45AbgeschnittenDannVorgegeben(): void
    {
        $html = (string) $this->seite([], 2, 'bula', 'Erste Zeile&#10');

        self::assertStringContainsString('name="textreminder1" value="P174"', $html);
    }

    public function testOhneLigenKeinAlle(): void
    {
        self::assertStringNotContainsString('name="liganr"', (string) $this->seite([], 2, ''));
    }

    public function testNewsletter(): void
    {
        $html = (string) $this->seite(['save' => '1', 'emailart' => '0', 'betreff' => '', 'message' => "Hallo <b>\n[nick]", 'start' => '1', 'ende' => '2']);

        self::assertSame([['/addon/tipp/lmo-tippemail.php', ['save' => '1', 'message' => "Hallo <b>\n[nick]", 'betreff' => 'P0', 'emailart' => '0',
            'adressat' => '', 'start' => '1', 'ende' => '2', 'tage' => '4']]], $this->eingebunden);
        self::assertStringStartsWith('VERSCHICKT ', $html);
        self::assertStringContainsString('name="emailart" value="0" checked', $html);
        self::assertStringContainsString('name="betreff" id="betreff" size="20" maxlength="40" value="P0"', $html, 'ohne Betreff der Name des Tippspiels');
        self::assertStringContainsString("cols=\"60\">Hallo &lt;b&gt;\n[nick]", $html, 'maskiert (bis 4.5 nicht)');
    }

    public function testErinnerungEinerLiga(): void
    {
        $html = (string) $this->seite(['save' => '1', 'emailart' => '1', 'liganr' => '2', 'liga1' => ['bula.l98', 'cup.l98'], 'st1' => ['2', '2'],
            'tage' => '9', 'betreff' => 'Tippen', 'message' => "Bitte\n[spiele]"]);

        self::assertSame(['st' => '2', 'liga' => 'cup.l98', 'viewermode' => 0], array_slice($this->eingebunden[0][1], -3));
        self::assertStringContainsString('name="liganr" value="2" checked', $html);
        self::assertStringNotContainsString('name="liganr" value="1" checked', $html);
        self::assertStringContainsString('<option value="2" selected>T374</option>', $html, 'die gewaehlte Runde');
        self::assertStringContainsString('name="textreminder1" value="Bitte&#10;[spiele]"', $html, 'als neuer Text der Erinnerung');
        self::assertStringContainsString('cols="60">Bitte&#10;[spiele]', $html);
        self::assertStringContainsString('name="tage" size="2" maxlength="2" value="9"', $html);
    }

    public function testErinnerungAllerLigen(): void
    {
        $html = (string) $this->seite(['save' => '1', 'emailart' => '1', 'liganr' => '0', 'liga1' => ['bula.l98', 'cup.l98'], 'st1' => ['2', '1'], 'message' => 'X']);

        self::assertSame(['st' => 0, 'file' => '', 'viewermode' => 1], array_slice($this->eingebunden[0][1], -3));
        self::assertStringContainsString('name="liganr" value="0" checked', $html);
        self::assertStringNotContainsString('name="liganr" value="1" checked', $html, 'keine einzelne Liga');
    }
}
