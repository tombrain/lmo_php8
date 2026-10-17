<?php

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Addon;
use Lmo\Addon\Addons;
use Lmo\Addon\AltesSpielInclude;
use Lmo\Addon\MitSeiten;
use Lmo\Addon\MitZusatzJeSpiel;
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

    public function testBrueckeZumAltenInclude(): void
    {
        $datei = $this->datei('stats/lmo-showprogram.inc.php', '<?php echo "Partie $i, Spieltag $st, " . $text[1] . ", Heim " . $lmo_spiel->heim;');
        $bruecke = new AltesSpielInclude('stats', $datei);

        self::assertSame('stats', $bruecke->name());
        self::assertSame('Partie 0, Spieltag 1, Eins, Heim 1', $bruecke->zusatzJeSpiel(self::spiel(), self::kontext()));
    }
}
