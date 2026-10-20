<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AnmeldungController;
use Lmo\Addon\Tipp\StartseiteController;
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
 * Anmelden, Abmelden und Kennwort vergessen im Tippspiel.
 */
class AnmeldungControllerTest extends TestCase
{
    private string $ordner;

    /** @var list<array{string, string, string}> E-Mail-Adresse, Nick, Kennwort */
    private array $mails = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-anmeldung-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps', 0777, true);
        file_put_contents($this->ordner . '/auth.txt', "<?php exit; ?>\n"
            . 'tester|' . password_hash('geheim', PASSWORD_BCRYPT) . "|5|Tina|t@example.org||||||1|1|EOL\n"
            . "alt|klartext|5|Alt|a@example.org||||||1|1|EOL\n"
            . 'neu|' . password_hash('x', PASSWORD_BCRYPT) . "||Neu|n@example.org||||||1|1|EOL\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/*.txt') ?: []);
        rmdir($this->ordner . '/tipps');
        rmdir($this->ordner);
    }

    /**
     * @param array<string, string> $parameter
     * @return array{0: string, 1: Sitzung}
     */
    private function seite(array $parameter, ?Sitzung $sitzung = null): array
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $sitzung ??= new Sitzung();
        $kontext = new Kontext(
            new Anfrage($parameter + ['action' => 'tipp']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => ['tippauthtxt' => 'auth.txt', 'dirtipp' => 'tipps/', 'immeralle' => '1', 'regeln' => '0', 'gesamt' => '1', 'nurgesamt' => '1']]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: $sitzung
        );
        $rahmen = new Tipprahmen(static fn (string $datei): string => '');
        $controller = new AnmeldungController($this->ordner, $rahmen, new StartseiteController($this->ordner, $rahmen), function (string $an, string $nick, string $kennwort): ?string {
            $this->mails[] = [$an, $nick, $kennwort];

            return null;
        });
        self::assertTrue($controller->zustaendig($kontext));

