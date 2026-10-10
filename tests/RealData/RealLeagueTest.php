<?php

namespace Lmo\Tests\RealData;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\RealData\Support\ComparesTables;
use Lmo\Tests\RealData\Support\RealLeague;
use PHPUnit\Framework\TestCase;

/**
 * Echte Ligen (tests/RealData/fixtures): Kern (lmo-calctable.php) und classlib (liga::calcTable())
 * muessen dieselbe Tabelle liefern wie die Quelle - Reihenfolge, Spiele, Punkte und Tore.
 */
final class RealLeagueTest extends TestCase
{
    use ComparesTables;

    public static function setUpBeforeClass(): void
    {
        self::loadClasslib();
    }

    /** Ligadateien wieder entfernen: die Instanz teilen sich alle Suites (Ligenuebersicht!) */
    public static function tearDownAfterClass(): void
    {
        $dir = Fixture::get()->app()->ligenDir() . '/real';
        foreach (glob($dir . '/*.l98') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    public static function leagues(): array
    {
        return self::usableLeagues();
    }

    /** @dataProvider leagues */
    public function testCoreMatchesSourceTable(string $file): void
    {
        $league = RealLeague::load($file);
        $ligen = Fixture::get()->app()->ligenDir();
        @mkdir($ligen . '/real');
        file_put_contents($ligen . '/real/' . $league->id() . '.l98', $league->toL98());

        self::assertTableMatches($league, self::coreTable('real/' . $league->id() . '.l98', $league->rounds()));
    }

    /** @dataProvider leagues */
    public function testClasslibMatchesSourceTable(string $file): void
    {
        $league = RealLeague::load($file);
        $path = sys_get_temp_dir() . '/real-' . $league->id() . '.l98';
        file_put_contents($path, $league->toL98());
        $liga = new \liga();
        $liga->loadFile($path);
        unlink($path);

        self::assertTableMatches($league, self::classlibTable($liga, $league->rounds()));
    }
}
