<?php

namespace Lmo\Tests\RealData;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\RealData\Support\ComparesTables;
use Lmo\Tests\RealData\Support\RealLeague;
use PHPUnit\Framework\TestCase;

/**
 * Ligen komplett ueber die classlib anlegen und mit liga::writeFile() speichern (in der
 * Testinstanz mit init.php und updateAddons(), alle Ligen in einem Prozess). Danach:
 * - der Kern liest die gespeicherte Datei und rechnet die erwartete Tabelle
 * - die classlib liest sie wieder ein und bekommt dieselbe Liga (Teams, Strafen, Partien,
 *   Sonderwertungen, Optionen)
 */
final class ClasslibWriteTest extends TestCase
{
    use ComparesTables;

    private const PREFIX = 'classlib-';

    /** Ausgabe des Kindprozesses (JSON, ggf. mit STDERR/EXIT-Kommentar) */
    private static string $output = '';
    /** @var array<string,mixed> Liga-ID => true oder Fehlertext */
    private static array $written = [];

    public static function setUpBeforeClass(): void
    {
        self::loadClasslib();
        $files = array_column(self::usableLeagues(), 0);
        self::$output = Fixture::get()->runner()->classlibWrite(self::PREFIX, $files);
        self::$written = json_decode(trim((string)preg_replace('/<!--.*?-->/s', '', self::$output)), true) ?? [];
    }

    /** Ligadateien wieder entfernen: die Instanz teilen sich alle Suites (Ligenuebersicht!) */
    public static function tearDownAfterClass(): void
    {
        foreach (glob(Fixture::get()->app()->ligenDir() . '/' . self::PREFIX . '*.l98') ?: [] as $file) {
            unlink($file);
        }
    }

    public static function leagues(): array
    {
        return self::usableLeagues();
    }

    public function testAllLeaguesAreWrittenInOneRequest(): void
    {
        self::assertStringNotContainsString('<!--', self::$output, 'PHP-Meldungen oder Abbruch beim Speichern');
        self::assertCount(count(self::usableLeagues()), self::$written);
        self::assertSame([], array_filter(self::$written, static fn ($ok) => $ok !== true), 'nicht gespeichert');
    }

    /** @dataProvider leagues */
    public function testCoreReadsLeagueWrittenByClasslib(string $file): void
    {
        $league = RealLeague::load($file);
        $this->writtenFile($league);

        self::assertTableMatches($league, self::coreTable(self::PREFIX . $league->id() . '.l98', $league->rounds()));
    }

    /** @dataProvider leagues */
    public function testClasslibReadsItsOwnLeagueBack(string $file): void
    {
        $league = RealLeague::load($file);
        $reread = new \liga();
        self::assertTrue($reread->loadFile($this->writtenFile($league)));

        self::assertSame(self::canonical($league->buildLiga(), $league), self::canonical($reread, $league));
    }

    /** Pfad der gespeicherten Ligadatei; Test schlaegt fehl, wenn das Speichern scheiterte. */
    private function writtenFile(RealLeague $league): string
    {
        $path = Fixture::get()->app()->ligenDir() . '/' . self::PREFIX . $league->id() . '.l98';
        $status = self::$written[$league->id()] ?? 'nicht versucht (Abbruch vorher?)';
        if ($status !== true || !is_file($path)) {
            self::fail('Liga wurde nicht gespeichert: ' . ($status === true ? 'Datei fehlt' : $status));
        }
        return $path;
    }

    /** Vergleichbare Form einer Liga: alles, was writeFile() speichern und loadFile() lesen muss. */
    private static function canonical(\liga $liga, RealLeague $league): array
    {
        $teams = [];
        foreach ($liga->teams as $team) {
            $keys = [];
            foreach (['SP', 'SM', 'TOR1', 'TOR2', 'STDA'] as $key) {
                $keys[$key] = (int)($team->keyValues[$key] ?? 0);
            }
            $teams[] = [(int)$team->nr, (string)$team->name, (string)$team->kurz, $keys];
        }
        $rounds = [];
        foreach ($liga->spieltage as $spieltag) {
            $matches = [];
            foreach ($spieltag->partien as $partie) {
                $matches[] = [(int)$partie->heim->nr, (int)$partie->gast->nr, (int)$partie->hTore, (int)$partie->gTore,
                    (int)$partie->getSpielEnde(), (string)$partie->getParameter('ET')];
            }
            $rounds[(int)$spieltag->nr] = $matches;
        }
        $options = [];
        foreach (array_keys($league->options()) as $key) {
            if ($key !== 'Title') {  // setzt writeFile() selbst
                $options[$key] = trim((string)($liga->options->keyValues[$key] ?? ''));
            }
        }
        // Leerzeichen am Rand gehen im INI-Format verloren (loadFile() schneidet Werte ab)
        return ['name' => trim((string)$liga->name), 'teams' => $teams, 'rounds' => $rounds, 'options' => $options];
    }
}