        return [$controller->zeige($kontext)->html, $sitzung];
    }

    public function testAnmeldeseite(): void
    {
        [$html, $sitzung] = $this->seite([]);

        self::assertStringContainsString('<h1>T0&nbsp;</h1>', $html);
        self::assertStringContainsString('name="xtippername" size="16" maxlength="32" value=""', $html);
        self::assertStringContainsString('name="todo" value="newtipper"', $html);
        self::assertStringContainsString('<li>[ T223 ]</li>', $html, 'keine Liga zu tippen');
        self::assertSame(0, $sitzung->zahl('lmotipperok'));
    }

    public function testAlleUebrigenSeiten(): void
    {
        // Gaeste sehen auf Seiten fuer Angemeldete, ohne Liga und in der Tippuebersicht die Anmeldeseite
        foreach ([['todo' => 'daten'], ['todo' => 'edit', 'file' => 'fehlt.l98'], ['todo' => 'edit', 'viewermode' => '1'], ['todo' => 'unbekannt']] as $parameter) {
            [$html] = $this->seite($parameter);
            self::assertStringContainsString('name="xtippername"', $html, implode(', ', $parameter));
        }

        // Angemeldete sehen ihre Startseite; "Uebersicht" ohne Link auf einer Ligenseite ohne Liga
        $angemeldet = new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => '']);
        [$ohneLiga] = $this->seite(['todo' => 'einsicht', 'file' => 'fehlt.l98'], $angemeldet);
        self::assertStringContainsString('todo=logout', $ohneLiga);
        self::assertStringNotContainsString('name="xtippername"', $ohneLiga);
        self::assertStringNotContainsString('title="T53">T52</a>', $ohneLiga);
        self::assertStringNotContainsString('file=fehlt.l98', $ohneLiga, 'keine Menuepunkte der Liga');
        [$unbekannt] = $this->seite(['todo' => 'unbekannt'], $angemeldet);
        self::assertStringContainsString('title="T53">T52</a>', $unbekannt, 'bei unbekanntem todo mit Link');
    }

    public function testAnmelden(): void
    {
        [$unbekannt, $sitzung] = $this->seite(['xtippername' => 'niemand', 'xtipperpass' => 'x']);
        self::assertSame(-2, $sitzung->zahl('lmotipperok'));
        self::assertStringContainsString('> T43</p>', $unbekannt);

        [$falsch, $sitzung] = $this->seite(['xtippername' => 'tester', 'xtipperpass' => 'falsch']);
        self::assertSame(-1, $sitzung->zahl('lmotipperok'));
        self::assertStringContainsString('> T42</p>', $falsch);
        self::assertStringContainsString('name="xtippername2" size="16" maxlength="32" value="tester"', $falsch, 'Nick fuer "Kennwort vergessen"');

        [$start, $sitzung] = $this->seite(['xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        self::assertSame(5, $sitzung->zahl('lmotipperok'));
        self::assertStringContainsString("<a href='/lmo.php?action=tipp&amp;todo=pwchange'>", $start, 'Startseite des Tippers');

        [$gesperrt, $sitzung] = $this->seite(['xtippername' => 'neu', 'xtipperpass' => 'x', 'xtippersub' => 'x']);
        self::assertSame('', $sitzung->text('lmotipperok'), 'noch nicht freigeschaltet');
        self::assertStringContainsString('> T148</p>', $gesperrt, 'die Anmeldeseite mit dem Hinweis');
        [, $sitzung] = $this->seite(['xtippername' => 'tester', 'xtipperpass' => 'geheim'], $sitzung);
        self::assertSame(5, $sitzung->zahl('lmotipperok'), 'danach geht eine andere Anmeldung');
    }

    public function testKennwortImKlartextWirdUmgestellt(): void
    {
        [, $sitzung] = $this->seite(['xtippername' => 'alt', 'xtipperpass' => 'klartext']);

        self::assertSame(5, $sitzung->zahl('lmotipperok'));
        $tipper = Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('alt');
        self::assertNotSame('klartext', $tipper?->kennwort());
        self::assertTrue($tipper?->pruefeKennwort('klartext'));
    }

    public function testAbmelden(): void
    {
        $sitzung = new Sitzung(['lmotipperok' => 5, 'lmotippername' => 'tester', 'lmotipperpass' => 'geheim', 'lmotipperverein' => 'Eins']);
        [$html] = $this->seite(['todo' => 'logout'], $sitzung);

        self::assertFalse($sitzung->gesetzt('lmotipperpass'), 'kein Kennwort in der Sitzung');
        self::assertStringContainsString('name="xtipperpass" size="16" maxlength="32" value=""', $html, 'kein Kennwort im Formular');
        self::assertStringContainsString('name="xtippername" size="16" maxlength="32" value="tester"', $html);

        self::assertSame(0, $sitzung->zahl('lmotipperok'));
        self::assertSame('', $sitzung->text('lmotipperverein'));
        self::assertStringContainsString('name="xtippername"', $html, 'wieder die Anmeldeseite');
    }

    public function testKennwortVergessen(): void
    {
        [$formular, $sitzung] = $this->seite(['todo' => 'getpass']);
        self::assertSame(0, $sitzung->zahl('lmotipperok'));
        self::assertStringContainsString('name="xtippername2"', $formular);

        [$unbekannt, $sitzung] = $this->seite(['todo' => 'getpass', 'xtippername2' => 'niemand']);
        self::assertSame(-3, $sitzung->zahl('lmotipperok'));
        self::assertSame([], $this->mails);
        self::assertStringContainsString('> T43</p>', $unbekannt);

        [$html, $sitzung] = $this->seite(['todo' => 'getpass', 'xtippername2' => 't@example.org']);
        self::assertStringStartsWith('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> T78</p>', $html);
        self::assertSame(0, $sitzung->zahl('lmotipperok'));
        $mail = $this->letzteMail();
        self::assertSame(['t@example.org', 'tester'], [$mail[0], $mail[1]], 'ueber die E-Mail-Adresse gefunden');
        self::assertTrue(Tipperdatei::lies($this->ordner . '/auth.txt')->tipper('tester')?->pruefeKennwort($mail[2]));
    }

    /** @return array{string, string, string} */
    private function letzteMail(): array
    {
        return end($this->mails) ?: ['', '', ''];
    }
}
