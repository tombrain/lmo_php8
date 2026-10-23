<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\TippAddon;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
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

    public function testAuswertungBeimSpeichern(): void
    {
        $addon = new TippAddon('/lmo/addon/tipp');
        $beide = ['aktauswert' => '1', 'aktauswertges' => '1'];
        $dateien = static fn (array $cfg, ?string $datei = 'golden/bula.l98'): array => $addon->auswertungBeimSpeichern(self::kontext('', $datei, ['tipp' => $cfg]));

        self::assertSame(['/lmo/addon/tipp/lmo-tippsavewert.php', '/lmo/addon/tipp/lmo-tippsavewertgesamt.php'], $dateien(['immeralle' => '1'] + $beide));
        self::assertSame(['/lmo/addon/tipp/lmo-tippsavewert.php'], $dateien(['immeralle' => '0', 'ligenzutippen' => 'x,bula', 'aktauswert' => '1']), 'die Liga steht in der Liste');
        self::assertSame([], $dateien(['immeralle' => '0', 'ligenzutippen' => 'bundesliga'] + $beide), 'nur ganze Namen');
        self::assertSame([], $dateien(['immeralle' => '1', 'aktauswert' => '0', 'aktauswertges' => '0']), 'keine Auswertung eingestellt');
        self::assertSame([], $dateien(['immeralle' => '1'] + $beide, null), 'ohne Liga');
        self::assertSame([], $dateien($beide), 'ohne immeralle zaehlt die (leere) Liste');
    }

    public function testBereich(): void
    {
        $addon = new TippAddon();

        self::assertSame('Tippspiel', $addon->bereichstitel(self::kontext('tipp', null, [])));
        self::assertNull($addon->bereichstitel(self::kontext('table', null, [])));
    }

    public function testErgebnisSpalten(): void
    {
        $addon = new TippAddon('/lmo/addon/tipp');
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Teams=2', 'Rounds=1', 'Matches=1', '[Round1]', 'TA1=1', 'TB1=2', 'TI1=1']));
        $spalten = static fn (array $cfg, int $rolle = 2, ?Liga $mit = null): array => $addon->ergebnisSpalten(new Kontext(
            new Anfrage([]),
            'admin',
            $mit !== null ? new GeladeneLiga('golden/bula.l98', $mit, '') : null,
            new Konfiguration(['tipp' => $cfg]),
            [],
            '/lmoadmin.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => [57 => 'ohne Tipp', 199 => 'ja']],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        ));

        $alle = $spalten(['immeralle' => '1'], 2, $liga);
        self::assertCount(1, $alle);
        $spalte = $alle[0];
        self::assertSame(['xmtipp', 'ohne Tipp', ['0' => '_', '1' => 'ja']], [$spalte->feld, $spalte->titel, $spalte->optionen]);
        self::assertSame('1', $spalte->wert(Spiel::ausRohdaten($liga, 1, 1)));

        $partie = $liga->spieltag(1)->partie(1);
        $spalte->uebernimm($partie, ' 0 ', 1);
        self::assertSame('0', $partie->spiele[1]['TI']);
        self::assertSame('0', $spalte->wert(Spiel::ausRohdaten($liga, 1, 1)));
        $spalte->uebernimm($partie, '1', 2);
        self::assertSame('1', $partie->spiele[2]['TI'], 'je Spiel einer Paarung');

        self::assertSame([], $spalten(['immeralle' => '1'], 1, $liga), 'nur fuer den Hauptadmin');
        self::assertSame([], $spalten(['immeralle' => '1']), 'ohne Liga');
        self::assertSame([], $spalten(['immeralle' => '0', 'ligenzutippen' => 'cup'], 2, $liga), 'Liga wird nicht getippt');
    }
}
