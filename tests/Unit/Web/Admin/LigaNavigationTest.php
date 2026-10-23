<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Addon\Spieler\SpielerAddon;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\LigaNavigation;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Navigation beim Bearbeiten einer Liga: die gezeigte Seite ohne Link, Punkte der Addons,
 * Tabellen nur mit Handicap, Mannschaften nur fuer Hauptadmin und erweiterte Hilfsadmins, Spieltage
 * zuordnen nur fuer den Hauptadmin in einer Liga.
 */
class LigaNavigationTest extends TestCase
{
    private const ADDR = '/lmoadmin.php?action=admin&amp;todo=edit&amp;file=a.l98&amp;st=';
    private const ADDB = '/lmoadmin.php?action=admin&amp;todo=tabs&amp;file=a.l98&amp;st=';

    /** @param array<string, int> $sitzung */
    private static function html(string $todo, string $st, array $sitzung = ['lmouserok' => 2], bool $handicap = false, bool $pokal = false): string
    {
        $texte = [];
        foreach ([10, 16, 99, 100, 101, 102, 409, 411, 5001, 5002] as $nr) {
            $texte[$nr] = 'T' . $nr;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => $todo, 'st' => $st]),
            '',
            null,
            new Konfiguration(['einspieler' => '1']),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['spieler' => [1 => 'S1', 18 => 'S18']],
            sitzung: new Sitzung($sitzung)
        );
        $liga = new Liga();
        $liga->setzeEinstellung('Actual', '7');
        $liga->setzeEinstellung('HandS', $handicap ? '1' : '0');
        $liga->setzeEinstellung('Type', $pokal ? '1' : '0');

        return LigaNavigation::html($kontext, new Addons([new SpielerAddon('/x/daten/spieler', '/x/spieler')]), $liga, $todo, $st, self::ADDR, self::ADDB);
    }

    public function testGezeigteSeiteOhneLink(): void
    {
        $ergebnisse = self::html('edit', '3');
        self::assertStringContainsString('<td align="center">T10</td>', $ergebnisse);
        self::assertStringContainsString("<a href='" . self::ADDR . "-1' onClick=\"return chklmolink();\" title=\"T100\">T99</a>", $ergebnisse);
        self::assertStringContainsString("<a href='" . self::ADDR . "-4' onClick=\"return chklmolink();\" title=\"S1\">S18</a>", $ergebnisse, 'Punkt des Addons');
        self::assertStringContainsString("<a href='" . self::ADDR . "-10' onclick=\"return chklmolink(this.href);\" title=\"T5002\">T5001</a>", $ergebnisse);

        $einstellungen = self::html('edit', '-1');
        self::assertStringContainsString("<a href='" . self::ADDR . "1'", $einstellungen, 'Ergebnisse verlinkt');
        self::assertStringContainsString('<td align="center">T99</td>', $einstellungen);
        self::assertStringContainsString('<td align="center">S18</td>', self::html('edit', '-4'), 'die Seite des Addons');
        self::assertStringContainsString('<td align="center">T5001</td>', self::html('edit', '-10'));
        self::assertStringContainsString('<td align="center">T101</td>', self::html('edit', '-2'));
    }

    public function testTabellenNurMitHandicap(): void
    {
        self::assertStringNotContainsString('T16', self::html('edit', '1'));
        self::assertStringContainsString("<a href='" . self::ADDB . "7'", self::html('edit', '1', handicap: true), 'auf den aktuellen Spieltag');
        $tabellen = self::html('tabs', '7', handicap: true);
        self::assertStringContainsString('<td align="center">T16</td>', $tabellen);
        self::assertStringContainsString("<a href='" . self::ADDR . "1'", $tabellen, 'Ergebnisse verlinkt');
    }

    public function testSpieltageZuordnenNurHauptadminInDerLiga(): void
    {
        self::assertStringNotContainsString('T5001', self::html('edit', '1', ['lmouserok' => 1, 'lmouserokerweitert' => 1]), 'nicht fuer Hilfsadmins');
        self::assertStringNotContainsString('T5001', self::html('edit', '1', pokal: true), 'nicht im Pokal');
    }

    public function testMannschaftenNachRechten(): void
    {
        self::assertStringNotContainsString('T101', self::html('edit', '1', ['lmouserok' => 1]));
        self::assertStringContainsString('T101', self::html('edit', '1', ['lmouserok' => 1, 'lmouserokerweitert' => 1]));
    }
}
