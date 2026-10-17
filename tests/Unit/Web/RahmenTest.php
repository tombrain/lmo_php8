<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Rahmen;
use Lmo\Web\Seiten\Hauptmenue;
use PHPUnit\Framework\TestCase;

/**
 * Der Rahmen der Seiten und das Menue einer Liga.
 */
class RahmenTest extends TestCase
{
    /**
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     * @param array<string, string> $parameter
     */
    private static function kontext(string $action, array $optionen = [], array $parameter = [], string $tabonres = '0'): Kontext
    {
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(array_merge(['[Options]', 'Teams=4', 'Rounds=6', 'Actual=3', 'kurve1=2', 'DatC=1'], $optionen)));

        return new Kontext(
            new Anfrage($parameter),
            $action,
            new GeladeneLiga('x.l98', $liga, ''),
            new Konfiguration(['tabonres' => $tabonres]),
            $text,
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '')
        );
    }

    public function testMenueEinerLiga(): void
    {
        $teile = Hauptmenue::teile(self::kontext('table'));

        self::assertSame('<a href="/lmo.php?file=x.l98&amp;action=cal&amp;st=3" title="T141">T140</a>&nbsp;&nbsp;', $teile['Kalender'], 'Spieltag aus der Datei');
        self::assertSame('<a href="/lmo.php?file=x.l98&amp;action=results&amp;st=3" title="T11">T10</a>&nbsp;&nbsp;', $teile['Ergebnisse']);
        self::assertSame('T16&nbsp;&nbsp;', $teile['Tabelle'], 'die gezeigte Seite ohne Link');
        self::assertSame('<a href="/lmo.php?file=x.l98&amp;action=stats&amp;stat1=2&amp;stat2=0" title="T19">T18</a>&nbsp;&nbsp;', $teile['Ligastatistik'], 'Mannschaft der Fieberkurve');
        self::assertSame('<a href="/lmo.php?file=x.l98&amp;action=info" title="T21">T20</a>', $teile['Info'], 'ohne Abstand dahinter');
    }

    public function testErgebnisseUndTabelleZusammen(): void
    {
        $teile = Hauptmenue::teile(self::kontext('table', [], [], '1'));

        self::assertSame('T10/T16&nbsp;&nbsp;', $teile['Ergebnisse'], 'Tabelle gezeigt: ohne Link');
        self::assertSame('', $teile['Tabelle']);
        self::assertStringContainsString('action=results" title="T104">T10/T16</a>', Hauptmenue::teile(self::kontext('cross', [], [], '1'))['Ergebnisse']);
        $getrennt = Hauptmenue::teile(self::kontext('cross', [], ['tabonres' => '0'], '1'));
        self::assertStringContainsString('title="T17">T16</a>', $getrennt['Tabelle'], 'tabonres aus der Anfrage geht vor');
    }

    public function testParameterDerAnfrage(): void
    {
        $teile = Hauptmenue::teile(self::kontext('cross', [], ['st' => '5', 'endtab' => '4', 'stat1' => '0', 'stat2' => '3']));

        self::assertStringContainsString('action=cal&amp;st=5"', $teile['Kalender']);
        self::assertStringContainsString('action=results&amp;st=4"', $teile['Ergebnisse'], 'endtab vor st');
        self::assertStringContainsString('stat1=3&amp;stat2=0"', $teile['Ligastatistik'], 'nur die zweite gewaehlt');
    }

    public function testMenueImPokal(): void
    {
        $teile = Hauptmenue::teile(self::kontext('program', ['Type=1']));

        self::assertNotSame('', $teile['Ergebnisse']);
        self::assertSame('T12&nbsp;&nbsp;', $teile['Spielplan']);
        self::assertSame('', $teile['Tabelle'] . $teile['Kreuztabelle'] . $teile['Fieberkurve'] . $teile['Ligastatistik'], 'nur Liga');
    }

    public function testAbgeschalteteSeitenFehlen(): void
    {
        $teile = Hauptmenue::teile(self::kontext('table', ['DatC=0', 'Kreuz=0', 'Graph=0']));

        self::assertSame('', $teile['Kalender'] . $teile['Kreuztabelle'] . $teile['Fieberkurve']);
    }

    public function testVorlagen(): void
    {
        $ordner = sys_get_temp_dir() . '/lmo-rahmen-' . bin2hex(random_bytes(4));
        mkdir($ordner);
        file_put_contents($ordner . '/x.l98.tpl.php', '');
        try {
            self::assertSame('x.l98.tpl.php', Rahmen::alteVorlage($ordner, 'archiv/x.l98', 'lmo-standard.tpl.php'), 'ligaeigene Vorlage');
            self::assertSame('lmo-standard.tpl.php', Rahmen::alteVorlage($ordner, 'y.l98', 'lmo-standard.tpl.php'));
            self::assertSame('eigen.tpl.php', Rahmen::alteVorlage($ordner, '', 'eigen.tpl.php'));
        } finally {
            unlink($ordner . '/x.l98.tpl.php');
            rmdir($ordner);
        }

        self::assertTrue(Rahmen::ersetzt('lmo-standard.tpl.php'));
        self::assertTrue(Rahmen::ersetzt('lmo-standard-komplett.tpl.php'));
        self::assertFalse(Rahmen::ersetzt('eigen.tpl.php'));
    }

    public function testTeileMitLiga(): void
    {
        $text = [];
        for ($i = 0; $i <= 900; $i++) {
            $text[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Name=Kreisliga', 'Teams=4', 'Rounds=6']));
        $kontext = new Kontext(
            new Anfrage([]),
            'table',
            new GeladeneLiga('x.l98', $liga, '17.05.2025'),
            new Konfiguration(['backlink' => '1']),
            $text,
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', '/url'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '')
        );

        $teile = Rahmen::teile($kontext, '<p>Seite</p>', false, '[Sprachen]', null);

        self::assertSame('Kreisliga', $teile['Titel']);
        self::assertSame('T798', $teile['HTML_LANG']);
        self::assertStringContainsString('href="/url/lmo-style-nc.php"', $teile['Stylesheet']);
        self::assertStringStartsWith('<!-- Dieser Container dient als das schwebende Fenster -->', $teile['Javascript']);
        self::assertStringEndsWith('</script>', $teile['Javascript']);
        self::assertSame('<p>Seite</p>', $teile['Hauptteil']);
        self::assertSame('[Sprachen]', $teile['Sprachauswahl']);
        self::assertSame('', $teile['Savehtml'], 'Druckansicht abgeschaltet');
        self::assertSame('T16&nbsp;&nbsp;', $teile['Tabelle'], 'Menue');
        self::assertSame('T406:&nbsp;17.05.2025', $teile['LetzteAuswertung'], 'Fusszeile');
        self::assertStringContainsString('T391</a>', $teile['Ligenuebersicht'], 'backlink');
        self::assertSame('', $teile['Berechnungszeit']);
    }

    public function testTeileOhneLiga(): void
    {
        $kontext = static fn (string $action): Kontext => new Kontext(
            new Anfrage([]),
            $action,
            null,
            new Konfiguration([]),
            [53 => 'Ligen', 798 => 'de'],
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => ''),
            weitereTexte: ['tipp' => [0 => 'Tippspiel']]
        );

        $teile = Rahmen::teile($kontext(''), '', false, '', null);

        self::assertSame('Ligen', $teile['Titel']);
        self::assertSame('', $teile['Kalender'] . $teile['Ergebnisse'] . $teile['Info'], 'Menue leer, aber vorhanden');
        self::assertSame('', $teile['LetzteAuswertung']);
        self::assertSame('Tippspiel', Rahmen::teile($kontext('tipp'), '', false, '', null)['Titel']);
    }

    public function testSeite(): void
    {
        $vorlagen = dirname(__DIR__, 3) . '/lmo/vorlagen';
        $teile = ['Titel' => 'Liga & Co', 'Hauptteil' => '<p>Inhalt</p>', 'Infolink' => '(c)', 'HTML_LANG' => 'de'];

        $eingebunden = Rahmen::zeige($vorlagen, 'lmo-standard.tpl.php', $teile);
        self::assertStringStartsWith('<table class="lmoMain"', $eingebunden);
        self::assertStringContainsString('<h1>Liga & Co</h1>', $eingebunden, 'Teile sind fertiges HTML');
        self::assertStringContainsString('<p>Inhalt</p>', $eingebunden);
        self::assertStringContainsString('(c)</td>', $eingebunden);

        $komplett = Rahmen::zeige($vorlagen, 'lmo-standard-komplett.tpl.php', $teile);
        self::assertStringContainsString('<html lang="de">', $komplett);
        self::assertStringContainsString('<title>Liga & Co</title>', $komplett);
    }
}
