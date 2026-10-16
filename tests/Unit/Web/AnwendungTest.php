<?php

namespace Lmo\Tests\Unit\Web;

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
            public function __construct(private string $action, private ?Kontext &$gesehen)
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

    /** @param list<Controller> $controller */
    private function anwendung(array $controller): Anwendung
    {
        return new Anwendung(
            new Konfiguration([]),
            [37 => 'Punkte'],
            new LigaLader($this->ordner . '/', 'd.m.Y'),
            new Pfade($this->ordner, $this->ordner, 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            $controller
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
}
