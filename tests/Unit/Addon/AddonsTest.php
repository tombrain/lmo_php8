<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Addon;
use Lmo\Addon\Addons;
use Lmo\Addon\AltesSeitenInclude;
use Lmo\Addon\AltesSpielInclude;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Addon\MitZusatzJeSpiel;
use Lmo\Addon\MitZusatzUnterSeite;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Seite;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Die Addons einer Installation: Laden aus dem Ordner und die Erweiterungspunkte.
 */
class AddonsTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-addons-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->ordner . '/*/*') ?: [] as $datei) {
            unlink($datei);
        }
        foreach (glob($this->ordner . '/*') ?: [] as $unterordner) {
            is_dir($unterordner) ? rmdir($unterordner) : unlink($unterordner);
        }
        rmdir($this->ordner);
    }

    private function datei(string $name, string $inhalt): string
    {
        $pfad = $this->ordner . '/' . $name;
        if (!is_dir(dirname($pfad))) {
            mkdir(dirname($pfad), 0777, true);
        }
        file_put_contents($pfad, $inhalt);

        return $pfad;
    }

    private static function kontext(): Kontext
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Teams=2', 'Rounds=1', 'Matches=1', '[Round1]', 'TA1=1', 'TB1=2']));

        return new Kontext(
            new Anfrage([]),
            'results',
            new GeladeneLiga('x.l98', $liga, ''),
            new Konfiguration([]),
            [1 => 'Eins'],
            '/lmo.php',
            new Pfade('/lmo', '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
        );
    }

    private static function spiel(): Spiel
    {
        return Spiel::ausRohdaten(self::kontext()->liga->liga, 1, 1);
    }

    /** Ein Addon mit Zusatz je Spiel, der seinen Namen und die Partie zeigt. */
    private static function zusatz(string $name): Addon
    {
        return new class ($name) implements MitZusatzJeSpiel {
            public function __construct(private string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function zusatzJeSpiel(Spiel $spiel, Kontext $kontext): string
            {
                return '[' . $this->name . ' ' . $spiel->partie . ']';
            }
        };
    }

    public function testLadenAusDemOrdner(): void
    {
        $this->datei('beta/addon.php', '<?php return new class implements \Lmo\Addon\Addon { public function name(): string { return "beta"; } };');
        $this->datei('alpha/addon.php', '<?php return new class implements \Lmo\Addon\Addon { public function name(): string { return "alpha"; } };');
        $this->datei('alt/lmo-alt.php', '<?php echo "alte Art";');
        $this->datei('readme.txt', 'kein Addon');

        $addons = Addons::ausOrdner($this->ordner);

        self::assertSame(['alpha', 'beta'], array_map(static fn (Addon $a): string => $a->name(), $addons->alle()), 'alphabetisch, ohne Ordner alter Art');
        self::assertTrue($addons->hat('beta'));
        self::assertFalse($addons->hat('alt'));
        self::assertSame([], Addons::ausOrdner($this->ordner . '/fehlt')->alle());
    }

    public function testAddonPhpOhneAddon(): void
    {
        $this->datei('kaputt/addon.php', '<?php return 42;');

        $this->expectException(UnexpectedValueException::class);
        Addons::ausOrdner($this->ordner);
    }

    public function testControllerDerAddonsMitSeiten(): void
    {
        $controller = new class implements Controller {
            public function zustaendig(Kontext $kontext): bool
            {
                return true;
            }

            public function zeige(Kontext $kontext): Seite
            {
                return new Seite('');
            }
        };
        $seiten = new class ($controller) implements MitSeiten {
            public function __construct(private Controller $controller)
            {
            }

            public function name(): string
            {
                return 'seiten';
            }

            public function controller(): array
            {
                return [$this->controller];
            }
        };

        $addons = new Addons([self::zusatz('ohne'), $seiten]);

        self::assertSame([$controller], $addons->controller());
    }

    public function testZusatzJeSpielHintereinander(): void
    {
        $addons = new Addons([self::zusatz('a')]);
        $mit = $addons->mit(self::zusatz('b'));

        self::assertTrue($addons->mitZusatzJeSpiel());
        self::assertSame('[a 1]', $addons->zusatzJeSpiel(self::spiel(), self::kontext()));
        self::assertSame('[a 1][b 1]', $mit->zusatzJeSpiel(self::spiel(), self::kontext()));
        self::assertFalse((new Addons())->mitZusatzJeSpiel());
        self::assertSame('', (new Addons())->zusatzJeSpiel(self::spiel(), self::kontext()));
    }

    public function testZusatzUnterDerSeite(): void
    {
        $unter = new class implements MitZusatzUnterSeite {
            public function name(): string
            {
                return 'unter';
            }

            public function zusatzUnterSeite(Kontext $kontext): string
            {
                return '<p>' . $kontext->action . '</p>';
            }
        };

        self::assertSame('<p>results</p>', (new Addons([self::zusatz('a'), $unter]))->zusatzUnterSeite(self::kontext()));
        self::assertSame('', (new Addons())->zusatzUnterSeite(self::kontext()));
    }

    public function testBrueckeZumAltenSeitenInclude(): void
    {
        $datei = $this->datei('pdf/lmo-showprogram.inc.php', '<?php echo "Plan $selteam aus $file, " . $text[1] . ", " . $lmo_liga->einstellungen()->spieltage();');
        $bruecke = new AltesSeitenInclude('pdf', 'results', $datei);
        $kontext = self::kontext();
        $mitTeam = new Kontext(new Anfrage(['selteam' => '2', 'file' => 'fremd.l98']), $kontext->action, $kontext->liga, $kontext->konfiguration, $kontext->text, $kontext->adresse, $kontext->pfade, $kontext->darstellung);

        self::assertSame('pdf', $bruecke->name());
        self::assertSame('Plan 2 aus x.l98, Eins, 1', $bruecke->zusatzUnterSeite($mitTeam), 'Parameter als Variablen, $file aber die geladene Liga');
        self::assertSame('', (new AltesSeitenInclude('pdf', 'program', $datei))->zusatzUnterSeite($mitTeam), 'nur unter seiner Seite');
    }

    public function testRahmenteileHintereinander(): void
    {
        $teil = static fn (string $name, array $teile): Addon => new class ($name, $teile) implements MitRahmenteilen {
            /** @param array<string, string> $teile */
            public function __construct(private string $name, private array $teile)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function rahmenteile(Kontext $kontext): array
            {
                return $this->teile;
            }
        };
        $addons = new Addons([$teil('a', ['Newsticker' => '<a>', 'Tippspiel' => 'T']), self::zusatz('ohne'), $teil('b', ['Newsticker' => '<b>'])]);

        self::assertSame(['Newsticker' => '<a><b>', 'Tippspiel' => 'T'], $addons->rahmenteile(self::kontext()));
        self::assertSame([], (new Addons())->rahmenteile(self::kontext()));
    }

    public function testBrueckeZumAltenInclude(): void
    {
        $datei = $this->datei('stats/lmo-showprogram.inc.php', '<?php echo "Partie $i, Spieltag $st ($j), " . $text[1] . ", Heim " . $lmo_spiel->heim;');
        $bruecke = new AltesSpielInclude('stats', $datei);

        self::assertSame('stats', $bruecke->name());
        self::assertSame('Partie 0, Spieltag 1 (0), Eins, Heim 1', $bruecke->zusatzJeSpiel(self::spiel(), self::kontext()));
    }
}
