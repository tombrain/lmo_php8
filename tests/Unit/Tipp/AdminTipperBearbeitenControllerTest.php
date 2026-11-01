<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AdminTipperBearbeitenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Einen Tipper in der Administration bearbeiten und anlegen.
 */
class AdminTipperBearbeitenControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tipperedit-' . uniqid();
        mkdir($this->ordner . '/ligen', 0777, true);
        mkdir($this->ordner . '/daten/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=Pokal\n");
        file_put_contents($this->ordner . '/daten/lmo-tippauth.txt', "<?php exit; ?>\n"
            . "anna|HASH|5|Anna Alt|anna@example.org|FC A|Weg 1|12345|Ort|1|-1|EOL\n"
            . "bert|HASH2||Bert Beispiel|bert@example.org|||||1|1|EOL\n");
        file_put_contents($this->ordner . '/daten/tipps/bula_anna.tip', '');
        file_put_contents($this->ordner . '/daten/tipps/cup_anna.tip', '');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, mixed> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): ?string
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
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tippuseredit']),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'tipp' => ['dirtipp' => 'tipps/', 'tippauthtxt' => 'lmo-tippauth.txt', 'immeralle' => '1', 'tipperimteam' => '0']]),
            $text,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminTipperBearbeitenController($this->ordner . '/daten');

        return $controller->zustaendig($kontext) ? $controller->zeige($kontext)->html : null;
    }

    private function tipper(): string
    {
        return (string) file_get_contents($this->ordner . '/daten/lmo-tippauth.txt');
    }

    public function testNurHauptadminUndMitNickOderNeu(): void
    {
        self::assertNull($this->seite(['nick' => 'anna'], 1));
        self::assertSame('', $this->seite([]));
    }

    public function testBearbeiten(): void
    {
        $html = (string) $this->seite(['nick' => 'anna']);

        self::assertStringContainsString('<h1>P106</h1>', $html);
        self::assertStringContainsString('<input type="hidden" name="nick" value="anna"><strong>anna</strong>', $html);
        self::assertStringContainsString('name="xtippervorname" size="25" maxlength="32" value="Anna"', $html);
        self::assertStringContainsString('name="xtippernachname" size="25" maxlength="32" value="Alt"', $html);
        self::assertStringContainsString('name="xfrei" checked', $html);
        self::assertStringNotContainsString('name="xremind" checked', $html);
        self::assertStringContainsString('<option value="FC A" selected>FC A [1]</option>', $html, 'ihr Verein');
        self::assertStringContainsString('value="1" id="1" checked', $html);
        self::assertStringContainsString('value="bula"checked>Bundesliga<br>', $html, 'wie bisher ohne Leerzeichen');
        self::assertStringContainsString('<input type="hidden" name="newpage" value="0">', $html);
    }

    public function testSpeichern(): void
    {
        $html = (string) $this->seite(['nick' => 'anna', 'newpage' => '0', 'xtipperpass' => '', 'xtippervorname' => 'Anna', 'xtippernachname' => 'Neu',
            'xtipperemail' => 'neu@example.org', 'xremind' => 'on', 'xtippervereinradio' => '2', 'xtippervereinneu' => 'FC N', 'xtipperligen' => ['bula']]);

        self::assertStringContainsString('T5 P111 P53', $html);
        self::assertStringContainsString("anna|HASH||Anna Neu|neu@example.org|FC N||||-1|1|EOL\n", $this->tipper(), 'ohne Kennwort das bisherige, ohne Haken nicht freigeschaltet');
        self::assertFileExists($this->ordner . '/daten/tipps/bula_anna.tip');
        self::assertFileDoesNotExist($this->ordner . '/daten/tipps/cup_anna.tip', 'abgewaehlt');
    }

    public function testNeuerTipper(): void
    {
        $formular = (string) $this->seite(['save' => '-1']);
        self::assertStringContainsString('<h1>P136</h1>', $formular);
        self::assertMatchesRegularExpression('/name="xtipperpass" value="[a-z0-9]{8,16}"/', $formular, 'ein Kennwort zum Vorschlagen');
        self::assertStringContainsString('<input type="hidden" name="newpage" value="1">', $formular);

        $this->seite(['nick' => 'cleo', 'newpage' => '1', 'xtipperpass' => 'geheim', 'xtippervorname' => 'Cleo', 'xtippernachname' => 'Neu',
            'xtipperemail' => 'cleo@example.org', 'xfrei' => 'on', 'xtippervereinradio' => '0', 'xtipperligen' => ['cup']]);

        $zeile = explode("\n", $this->tipper())[3];
        self::assertStringStartsWith('cleo|', $zeile);
        self::assertTrue(password_verify('geheim', explode('|', $zeile)[1]));
        self::assertStringEndsWith('|5|Cleo Neu|cleo@example.org|||||-1|-1|EOL', $zeile);
        self::assertFileExists($this->ordner . '/daten/tipps/cup_cleo.tip');
        self::assertStringContainsString("[cleo]\nTeam=", (string) file_get_contents($this->ordner . '/daten/tipps/auswert/cup.aus'));
    }

    public function testNeuerTipperBeginntLeer(): void
    {
        $html = (string) $this->seite(['nick' => 'cleo', 'newpage' => '1', 'xtipperpass' => '', 'xtippervorname' => 'Cleo', 'xtippernachname' => 'Neu', 'xtippervereinradio' => '0']);

        self::assertStringContainsString('P69', $html, 'ohne Kennwort nicht angelegt (bis 4.5 mit dem Kennwort von bert)');
        self::assertStringNotContainsString('cleo|', $this->tipper());
        self::assertStringNotContainsString('bert@example.org', $html, 'nichts vom letzten Tipper im Formular');
    }

    public function testFehler(): void
    {
        $doppelt = (string) $this->seite(['nick' => 'bert', 'newpage' => '1', 'xtippervorname' => 'B', 'xtippernachname' => 'B', 'xtippervereinradio' => '0']);
        self::assertStringContainsString('P24', $doppelt, 'Nick schon vergeben');

        $name = (string) $this->seite(['nick' => 'dora', 'newpage' => '1', 'xtippervorname' => 'Do Ra', 'xtippernachname' => 'X', 'xtippervereinradio' => '2', 'xtippervereinneu' => 'FC A']);
        self::assertStringContainsString('P109', $name, 'Leerzeichen im Namen');
        self::assertStringContainsString('P143', $name, 'Verein gibt es schon');
        self::assertStringNotContainsString('dora|', $this->tipper());
    }
}
