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
 * Das Tippspiel als Addon: die Seite ueber den alten Code und der Menuepunkt.
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

    public function testSeiteUeberDenAltenCode(): void
    {
        $addon = new TippAddon(static function (): void {
            echo '<p>alter Code</p>';
        });
        $alle = $addon->controller();
        self::assertSame(
            [\Lmo\Addon\Tipp\RegistrierungController::class, \Lmo\Addon\Tipp\KontoController::class, \Lmo\Addon\Tipp\TippabgabeController::class, \Lmo\Addon\Tipp\EinsichtController::class, \Lmo\Addon\Tipp\TabelleController::class, \Lmo\Addon\Tipp\RanglisteController::class, \Lmo\Addon\Tipp\FieberkurveController::class, \Lmo\Addon\Tipp\InfoController::class, \Lmo\Addon\Tipp\StartseiteController::class, \Lmo\Addon\Tipp\AnmeldungController::class],
            array_map('get_class', array_slice($alle, 0, -1)),
            'erst die umgebauten Seiten, zuletzt der alte Code'
        );
        $controller = $alle[count($alle) - 1];
        $an = ['eintippspiel' => '1'];

        self::assertSame('tipp', $addon->name());
        self::assertTrue($controller->zustaendig(self::kontext('tipp', null, $an)));
        self::assertFalse($controller->zustaendig(self::kontext('tipp', null, ['eintippspiel' => '0'])), 'Tippspiel abgeschaltet');
        self::assertFalse($controller->zustaendig(self::kontext('table', 'x.l98', $an)));
        self::assertSame('<p>alter Code</p>', $controller->zeige(self::kontext('tipp', null, $an))->html, 'Ausgabe des alten Codes als Seite');
    }

    public function testMenuepunkt(): void
    {
        $addon = new TippAddon(static function (): void {
        });
        $immer = ['eintippspiel' => '1', 'tipp' => ['immeralle' => '1', 'ligenzutippen' => '']];
        $auswahl = ['eintippspiel' => '1', 'tipp' => ['immeralle' => '0', 'ligenzutippen' => 'bula,cup']];

        self::assertSame(['Tippspiel' => '<a href="/lmo.php?file=archiv/x.l98&amp;action=tipp" title="Tippspiel">Tippspiel</a>'], $addon->rahmenteile(self::kontext('table', 'archiv/x.l98', $immer)));
        self::assertSame(['Tippspiel' => 'Tippspiel'], $addon->rahmenteile(self::kontext('tipp', null, $immer)), 'im Tippspiel ohne Link');
        self::assertArrayHasKey('Tippspiel', $addon->rahmenteile(self::kontext('table', 'bula.l98', $auswahl)), 'Liga zu tippen');
        self::assertSame([], $addon->rahmenteile(self::kontext('table', 'andere.l98', $auswahl)), 'Liga nicht zu tippen');
        self::assertArrayHasKey('Tippspiel', $addon->rahmenteile(self::kontext('', null, $auswahl)), 'ohne Liga');
        self::assertSame([], $addon->rahmenteile(self::kontext('table', 'bula.l98', ['eintippspiel' => '0'] + $immer)), 'abgeschaltet');
    }
}
