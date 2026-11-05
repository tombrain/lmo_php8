<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Addon "viewer": Der Admin speichert eine Ansicht (config/viewer/<name>.view), die Seite
 * addon/viewer/viewer.php zeigt damit die Spiele ausgewaehlter Mannschaften - nach Datum oder nach
 * Spieltag. Liga: 1. Bundesliga 2024/25 mit echten Terminen, Testuhr 15.03.2025.
 */
final class ViewerAddonTest extends TestCase
{
    use AssertsSnapshots;

    private const LIGA = '1l_2024-25.l98';

    private static AdminClient $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = Fixture::loggedInClient();
    }

    /**
     * Speichert eine Ansicht wie der letzte Schritt des Admin-Formulars (Mannschaften 1 und 2).
     *
     * @param array<string,string|int> $settings Abweichungen von den Standardwerten
     */
    private static function saveView(string $name, array $settings = []): string
    {
        $config = $settings + [
            'modus' => 1, 'anzahl_tage_plus' => 7, 'anzahl_tage_minus' => 7, 'anzahl_spieltage_vor' => 1,
            'anzahl_spieltage_zurueck' => 1, 'datumsformat' => 'd.m.Y', 'heute_highlight' => 1, 'tabelle_verlinken' => 1,
            'spielberichte_neues_fenster' => '', 'mannschaftshomepages_verlinken' => '', 'mannschaftsnamen' => 0,
            'titelzeile' => 'Testansicht', 'anstosstermin' => '', 'template' => 'standard', 'uhrzeitformat' => 'H:i',
            'tordummy' => '_', 'tabellensymbol' => 'tabelle.svg', 'spielberichtesymbol' => 'bericht.svg',
            'notizsymbol' => 'notiz.svg', 'spieltagtext' => 'ST', 'cache_refresh' => 0, 'favteam_highlight' => 1,
        ];
        $pairs = [];
        foreach ($config as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        return self::$client->post('lmoadmin.php', '', [
            'action' => 'admin', 'todo' => 'vieweroptions', 'formular3' => '1', 'dateiname' => $name,
            'config_array' => implode(';', $pairs), 'zaehler' => '2',
            't0' => self::LIGA . '[1]', 't1' => self::LIGA . '[2]',
        ]);
    }

    private static function view(string $name): string
    {
        return self::$client->get('addon/viewer/viewer.php', 'multi=' . $name);
    }

    /**
     * Der Assistent in der Administration: Einstellungen, Ligen, Mannschaften, Speichern. Gewaehlt
     * wird die erste Liga des Ligenordners und deren erste Mannschaft. todo steht im Browser in der
     * Adresse des Formulars, hier als Feld.
     */
    public function testAdminWizard(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $ligen = array_values(array_filter(scandir($c->instance()->ligenDir()) ?: [], static fn (string $f): bool => strtolower(substr($f, -4)) == '.l98'));

        $t->add('Schritt 1: Einstellungen', $c->submitForm('lmoadmin.php', 'action=admin&todo=vieweroptions', 'formular1', ['dateiname' => 'assistent', 'titelzeile' => 'Assistent', 'todo' => 'vieweroptions']), $c->changes());
        $t->add('Schritt 2: Ligen', $c->submitFromLast('formular2', ['c1' => 'c1', 'todo' => 'vieweroptions'], 'B2'), $c->changes());
        $t->add('Schritt 3: Mannschaften', $c->submitFromLast('formular3', ['t0' => $ligen[0] . '[1]', 'todo' => 'vieweroptions']), $c->changes());

        $this->assertMatchesSnapshot('viewer', 'assistent', $t->text());
    }

    public function testAdminSavesView(): void
    {
        $html = self::saveView('gespeichert');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen beim Speichern');
        $file = (string)file_get_contents(self::$client->instance()->path() . '/config/viewer/gespeichert.view');
        self::assertStringContainsString("[config]\nmodus=1\n", $file);
        self::assertStringContainsString("template=standard\n", $file);
        self::assertStringContainsString("[Viewer Ligen]\nliga1=" . self::LIGA . "\n" . self::LIGA . "_1=1\n" . self::LIGA . "_2=2\n", $file);
    }

    public function testGamesByDate(): void
    {
        self::saveView('datum');

        $html = self::view('datum');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        // 7 Tage vor und nach dem 15.03.2025: je zwei Spiele von Bayern (1) und Leverkusen (2)
        // der 08.03.2025 steht zusaetzlich in der Ueberschrift "Begegnungen von ... bis ..."
        self::assertSame(3, substr_count($html, '08.03.2025'), 'Spiele am 08.03.2025');
        self::assertSame(2, substr_count($html, '15.03.2025'), 'Spiele am 15.03.2025');
        self::assertStringContainsString('Bayern München', $html);
        self::assertStringContainsString('Bayer 04 Leverkusen', $html);
        $this->assertMatchesSnapshot('viewer', 'nach Datum', Normalizer::html($html, self::$client->instance()->path(), 'multi=datum'));
    }

    public function testGamesByMatchday(): void
    {
        self::saveView('spieltag', ['modus' => 2, 'template' => 'standard_spieltag']);

        $html = self::view('spieltag');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertStringContainsString('Bayern München', $html);
        $this->assertMatchesSnapshot('viewer', 'nach Spieltag', Normalizer::html($html, self::$client->instance()->path(), 'multi=spieltag'));
    }

    public function testCachedViewIsServedUntilRefreshLimit(): void
    {
        self::saveView('cache', ['cache_refresh' => 3]);
        $counter = self::$client->instance()->path() . '/output/viewer_cache_count.txt';

        $first = self::view('cache');
        self::assertSame('1', trim((string)file_get_contents($counter)), 'erster Aufruf erzeugt die Ansicht');
        $second = self::view('cache');

        self::assertStringNotContainsString('STDERR', $first . $second, 'PHP-Meldungen');
        self::assertSame($first, $second, 'zweiter Aufruf liefert dieselbe Ausgabe aus dem Cache');
        self::assertSame('2', trim((string)file_get_contents($counter)), 'Cache-Zaehler');
    }

    /** Ansicht direkt als Datei anlegen: Spiele nach Spieltag, alle Mannschaften der angegebenen Ligen. */
    private static function writeView(string $name, array $leagues): void
    {
        $lines = ['[config]', 'modus=2', 'anzahl_tage_plus=7', 'anzahl_tage_minus=7', 'anzahl_spieltage_vor=1',
            'anzahl_spieltage_zurueck=1', 'datumsformat=d.m.Y', 'heute_highlight=1', 'tabelle_verlinken=1',
            'spielberichte_neues_fenster=', 'mannschaftshomepages_verlinken=', 'mannschaftsnamen=0', 'titelzeile=Mehrere Ligen',
            'anstosstermin=', 'template=standard_spieltag', 'uhrzeitformat=H:i', 'tordummy=_', 'tabellensymbol=tabelle.svg',
            'spielberichtesymbol=bericht.svg', 'notizsymbol=notiz.svg', 'spieltagtext=ST', 'cache_refresh=0',
            'favteam_highlight=1', '[Viewer Ligen]'];
        foreach (array_values($leagues) as $i => $league) {
            $lines[] = 'liga' . ($i + 1) . '=' . $league;
        }
        $dir = self::$client->instance()->path() . '/config/viewer';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $name . '.view', implode("\n", $lines) . "\n");
    }

    public function testViewOverSeveralLeagues(): void
    {
        // zweite und dritte Liga mit eigenen Mannschaftsnamen neben die Bundesliga legen
        $ligen = self::$client->instance()->ligenDir();
        foreach (['basic' => 'Alpha', 'kegel' => 'Kegler'] as $source => $prefix) {
            $content = (string)file_get_contents($ligen . '/golden/' . $source . '.l98');
            file_put_contents($ligen . '/mehr-' . $source . '.l98', str_replace('Mannschaft ', $prefix . ' ', $content));
        }
        self::writeView('eine', [self::LIGA]);
        self::writeView('drei', [self::LIGA, 'mehr-basic.l98', 'mehr-kegel.l98']);

        $one = self::view('eine');
        $three = self::view('drei');

        self::assertStringNotContainsString('STDERR', $one . $three, 'PHP-Meldungen');
        self::assertStringContainsString('Bayern München', $one);
        self::assertStringNotContainsString('Alpha 1', $one);
        foreach (['Bayern München', 'Alpha 1', 'Kegler 1'] as $team) {
            self::assertStringContainsString($team, $three, "Spiele von $team in der Ansicht ueber drei Ligen");
        }
    }

    /**
     * Alle Schalter einer Ansicht auf einer Liga mit Sonderfaellen: Torfaktor 10, ein Spiel mit
     * Notiz, Spielbericht und Spielende nach Verlaengerung, die Homepage einer Mannschaft, die
     * Lieblingsmannschaft. Nach Spieltag (sortiert nach Datum) mit mittleren Namen und Links, nach
     * Datum mit kurzen Namen.
     */
    public function testAlleSchalter(): void
    {
        $ligen = self::$client->instance()->ligenDir();
        $liga = str_replace("\r\n", "\n", (string)file_get_contents($ligen . '/' . self::LIGA));
        $liga = str_replace(['goalfaktor=1', 'favTeam=0', "[Team2]\nSP=0\nSM=0\nTOR1=0\nTOR2=0\nSTDA=0\nURL=\n"], ['goalfaktor=10', 'favTeam=2', "[Team2]\nSP=0\nSM=0\nTOR1=0\nTOR2=0\nSTDA=0\nURL=http://bayern.example\n"], $liga);
        $liga = (string)preg_replace('/(\[Round25\]\n(?:.*\n)*?)GA1=-1\nGB1=-1\nNT1=\nBE1=\n/', "$1GA1=30\nGB1=10\nNT1=Notiz zum Spiel\nBE1=http://bericht.example\nSP1=1\n", $liga, 1, $count);
        self::assertSame(1, $count, 'Spiel 1 am Spieltag 25');
        file_put_contents($ligen . '/viewer-alles.l98', $liga);

        $dir = self::$client->instance()->path() . '/config/viewer';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $schreibe = static function (string $name, array $werte) use ($dir): void {
            $zeilen = ['[config]'];
            foreach ($werte + [
                'anzahl_tage_plus' => 7, 'anzahl_tage_minus' => 7, 'anzahl_spieltage_vor' => 1, 'anzahl_spieltage_zurueck' => 1,
                'datumsformat' => 'd.m.Y', 'heute_highlight' => 1, 'tabelle_verlinken' => 0, 'spielberichte_neues_fenster' => 1,
                'mannschaftshomepages_verlinken' => 1, 'titelzeile' => 'Alles', 'anstosstermin' => '', 'uhrzeitformat' => 'H:i',
                'tordummy' => '-', 'tabellensymbol' => 'tabelle.svg', 'spielberichtesymbol' => 'bericht.svg', 'notizsymbol' => 'notiz.svg',
                'spieltagtext' => 'ST', 'cache_refresh' => 0, 'favteam_highlight' => 1,
            ] as $schluessel => $wert) {
                $zeilen[] = $schluessel . '=' . $wert;
            }
            $zeilen[] = '[Viewer Ligen]';
            $zeilen[] = 'liga1=viewer-alles.l98';
            file_put_contents($dir . '/' . $name . '.view', implode("\n", $zeilen) . "\n");
        };
        $schreibe('alles-spieltag', ['modus' => 2, 'template' => 'standard_spieltag', 'mannschaftsnamen' => 2]);
        $schreibe('alles-datum', ['modus' => 1, 'template' => 'standard', 'mannschaftsnamen' => 1]);

        $t = new Transcript();
        foreach (['alles-spieltag', 'alles-datum'] as $name) {
            $html = self::view($name);
            self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
            $t->add($name, Normalizer::html($html, self::$client->instance()->path(), 'multi=' . $name), '');
        }

        $this->assertMatchesSnapshot('viewer', 'alle Schalter', $t->text());
    }

    public function testUnknownView(): void
    {
        $html = self::view('gibtesnicht');

        self::assertStringContainsString('gibtesnicht.view', $html);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertFileDoesNotExist(self::$client->instance()->path() . '/output/viewer_gibtesnicht_count.txt', 'kein Zaehler fuer eine unbekannte Ansicht');
    }

    public function testNameMitPfadOderSkript(): void
    {
        // Tags und Ereignis-Attribute entschaerft der LMO schon beim Einlesen der Anfrage; die Meldung
        // maskiert den Namen zusaetzlich
        $html = self::$client->get('addon/viewer/viewer.php', 'multi=' . rawurlencode('../gespeichert" onmouseover="x&'));

        self::assertStringNotContainsString('onmouseover="', $html);
        self::assertStringContainsString('x&amp;.view', $html, 'maskiert');
        self::assertStringNotContainsString('Bayern München', $html, 'keine Ansicht ausserhalb von config/viewer');

        self::saveView('umweg');
        self::assertStringContainsString('Bayern München', self::view('umweg'));
        self::assertStringNotContainsString('Bayern München', self::$client->get('addon/viewer/viewer.php', 'multi=' . rawurlencode('../viewer/umweg')), 'auch nicht ueber einen Umweg');
    }
}
