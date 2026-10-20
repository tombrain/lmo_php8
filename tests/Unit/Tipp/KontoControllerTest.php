<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\KontoController;
use Lmo\Addon\Tipp\Tipperdatei;
use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Kennwort aendern im Tippspiel (todo=pwchange).
 */
class KontoControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tipp-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
        file_put_contents($this->ordner . '/auth.txt', "<?php exit; ?>\ntester|" . password_hash('geheim', PASSWORD_BCRYPT) . "|5|Tina Test|t@example.org|Eins||||1|1|EOL\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/*') ?: []);
        rmdir($this->ordner);
    }

    /**
     * @param array<string, string> $parameter
     * @param array<string, mixed> $sitzung
     */
    private function kontext(array $parameter, array $sitzung = ['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => 'Eins']): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 400; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => ['tippauthtxt' => 'auth.txt', 'regeln' => '0']]),
            $texte,
            '/lmo.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung($sitzung)
        );
    }

    private function controller(): KontoController
    {
        return new KontoController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
    }

    public function testZustaendig(): void
    {
        $c = $this->controller();

        self::assertTrue($c->zustaendig($this->kontext(['todo' => 'pwchange'])));
        self::assertFalse($c->zustaendig($this->kontext(['todo' => 'daten'])), 'andere Seite');
        self::assertFalse($c->zustaendig($this->kontext(['todo' => 'pwchange'], ['lmotipperok' => '0'])), 'nicht angemeldet');
        self::assertFalse($c->zustaendig($this->kontext(['todo' => 'pwchange', 'viewermode' => '1'])));
    }

    public function testTipperNichtInDerDatei(): void
    {
        $html = $this->controller()->zeige($this->kontext(['todo' => 'pwchange'], ['lmotipperok' => '5', 'lmotippername' => 'fremd']))->html;

        self::assertStringContainsString('<h1>T0 </h1>', $html, 'Rahmen ohne Inhalt');
        self::assertStringNotContainsString('xtipperpass', $html);
    }

    public function testFormularImRahmen(): void
    {
        $html = $this->controller()->zeige($this->kontext(['todo' => 'pwchange']))->html;

        self::assertStringContainsString('<caption>tester - Eins</caption>', $html);
        self::assertStringContainsString('name="xtipperpassneu"', $html);
        self::assertStringContainsString('<a href="/lmo.php?action=tipp" title="T53">T52</a>', $html, 'Menue: zur Uebersicht');
        self::assertStringContainsString('<a href="/lmo.php?action=tipp&amp;todo=logout">T88</a>', $html);
        self::assertStringContainsString('<h1>T0 </h1>', $html);
    }

    public function testFehler(): void
    {
        $falsch = $this->controller()->zeige($this->kontext(['todo' => 'pwchange', 'newpage' => '1', 'xtipperpass' => 'falsch', 'xtipperpassneu' => 'a"b', 'xtipperpassneuw' => 'a"b']))->html;
        self::assertStringContainsString('wrong.svg" border="0" width="20" alt=""> T42</p>', $falsch);
        self::assertStringContainsString('value="a&quot;b"', $falsch, 'Eingaben maskiert');

        $kurz = $this->controller()->zeige($this->kontext(['todo' => 'pwchange', 'newpage' => '1', 'xtipperpass' => 'geheim', 'xtipperpassneu' => 'ab', 'xtipperpassneuw' => 'xy']))->html;
        self::assertStringContainsString('> T73</p>', $kurz, 'zu kurz');
        self::assertStringContainsString('> T70</p>', $kurz, 'Wiederholung anders');
        self::assertTrue(Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('tester')?->pruefeKennwort('geheim'), 'nichts gespeichert');
    }

    public function testKennwortGeaendert(): void
    {
        $html = $this->controller()->zeige($this->kontext(['todo' => 'pwchange', 'newpage' => '1', 'xtipperpass' => ' geheim ', 'xtipperpassneu' => 'anders', 'xtipperpassneuw' => 'anders']))->html;

        self::assertStringContainsString('right.svg" border="0" width="20" alt=""> T138</p>', $html, 'gespeichert');
        self::assertStringContainsString('> T121</p>', $html);
        self::assertStringNotContainsString('name="xtipperpassneu"', $html, 'kein Formular mehr');
        $datei = Tipperdatei::lies($this->ordner . '/auth.txt');
        self::assertTrue($datei->tipper('tester')?->pruefeKennwort('anders'));
        self::assertStringStartsWith("<?php exit; ?>\n", (string) file_get_contents($this->ordner . '/auth.txt'), 'Schutzzeile bleibt');
    }
}
