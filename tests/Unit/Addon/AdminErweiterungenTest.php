<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Addons;
use Lmo\Addon\AlteAdminSeite;
use Lmo\Addon\AltesAdminAddon;
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitSchaltern;
use Lmo\Addon\Schalter;
use Lmo\Addon\Spieler\SpielerAddon;
use Lmo\Addon\Tipp\TippAddon;
use Lmo\Addon\Viewer\ViewerAddon;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Was Addons zur Administration beitragen: Punkte im Hauptmenue und in der Navigation einer Liga
 * (MitAdminMenue) und eigene Seiten (MitAdminSeiten), noch nach alter Art ueber AlteAdminSeite.
 */
class AdminErweiterungenTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param array<string, mixed> $konfiguration
     */
    private static function kontext(array $parameter = [], array $konfiguration = ['eintippspiel' => '1', 'einspieler' => '1'], int $rolle = 2): Kontext
    {
        $texte = static function (string $praefix): array {
            $t = [];
            for ($i = 0; $i <= 310; $i++) {
                $t[$i] = $praefix . $i;
            }

            return $t;
        };

        return new Kontext(
            new Anfrage($parameter + ['action' => 'admin']),
            '',
            null,
            new Konfiguration($konfiguration),
            [],
            '/lmoadmin.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte('T'), 'viewer' => $texte('V'), 'spieler' => $texte('S'), 'pdf' => $texte('P')],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
    }

    /**
     * @param list<Menuepunkt> $punkte
     * @return list<array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function punkte(array $punkte): array
    {
        return array_map(static fn (Menuepunkt $p): array => [$p->ziel, $p->name, $p->titel, $p->aktiv], $punkte);
    }

    private static function addons(): Addons
    {
        return (new Addons([new SpielerAddon('/x/daten/spieler', '/x/spieler'), new TippAddon('/x/tipp'), new ViewerAddon('/x/viewer')]))
            ->mit(new AltesAdminAddon('pdf', 'pdfoptions', '/x/pdf/lmo-adminpdfoptions.inc.php', 200, 201));
    }

    public function testHauptmenueInDerReihenfolgeDerAddons(): void
    {
        self::assertSame([
            ['tipp', 'T0', 'T57', false],
            ['vieweroptions', 'V20', 'V21', false],
            ['pdfoptions', 'P200', 'P201', false],
        ], self::punkte(self::addons()->adminMenue(self::kontext())));

        self::assertSame(['tipp', 'vieweroptions', 'pdfoptions'], array_map(static fn (Menuepunkt $p): string => $p->ziel, self::addons()->adminMenue(self::kontext(['todo' => 'tippuser']))));
        self::assertTrue(self::addons()->adminMenue(self::kontext(['todo' => 'tippuser']))[0]->aktiv, 'auf allen Seiten des Tippspiels');
        self::assertSame(['vieweroptions', 'pdfoptions'], array_map(static fn (Menuepunkt $p): string => $p->ziel, self::addons()->adminMenue(self::kontext([], ['eintippspiel' => '0']))), 'Tippspiel abgeschaltet');
    }

    public function testStartseite(): void
    {
        self::assertSame([['tipp', 'T115', '', false]], self::punkte(self::addons()->startseite(self::kontext())), 'nur das Tippspiel');
        self::assertSame([], self::addons()->startseite(self::kontext([], ['eintippspiel' => '0'])));
    }

    public function testNavigationEinerLiga(): void
    {
        self::assertSame([['-4', 'S18', 'S1', false]], self::punkte(self::addons()->ligaMenue(self::kontext(['todo' => 'edit', 'st' => '3']))));
        self::assertTrue(self::addons()->ligaMenue(self::kontext(['todo' => 'edit', 'st' => '-4']))[0]->aktiv);
        self::assertSame([], self::addons()->ligaMenue(self::kontext([], ['einspieler' => '0'])), 'Spielerstatistik abgeschaltet');
    }

    public function testSeitenDerAddons(): void
    {
        $seite = static function (Kontext $kontext): ?string {
            foreach (self::addons()->adminController() as $controller) {
                if ($controller->zustaendig($kontext) && $controller instanceof AlteAdminSeite) {
                    return $controller->datei();
                }
            }

            return null;
        };

        self::assertSame('/x/tipp/lmo-admintippoptions.php', $seite(self::kontext(['todo' => 'tippoptions'])));
        self::assertSame('/x/tipp/lmo-admintipp.php', $seite(self::kontext(['todo' => 'tipp'])));
        self::assertNull($seite(self::kontext(['todo' => 'tipp'], rolle: 1)), 'nur fuer den Hauptadmin');
        self::assertSame('/x/viewer/lmo-adminvieweroptions.php', $seite(self::kontext(['todo' => 'vieweroptions'])));
        self::assertSame('/x/pdf/lmo-adminpdfoptions.inc.php', $seite(self::kontext(['todo' => 'pdfoptions'])));
        self::assertSame('/x/spieler/lmo-statadmin.php', $seite(self::kontext(['todo' => 'edit', 'st' => '-4'], rolle: 1)), 'auch fuer Hilfsadmins');
        self::assertNull($seite(self::kontext(['todo' => 'edit', 'st' => '-4'], ['einspieler' => '0'])), 'abgeschaltet: die Ergebnisse');
        self::assertNull($seite(self::kontext(['todo' => 'edit', 'st' => '1'])));
    }

    public function testAlteAdminSeiteBindetDieDateiEin(): void
    {
        $datei = sys_get_temp_dir() . '/lmo-alteadmin-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($datei, '<?php echo "Seite " . $lmo_test_wert;');
        $GLOBALS['lmo_test_wert'] = 'mit globalen Werten';
        $seite = new AlteAdminSeite(static fn (Kontext $kontext): bool => true, $datei);

        self::assertSame('Seite mit globalen Werten', $seite->zeige(self::kontext())->html);
        unset($GLOBALS['lmo_test_wert']);
        unlink($datei);
    }

    public function testSchalterVonSpielerUndTipp(): void
    {
        $schalter = self::addons()->schalter(self::kontext());

        self::assertSame(['spieler', 'tipp'], array_keys($schalter));
        self::assertSame(['einspieler', 'S18', 'S60', true], [$schalter['spieler'][0]->schluessel, $schalter['spieler'][0]->name, $schalter['spieler'][0]->titel, $schalter['spieler'][0]->vorgabe]);
        self::assertSame(['eintippspiel', 'T0', 'T306', true], [$schalter['tipp'][0]->schluessel, $schalter['tipp'][0]->name, $schalter['tipp'][0]->titel, $schalter['tipp'][0]->vorgabe]);

        // aus: im Addon, bis 4.5 in config/cfg.txt; ohne beides an
        self::assertSame(['vieweroptions', 'pdfoptions'], array_map(static fn (Menuepunkt $p): string => $p->ziel, self::addons()->adminMenue(self::kontext([], ['eintippspiel' => '1', 'tipp' => ['eintippspiel' => '0']]))), 'im Addon aus, das gilt vor config/cfg.txt');
        self::assertFalse(TippAddon::an(new Konfiguration(['eintippspiel' => '0'])));
        self::assertTrue(TippAddon::an(new Konfiguration(['eintippspiel' => '0', 'tipp' => ['eintippspiel' => '1']])));
        self::assertTrue(SpielerAddon::an(new Konfiguration([])));
        self::assertSame([], (new SpielerAddon('/x/daten/spieler', '/x/spieler'))->ligaMenue(self::kontext(['st' => '-4'], ['spieler' => ['einspieler' => '0']])));
    }

    public function testAuswertungBeimSpeichernAllerAddons(): void
    {
        $auswertung = new class () implements \Lmo\Addon\MitErgebnisAuswertung {
            public function name(): string
            {
                return 'demo';
            }

            public function auswertungBeimSpeichern(Kontext $kontext): array
            {
                return ['/x/demo/auswertung.php'];
            }
        };

        self::assertSame(['/x/demo/auswertung.php'], (new Addons([new ViewerAddon('/x/viewer'), $auswertung]))->auswertungBeimSpeichern(self::kontext()));
        self::assertSame([], self::addons()->auswertungBeimSpeichern(self::kontext()), 'ohne Liga wertet das Tippspiel nichts aus');
    }

    public function testLigaEinstellungen(): void
    {
        $schalter = self::addons()->ligaEinstellungen(self::kontext());
        self::assertSame([['mittore', 'S18', 'S19']], array_map(static fn (Schalter $s): array => [$s->schluessel, $s->name, $s->titel], $schalter), 'Spielerstatistik, wenn eingeschaltet');

        self::assertSame([], self::addons()->ligaEinstellungen(self::kontext([], ['spieler' => ['einspieler' => '0']])), 'abgeschaltet nichts');
    }

    public function testSchalterJeAddon(): void
    {
        $mitSchalter = new class () implements MitSchaltern {
            public function name(): string
            {
                return 'demo';
            }

            public function schalter(Kontext $kontext): array
            {
                return [new Schalter('eindemo', 'Demo', 'Demo zeigen?', true)];
            }
        };
        $ohneSchalter = new class () implements MitSchaltern {
            public function name(): string
            {
                return 'leer';
            }

            public function schalter(Kontext $kontext): array
            {
                return [];
            }
        };

        $schalter = (new Addons([$ohneSchalter, $mitSchalter, new ViewerAddon('/x/viewer')]))->schalter(self::kontext());

        self::assertSame(['demo'], array_keys($schalter), 'nur Addons mit Schaltern');
        self::assertSame('eindemo', $schalter['demo'][0]->schluessel);
    }
}
