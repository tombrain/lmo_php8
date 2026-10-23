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

    /** @var list<array{string, string, string}> verschickte Freischaltmails: E-Mail-Adresse, Nick, Kennwort */
    private array $mails = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tipp-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
        file_put_contents($this->ordner . '/auth.txt', "<?php exit; ?>\ntester|" . password_hash('geheim', PASSWORD_BCRYPT) . "|5|Tina Test|t@example.org|Eins||||1|1|EOL\nanna|x|5|Anna|a@example.org|||||1|1|EOL\n");
        mkdir($this->ordner . '/tipps');
        foreach (['bula_tester.tip', 'cup_tester.tip', 'bula_anna.tip', 'bula_xtester.tip'] as $tippdatei) {
            touch($this->ordner . '/tipps/' . $tippdatei);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/tipps/*') ?: []);
        rmdir($this->ordner . '/tipps');
        array_map('unlink', glob($this->ordner . '/*') ?: []);
        rmdir($this->ordner);
    }

    public function testPersoenlicheDaten(): void
    {
        $mit = ['realname' => '1', 'adresse' => '1', 'tipperimteam' => '2'];
        $formular = $this->controller()->zeige($this->kontext(['todo' => 'daten'], tipp: $mit))->html;
        self::assertStringContainsString('name="xtippervorname" size="25" maxlength="32" value="Tina"', $formular);
        self::assertStringContainsString('name="xtippernachname" size="25" maxlength="32" value="Test"', $formular);
        self::assertStringContainsString('<option value="Eins" selected>Eins [1]</option>', $formular, 'Verein mit Zahl seiner Tipper');
        self::assertStringContainsString('name="xtippervereinradio" value="1" checked>', $formular);
        self::assertStringContainsString('name="xnews" value="1" checked>', $formular);

        $daten = ['todo' => 'daten', 'newpage' => '1', 'xtippervorname' => 'Tina', 'xtippernachname' => 'Test', 'xtipperstrasse' => 'Weg 1',
            'xtipperplz' => '12345', 'xtipperort' => 'Ort', 'xtipperemail' => 't@example.org', 'xtippervereinradio' => '1', 'xtippervereinalt' => 'Eins', 'xremind' => '1'];
        $fehler = $this->controller()->zeige($this->kontext(['xtippervorname' => 'Ti na', 'xtipperemail' => 'kaputt', 'xtippervereinradio' => '2', 'xtippervereinneu' => 'Eins'] + $daten, tipp: $mit))->html;
        self::assertStringContainsString('> T109</p>', $fehler, 'Leerzeichen im Namen');
        self::assertStringContainsString('> T68</p>', $fehler, 'E-Mail-Adresse');
        self::assertStringContainsString('> T143</p>', $fehler, 'den Verein gibt es schon');
        self::assertStringContainsString('> T142</p>', $this->controller()->zeige($this->kontext($daten, tipp: ['tipperimteam' => '1'] + $mit))->html, 'Verein voll');

        $sitzung = new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => 'Eins']);
        $html = $this->controller()->zeige($this->kontext(['xtipperemail' => 'neu@example.org', 'xtippervereinradio' => '2', 'xtippervereinneu' => 'Zwei'] + $daten, $sitzung, $mit))->html;
        self::assertStringContainsString('> T138</p>[Mail an neu@example.org]', $html, 'gespeichert, dann die Freischaltmail');
        self::assertSame('Zwei', $sitzung->text('lmotipperverein'));
        $tipper = Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('tester');
        self::assertNotNull($tipper);
        self::assertStringEndsWith('|5|Tina Test|neu@example.org|Zwei|Weg 1|12345|Ort|-1|1|EOL', $tipper->zeile());
        self::assertSame([['neu@example.org', 'tester', $this->mails[0][2]]], $this->mails);
        self::assertTrue($tipper->pruefeKennwort($this->mails[0][2]), 'neues Kennwort aus der Mail, gespeichert als Hash');
    }

    public function testDatenOhneNamensfelder(): void
    {
        $html = $this->controller()->zeige($this->kontext(['todo' => 'daten', 'newpage' => '1', 'xtipperemail' => 't@example.org']))->html;

        self::assertStringContainsString('> T138</p>', $html);
        self::assertSame([], $this->mails, 'gleiche E-Mail-Adresse: keine Mail');
        $tipper = Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('tester');
        self::assertStringEndsWith('|5|Tina Test|t@example.org|||||-1|-1|EOL', (string) $tipper?->zeile(), 'Name bleibt; ohne Vereinswahl kein Verein');
        self::assertTrue($tipper?->pruefeKennwort('geheim'));
    }

    public function testLigenHinzufuegenUndEntfernen(): void
    {
        mkdir($this->ordner . '/ligen');
        mkdir($this->ordner . '/tipps/auswert');
        file_put_contents($this->ordner . '/ligen/bula.l98', "[Options]
Name=Bundesliga
");
        file_put_contents($this->ordner . '/ligen/zweite.l98', "[Options]
");
        file_put_contents($this->ordner . '/ligen/andere.l98', "[Options]
Name=Andere
");
        $tipp = ['ligenzutippen' => 'bula,zweite,cup'];
        try {
            $auswahl = $this->controller()->zeige($this->kontext(['todo' => 'newligen'], tipp: $tipp))->html;
            self::assertStringContainsString('value="zweite">T5071<br>', $auswahl, 'ohne Namen: Liga und Nummer');
            self::assertStringNotContainsString('value="bula"', $auswahl, 'schon getippt');
            self::assertStringNotContainsString('value="andere"', $auswahl, 'nicht zu tippen');

            $html = $this->controller()->zeige($this->kontext(['todo' => 'newligen', 'newpage' => '1', 'xtipperligen' => ['zweite', '../../boese']], tipp: $tipp))->html;
            self::assertStringContainsString('> T20</p>', $html);
            self::assertSame("
", file_get_contents($this->ordner . '/tipps/zweite_tester.tip'));
            self::assertSame("
[tester]
Team=Eins
Name=Tina Test
", file_get_contents($this->ordner . '/tipps/auswert/zweite.aus'));
            self::assertFileDoesNotExist($this->ordner . '/boese_tester.tip', 'nur Ligen aus der Liste');

            $entfernen = $this->controller()->zeige($this->kontext(['todo' => 'delligen'], tipp: $tipp))->html;
            self::assertStringContainsString('value="bula">Bundesliga<br>', $entfernen);
            self::assertStringContainsString('value="zweite">', $entfernen);
            $this->controller()->zeige($this->kontext(['todo' => 'delligen', 'newpage' => '1', 'xtipperligen' => ['zweite']], tipp: $tipp));
            self::assertFileDoesNotExist($this->ordner . '/tipps/zweite_tester.tip');
            self::assertFileExists($this->ordner . '/tipps/bula_tester.tip');

            $keine = $this->controller()->zeige($this->kontext(['todo' => 'newligen'], tipp: ['ligenzutippen' => 'bula']))->html;
            self::assertStringContainsString('<li>[ T223 ]</li>', $keine, 'keine Liga mehr zu tippen');
        } finally {
            array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
            rmdir($this->ordner . '/ligen');
            array_map('unlink', glob($this->ordner . '/tipps/auswert/*') ?: []);
            rmdir($this->ordner . '/tipps/auswert');
        }
    }

    public function testKontoLoeschen(): void
    {
        $sitzung = new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => 'Eins']);
        $formular = $this->controller()->zeige($this->kontext(['todo' => 'delaccount']))->html;
        self::assertStringContainsString('name="xtipperpass"', $formular);

        $falsch = $this->controller()->zeige($this->kontext(['todo' => 'delaccount', 'newpage' => '1', 'xtipperpass' => 'falsch'], $sitzung))->html;
        self::assertStringContainsString('> T42</p>', $falsch);
        self::assertNotNull(Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('tester'), 'nichts geloescht');

        $html = $this->controller()->zeige($this->kontext(['todo' => 'delaccount', 'newpage' => '1', 'xtipperpass' => 'geheim'], $sitzung))->html;
        self::assertStringContainsString('> T138</p>', $html);
        self::assertStringContainsString('<caption>tester</caption>', $html, 'abgemeldet, ohne Verein');
        self::assertSame(0, $sitzung->zahl('lmotipperok'));
        $datei = Tipperdatei::lies($this->ordner . '/auth.txt');
        self::assertNull($datei->tipper('tester'));
        self::assertNotNull($datei->tipper('anna'), 'die anderen bleiben');
        self::assertSame(['bula_anna.tip', 'bula_xtester.tip'], array_map('basename', glob($this->ordner . '/tipps/*') ?: []), 'nur die Tipps des Tippers geloescht');
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed>|Sitzung $sitzung
     * @param array<string, string> $tipp abweichende Einstellungen des Tippspiels
     */
    private function kontext(array $parameter, array|Sitzung $sitzung = ['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => 'Eins'], array $tipp = []): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => $tipp + ['tippauthtxt' => 'auth.txt', 'dirtipp' => 'tipps/', 'regeln' => '0', 'realname' => '0', 'adresse' => '0', 'tipperimteam' => '-1', 'freischaltcode' => '0']]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: $sitzung instanceof Sitzung ? $sitzung : new Sitzung($sitzung)
        );
    }

    private function controller(): KontoController
    {
        return new KontoController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''), function (string $email, string $nick, string $kennwort): string {
            $this->mails[] = [$email, $nick, $kennwort];

            return '[Mail an ' . $email . ']';
        });
    }

    public function testZustaendig(): void
    {
        $c = $this->controller();

        self::assertTrue($c->zustaendig($this->kontext(['todo' => 'pwchange'])));
        self::assertFalse($c->zustaendig($this->kontext(['todo' => 'edit'])), 'andere Seite');
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
