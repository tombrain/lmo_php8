<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\TippAddon;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Das Tippspiel als Addon: seine Controller und der Menuepunkt.
 */
class TippAddonTest extends TestCase
{
    /** @param array<string, mixed> $konfiguration */
    private static function kontext(string $action, ?string $datei, array $konfiguration): Kontext
    {
        return new Kontext(
            new Anfrage([]),
            $action,
            $datei !== null ? new GeladeneLiga($datei, new Liga(), '') : null,
            new Konfiguration($konfiguration),
            [],
            '/lmo.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => [0 => 'Tippspiel']]
        );
    }

    public function testControllerReihenfolge(): void
    {
        $addon = new TippAddon();
        $alle = $addon->controller();
        self::assertSame(
            [\Lmo\Addon\Tipp\RegistrierungController::class, \Lmo\Addon\Tipp\KontoController::class, \Lmo\Addon\Tipp\UebersichtController::class, \Lmo\Addon\Tipp\TippabgabeController::class, \Lmo\Addon\Tipp\EinsichtController::class, \Lmo\Addon\Tipp\TabelleController::class, \Lmo\Addon\Tipp\RanglisteController::class, \Lmo\Addon\Tipp\FieberkurveController::class, \Lmo\Addon\Tipp\InfoController::class, \Lmo\Addon\Tipp\StartseiteController::class, \Lmo\Addon\Tipp\AnmeldungController::class],
            array_map('get_class', $alle),
            'zuletzt die Anmeldung, die auch alle uebrigen Seiten zeigt'
        );
        $letzter = $alle[count($alle) - 1];
        $an = ['eintippspiel' => '1'];

        self::assertSame('tipp', $addon->name());
        self::assertTrue($letzter->zustaendig(self::kontext('tipp', null, $an)));
        self::assertFalse($letzter->zustaendig(self::kontext('tipp', null, ['eintippspiel' => '0'])), 'Tippspiel abgeschaltet');
        self::assertFalse($letzter->zustaendig(self::kontext('table', 'x.l98', $an)));
    }

    public function testMenuepunkt(): void
    {
        $addon = new TippAddon();
        $immer = ['eintippspiel' => '1', 'tipp' => ['immeralle' => '1', 'ligenzutippen' => '']];
        $auswahl = ['eintippspiel' => '1', 'tipp' => ['immeralle' => '0', 'ligenzutippen' => 'bula,cup']];

        self::assertSame(['Tippspiel' => '<a href="/lmo.php?file=archiv/x.l98&amp;action=tipp" title="Tippspiel">Tippspiel</a>'], $addon->rahmenteile(self::kontext('table', 'archiv/x.l98', $immer)));
        self::assertSame(['Tippspiel' => 'Tippspiel'], $addon->rahmenteile(self::kontext('tipp', null, $immer)), 'im Tippspiel ohne Link');
        self::assertArrayHasKey('Tippspiel', $addon->rahmenteile(self::kontext('table', 'bula.l98', $auswahl)), 'Liga zu tippen');
        self::assertSame([], $addon->rahmenteile(self::kontext('table', 'andere.l98', $auswahl)), 'Liga nicht zu tippen');
        self::assertArrayHasKey('Tippspiel', $addon->rahmenteile(self::kontext('', null, $auswahl)), 'ohne Liga');
        self::assertSame([], $addon->rahmenteile(self::kontext('table', 'bula.l98', ['eintippspiel' => '0'] + $immer)), 'abgeschaltet');
    }

    public function testDatenordnerZiehtUmUndLegtAn(): void
    {
        $lmo = sys_get_temp_dir() . '/lmo-tippdaten-' . bin2hex(random_bytes(4));
        mkdir($lmo . '/addon/tipp/meine', 0777, true);
        mkdir($lmo . '/config/tipp', 0777, true);
        file_put_contents($lmo . '/config/tipp/cfg.txt', "dirtipp=meine/
tippauthtxt=tipper.txt
");
        file_put_contents($lmo . '/addon/tipp/tipper.txt', 'T');
        file_put_contents($lmo . '/addon/tipp/meine/liga_nick.tip', 'X');
        file_put_contents($lmo . '/addon/tipp/addon.php', '<?php');

        $ordner = TippAddon::datenordner($lmo . '/daten', $lmo . '/addon', $lmo . '/config');

        self::assertSame($lmo . '/daten/tipp', $ordner);
        self::assertStringEqualsFile($ordner . '/tipper.txt', 'T', 'Tipperdatei nach tippauthtxt');
        self::assertStringEqualsFile($ordner . '/meine/liga_nick.tip', 'X', 'Tipps nach dirtipp');
        foreach (['auswert', 'auswert/vereine', 'einsicht'] as $unterordner) {
            self::assertDirectoryExists($ordner . '/meine/' . $unterordner);
        }
        self::assertFileExists($lmo . '/addon/tipp/addon.php', 'der Code bleibt');
        self::assertFileDoesNotExist($lmo . '/addon/tipp/tipper.txt');

        // ohne alte Daten: der Datenordner mit leerer Tipperdatei und den Vorgaben tipps/, lmo-tippauth.txt
        $neu = sys_get_temp_dir() . '/lmo-tippdaten-' . bin2hex(random_bytes(4));
        $leer = TippAddon::datenordner($neu . '/daten', $neu . '/addon', $neu . '/config');
        self::assertStringEqualsFile($leer . '/lmo-tippauth.txt', '');
        self::assertDirectoryExists($leer . '/tipps/auswert/vereine');

        exec('rm -rf ' . escapeshellarg($lmo) . ' ' . escapeshellarg($neu));
    }
}
