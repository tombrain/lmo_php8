<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\RegistrierungController;
use Lmo\Addon\Tipp\Tipperdatei;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Registrierung eines neuen Tippers.
 */
class RegistrierungControllerTest extends TestCase
{
    private string $ordner;

    /** @var list<string> verschickte Mails, kurz beschrieben */
    private array $mails = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-registrierung-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert', 0777, true);
        mkdir($this->ordner . '/ligen');
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]\nName=Bundesliga\n");
        file_put_contents($this->ordner . '/ligen/cup.l98', "[Options]\nName=Pokal\n");
        file_put_contents($this->ordner . '/auth.txt', "<?php exit; ?>\nanna|x|5|Anna A|anna@example.org|Eins||||1|1|EOL\n");
    }

    protected function tearDown(): void
    {
        foreach (['/tipps/auswert/*', '/tipps/*', '/ligen/*', '/*'] as $muster) {
            array_map(static fn (string $pfad): bool => is_dir($pfad) ? rmdir($pfad) : unlink($pfad), glob($this->ordner . $muster) ?: []);
        }
        rmdir($this->ordner);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, string> $tipp
     * @return array{0: string, 1: Sitzung}
     */
    private function seite(array $parameter, array $tipp = []): array
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $sitzung = new Sitzung(['lmotipperok' => 5, 'lmotippername' => 'vorher']);
        $kontext = new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'newtipper']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => $tipp + ['tippauthtxt' => 'auth.txt', 'dirtipp' => 'tipps/', 'immeralle' => '1', 'realname' => '1',
                'adresse' => '0', 'tipperimteam' => '-1', 'freischaltcode' => '0', 'freischaltung' => '0', 'mailbeianmeldung' => '1']]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: $sitzung
        );
        $controller = new RegistrierungController(
            $this->ordner,
            function (string $email, string $nick, string $kennwort): string {
                $this->mails[] = 'Freischaltung ' . $email . ' ' . $nick . ' ' . $kennwort;

                return '[Freischaltmail]';
            },
            function (string $nick): string {
                $this->mails[] = 'Admin ' . $nick;

                return '';
            }
        );
        self::assertTrue($controller->zustaendig($kontext));

        return [$controller->zeige($kontext)->html, $sitzung];
    }

    /** @return array<string, mixed> */
    private static function nora(): array
    {
        return ['newpage' => '1', 'xtippernick' => 'nora', 'xtippervorname' => 'Nora', 'xtippernachname' => 'Neu', 'xtipperemail' => 'nora@example.org',
            'xtipperpass' => 'neu123', 'xtipperpassw' => 'neu123'];
    }

    public function testFormular(): void
    {
        [$html, $sitzung] = $this->seite([]);

        self::assertStringContainsString('<input type="checkbox" name="xtipperligen[]" value="bula"checked>Bundesliga<br>', $html, 'alle Ligen angekreuzt');
        self::assertStringContainsString('name="xtippervorname"', $html);
        self::assertSame(0, $sitzung->zahl('lmotipperok'), 'nach der Registrierung nicht angemeldet');
    }

    public function testFehler(): void
    {
        [$html] = $this->seite(['xtippernick' => 'An_na', 'xtipperemail' => 'ANNA@example.org', 'xtipperpass' => 'ab', 'xtipperpassw' => 'xy', 'xtipperligen' => ['cup']] + self::nora());

        self::assertStringContainsString('> T201</p>', $html, 'E-Mail-Adresse schon vergeben');
        self::assertStringContainsString('> T109</p>', $html, 'Nick nur aus Buchstaben und Ziffern');
        self::assertStringContainsString('> T73</p>', $html, 'Kennwort zu kurz');
        self::assertStringContainsString('> T70</p>', $html);
        self::assertStringContainsString('value="cup"checked>', $html);
        self::assertStringContainsString('value="bula">', $html, 'die Auswahl bleibt');
        self::assertStringContainsString('> T24</p>', $this->seite(['xtippernick' => 'ANNA'] + self::nora())[0], 'Nick schon vergeben');
        self::assertSame([], $this->mails);
    }

    public function testNeuerTipper(): void
    {
        [$html, $sitzung] = $this->seite(['xtipperligen' => ['bula', '../boese']] + self::nora());

        self::assertStringContainsString('> T138</p>', $html);
        self::assertStringContainsString('> T20</p>', $html);
        $tipper = Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('nora');
        self::assertNotNull($tipper);
        self::assertTrue($tipper->pruefeKennwort('neu123'));
        self::assertStringEndsWith('|5|Nora&nbsp;Neu|nora@example.org|||||1|1|EOL', $tipper->zeile());
        self::assertSame("\n", file_get_contents($this->ordner . '/tipps/bula_nora.tip'));
        self::assertFileDoesNotExist($this->ordner . '/tipps/cup_nora.tip');
        self::assertFileDoesNotExist($this->ordner . '/boese_nora.tip', 'nur Ligen aus der Liste');
        self::assertSame("\n[nora]\nTeam=\nName=Nora&nbsp;Neu\n", file_get_contents($this->ordner . '/tipps/auswert/bula.aus'));
        self::assertSame("\n[nora]\nTeam=\nName=Nora&nbsp;Neu\n", file_get_contents($this->ordner . '/tipps/auswert/gesamt.aus'));
        self::assertSame(['Admin nora'], $this->mails);
        self::assertSame(['nora', 0], [$sitzung->text('lmotippername'), $sitzung->zahl('lmotipperok')]);
    }

    public function testMitFreischaltung(): void
    {
        [$html] = $this->seite(['xtipperemail2' => 'nora@example.org', 'xtipperpass' => ''] + self::nora(), ['freischaltcode' => '1', 'freischaltung' => '1', 'mailbeianmeldung' => '0']);

        self::assertStringContainsString('[Freischaltmail]', $html);
        $tipper = Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('nora');
        self::assertStringContainsString('||Nora&nbsp;Neu|', (string) $tipper?->zeile(), 'noch nicht freigeschaltet');
        self::assertSame(1, preg_match('/^Freischaltung nora@example.org nora (\S+)$/', $this->mails[0] ?? '', $treffer));
        self::assertTrue($tipper?->pruefeKennwort($treffer[1]), 'das Kennwort aus der Mail');
    }
}
