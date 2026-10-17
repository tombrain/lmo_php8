<?php

declare(strict_types=1);

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Instance;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use Lmo\Tests\GoldenMaster\Support\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Die oeffentliche Seite des Spieler-Addons (action=spieler) in ihren Einstellungen: Sortierung,
 * Seiten, Nullwerte, vereinsweise Anzeige, Sortierspalte, Spielerlinks und Formelspalte. Die
 * Statistik (addon/spieler/stats/<liga>.stat) und ihre Einstellungen (<liga>.cfg) legt der Test
 * direkt an; wie der Admin sie schreibt, prueft SpielerAddonTest.
 */
final class SpielerSeiteTest extends TestCase
{
    use AssertsSnapshots;

    private const STATISTIK = <<<'STAT'
        Name#Verein#Tore#Spiele#Spielerlink
        Müller#Mannschaft 1#5#10#https://example.org/mueller
        Ahrens#Mannschaft 2#3#4#___
        Zander#Mannschaft 1#0#2#___
        Bauer#Mannschaft 3#3#7#___
        Krause#Mannschaft 4#7#9#___
        Lange#Mannschaft 2#3#8#___
        Engel#Mannschaft 5#1#3#___
        Fuchs#Mannschaft 6#0#0#___
        Graf#Mannschaft 1#2#5#___
        Hahn#Mannschaft 3#3#6#___
        Ilić#Mannschaft 4#4#9#___
        Jung#Mannschaft 2#3#1#___
        STAT;

    private const FORMEL = <<<'STAT'
        Name#Tore#Spiele#Quote*_*-*
        ###Tore/Spiele
        Müller#5#10#0.5
        Ahrens#3#4#0.75
        Zander#0#2#0
        STAT;

    /** @var array{0:Instance,1:Runner}|null */
    private static ?array $instanz = null;

    /**
     * @dataProvider faelle
     * @param array<string, string> $einstellungen Name in der .cfg-Datei => Wert ([] = ohne Datei: config/spieler/cfg.txt)
     */
    public function testSeiteIstUnveraendert(string $statistik, array $einstellungen, string $query): void
    {
        [$app, $runner] = self::instanz();
        $stats = $app->path() . '/addon/spieler/stats/basic';
        if ($statistik === '') {
            @unlink($stats . '.stat');
        } else {
            file_put_contents($stats . '.stat', str_replace("\n", "\r\n", $statistik) . "\r\n");
        }
        @unlink($stats . '.cfg');
        if ($einstellungen !== []) {
            $zeilen = [];
            foreach ($einstellungen as $name => $wert) {
                $zeilen[] = $name . '=' . $wert;
            }
            file_put_contents($stats . '.cfg', implode("\n", $zeilen));
        }

        $query = 'file=golden/basic.l98&action=spieler' . $query;
        $html = $runner->request($query);

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        $this->assertMatchesSnapshot('spieler-seite', (string) $this->dataName(), Normalizer::html($html, $app->path(), $query));
    }

    /** @return array<string, array{0:string, 1:array<string, string>, 2:string}> */
    public static function faelle(): array
    {
        $seiten = ['Anzeige pro Seite' => '5', 'Extra Sortierspalte' => '0'];

        return [
            'standard' => [self::STATISTIK, [], ''],
            'nach_toren_absteigend' => [self::STATISTIK, [], '&sort=2&direction=1'],
            'nach_toren_aufsteigend' => [self::STATISTIK, [], '&sort=2&direction=0'],
            'nach_namen' => [self::STATISTIK, [], '&sort=0&direction=0'],
            'nach_verein_absteigend' => [self::STATISTIK, [], '&sort=1&direction=1'],
            'seite_1' => [self::STATISTIK, $seiten, '&sort=2&direction=1'],
            'seite_2_gleichstand_ueber_die_grenze' => [self::STATISTIK, $seiten, '&sort=2&direction=1&begin=5'],
            'letzte_seite' => [self::STATISTIK, $seiten, '&sort=2&direction=1&begin=10'],
            'alle_auf_einer_seite' => [self::STATISTIK, ['Anzeige pro Seite' => '0'], ''],
            'ohne_nullwerte' => [self::STATISTIK, ['Nullwerte einblenden' => '0', 'Vorsortierung User' => '2'], ''],
            'vereinsweise' => [self::STATISTIK, ['Vereinsweise anzeigen' => '1'], ''],
            'vereinsweise_ein_verein' => [self::STATISTIK, ['Vereinsweise anzeigen' => '1'], '&team=Mannschaft%201'],
            'vorsortierung_und_richtung' => [self::STATISTIK, ['Vorsortierung User' => '3', 'Sortierung' => '0'], ''],
            'eigener_menuepunkt' => [self::STATISTIK, ['Linkbezeichnung' => 'Torjäger'], ''],
            'formelspalte' => [self::FORMEL, [], '&sort=3&direction=1'],
            'ohne_statistik' => ['', [], ''],
        ];
    }

    /** @return array{0:Instance,1:Runner} */
    private static function instanz(): array
    {
        if (self::$instanz === null) {
            [$app, $runner] = Fixture::freshInstance();
            // Spielerstatistik fuer die Liga einschalten (Option "mittore")
            $pfad = $app->ligenDir() . '/golden/basic.l98';
            $inhalt = str_replace("\r\n", "\n", (string) file_get_contents($pfad));
            $inhalt = preg_replace('/^mittore=.*$/m', 'mittore=1', $inhalt, 1, $anzahl);
            if ($anzahl === 0) {
                $inhalt = preg_replace('/^\[Options\]\n/m', "[Options]\nmittore=1\n", (string) $inhalt, 1);
            }
            file_put_contents($pfad, $inhalt);
            $app->freezeLeagueTimes();
            self::$instanz = [$app, $runner];
        }

        return self::$instanz;
    }
}
