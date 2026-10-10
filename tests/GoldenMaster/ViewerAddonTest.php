<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
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
            'tordummy' => '_', 'tabellensymbol' => 'tabelle.gif', 'spielberichtesymbol' => 'bericht.gif',
            'notizsymbol' => 'notiz.gif', 'spieltagtext' => 'ST', 'cache_refresh' => 0, 'favteam_highlight' => 1,
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

    public function testUnknownView(): void
    {
        $html = self::view('gibtesnicht');

        self::assertStringContainsString('gibtesnicht.view', $html);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
    }
}
