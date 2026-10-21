<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Addon\Addons;
use Lmo\Addon\MitSeiten;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Anwendung;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\LigaLader;
use Lmo\Web\Pfade;
use Lmo\Web\Seite;
use PHPUnit\Framework\TestCase;

/**
 * Der Einstieg: welche Seite gewaehlt ist, welche Liga geladen wird und welcher Controller die
 * Seite zeigt.
 */
class AnwendungTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-anwendung-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
        file_put_contents($this->ordner . '/a.l98', "[Options]\nName=Liga A\nOnRun=1\nnamePkt=Zaehler\n");
    }

    protected function tearDown(): void
    {
        unlink($this->ordner . '/a.l98');
        rmdir($this->ordner);
    }

    /** Ein Controller, der fuer eine action zustaendig ist und den Kontext festhaelt. */
    private static function controller(string $action, ?Kontext &$gesehen = null): Controller
    {
        return new class ($action, $gesehen) implements Controller {
            /** @param Kontext|null $gesehen wird ueber die Referenz vom Test gelesen */
            public function __construct(private string $action, public ?Kontext &$gesehen)
            {
            }

            public function zustaendig(Kontext $kontext): bool
            {
                return $kontext->action === $this->action;
            }

            public function zeige(Kontext $kontext): Seite
            {
                $this->gesehen = $kontext;

                return new Seite('Seite ' . $this->action, true);
            }
        };
    }

    /**
     * @param list<Controller> $controller
     * @param list<Controller> $zuletzt
     */
    private function anwendung(array $controller, ?Addons $addons = null, array $zuletzt = []): Anwendung
    {
        return new Anwendung(
            new Konfiguration([]),
            new \Lmo\Web\Sprachtexte([37 => 'Punkte'], ['date' => [1 => 'Montag']]),
            new LigaLader($this->ordner . '/', 'd.m.Y'),
            new Pfade($this->ordner, $this->ordner, 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            $controller,
            $addons ?? new Addons(),
            $zuletzt
        );
    }

    public function testDerZustaendigeControllerZeigtDieSeite(): void
    {
        $gesehen = null;
        $anwendung = $this->anwendung([self::controller('cross'), self::controller('table', $gesehen)]);

        $seite = $anwendung->bearbeite(new Anfrage(['file' => 'a.l98', 'action' => 'table', 'st' => '3']), '/lmo.php');

        self::assertNotNull($seite);
        self::assertSame('Seite table', $seite->html);
        self::assertNotNull($gesehen);
        self::assertSame('Liga A', $gesehen->liga?->liga->optionen['Name']);
        self::assertSame('3', $gesehen->anfrage->text('st'));
        self::assertSame('/lmo.php', $gesehen->adresse);
        self::assertSame('Zaehler', $gesehen->text[37], 'Sprachtexte fuer die Liga');
    }

    public function testSeitenDerAddonsNachDenenDesKerns(): void
    {
        $gesehen = null;
        $addon = new class (self::controller('spieler', $gesehen), self::controller('table')) implements MitSeiten {
            public function __construct(private \Lmo\Web\Controller\Controller $spieler, private \Lmo\Web\Controller\Controller $tabelle)
            {
            }

            public function name(): string
            {
                return 'spieler';
            }

            public function controller(): array
            {
                return [$this->spieler, $this->tabelle];
            }
        };
        $kern = null;
        $anwendung = $this->anwendung([self::controller('table', $kern)], new Addons([$addon]));

        $seite = $anwendung->bearbeite(new Anfrage(['file' => 'a.l98', 'action' => 'spieler']), '/lmo.php');
        self::assertSame('Seite spieler', $seite?->html);
        self::assertTrue($gesehen?->addons->hat('spieler'), 'der Kontext kennt die Addons');

        $anwendung->bearbeite(new Anfrage(['file' => 'a.l98', 'action' => 'table']), '/lmo.php');
        self::assertNotNull($kern, 'der Kern geht vor');
    }

    public function testAuffangseitenNachDenenDerAddons(): void
    {
        $auffang = new class () implements Controller {
            public function zustaendig(Kontext $kontext): bool
            {
                return $kontext->liga === null;
            }

            public function zeige(Kontext $kontext): Seite
            {
                return new Seite('Ligenuebersicht', true);
            }
        };
        $addon = new class (self::controller('demo')) implements MitSeiten {
            public function __construct(private Controller $demo)
            {
            }

            public function name(): string
            {
                return 'demo';
            }

            public function controller(): array
            {
                return [$this->demo];
            }
        };
        $anwendung = $this->anwendung([self::controller('table')], new Addons([$addon]), [$auffang]);

        self::assertSame('Seite demo', $anwendung->bearbeite(new Anfrage(['action' => 'demo']), '/lmo.php')?->html, 'die Seite des Addons ohne Liga geht vor');
        self::assertSame('Ligenuebersicht', $anwendung->bearbeite(new Anfrage(['action' => 'program']), '/lmo.php')?->html, 'sonst die Auffangseite');
        self::assertSame('Seite table', $anwendung->bearbeite(new Anfrage(['file' => 'a.l98', 'action' => 'table']), '/lmo.php')?->html);
    }

    public function testOhneZustaendigenControllerNull(): void
    {
        $anwendung = $this->anwendung([self::controller('table')]);

        self::assertNull($anwendung->bearbeite(new Anfrage(['file' => 'a.l98', 'action' => 'program']), '/lmo.php'));
    }

    public function testVorgegebeneDateiGehtVor(): void
    {
        $gesehen = null;
        $anwendung = $this->anwendung([self::controller('table', $gesehen)]);

        $anwendung->bearbeite(new Anfrage(['file' => 'fehlt.l98', 'action' => 'table']), '/lmo.php', 'a.l98');

        self::assertSame('a.l98', $gesehen?->liga?->datei, 'eine einbindende Seite setzt $file');
    }

    public function testOhneLigaKeineLiga(): void
    {
        $gesehen = null;
        $anwendung = $this->anwendung([self::controller('table', $gesehen)]);

        $anwendung->bearbeite(new Anfrage(['file' => 'fehlt.l98', 'action' => 'table']), '/lmo.php');

        self::assertNotNull($gesehen);
        self::assertNull($gesehen->liga);
        self::assertSame('Punkte', $gesehen->text[37]);
    }

    public function testGewaehlteSeite(): void
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'OnRun=3']));

        self::assertSame('table', Anwendung::action(new Anfrage(['action' => 'table']), $liga));
        self::assertSame('', Anwendung::action(new Anfrage(['action' => 'admin']), $liga), 'admin ist hier keine Seite');
        self::assertSame('cross', Anwendung::action(new Anfrage([]), $liga), 'Startseite der Liga (OnRun)');
        self::assertSame('cross', Anwendung::action(new Anfrage(['action' => '']), $liga));
        self::assertSame('', Anwendung::action(new Anfrage([]), null), 'ohne Liga keine Startseite');
        self::assertSame('', Anwendung::action(new Anfrage([]), Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'OnRun=9']))));
    }

    public function testStartseiteOhneAction(): void
    {
        $anwendung = $this->anwendung([self::controller('table')]);

        self::assertSame('Seite table', $anwendung->bearbeite(new Anfrage(['file' => 'a.l98']), '/lmo.php')?->html, 'OnRun=1: Tabelle');
    }

    public function testFehlendeStartseiteWirdZurTabelle(): void
    {
        file_put_contents($this->ordner . '/s.l98', "[Options]
Name=Liga S
OnRun=7
");
        $gesehen = null;
        $anwendung = $this->anwendung([self::controller('table', $gesehen)]);

        // OnRun=7 ist die Spielerstatistik; ohne deren Addon ist niemand zustaendig
        self::assertSame('Seite table', $anwendung->bearbeite(new Anfrage(['file' => 's.l98']), '/lmo.php')?->html);
        self::assertSame('table', $gesehen?->action, 'Menue und Rahmen sehen die Tabelle');
        self::assertNull($anwendung->bearbeite(new Anfrage(['file' => 's.l98', 'action' => 'spieler']), '/lmo.php'), 'eine gewaehlte Seite bleibt, wie sie ist');

        $mitAddon = $this->anwendung([self::controller('table'), self::controller('spieler')]);
        self::assertSame('Seite spieler', $mitAddon->bearbeite(new Anfrage(['file' => 's.l98']), '/lmo.php')?->html, 'mit Addon die eingestellte Startseite');
        unlink($this->ordner . '/s.l98');
    }
}
