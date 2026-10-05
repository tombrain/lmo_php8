<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Matrix;
use PHPUnit\Framework\TestCase;

/**
 * Rechenebene: lmo-calctable.php (inkl. direktem Vergleich) fuer jede Liga in allen
 * Tabellenansichten und mehreren Spieltagen. Verglichen werden Sortierschluessel
 * und alle Bilanzspalten als JSON (siehe bin/calc.php).
 */
final class CalculationTest extends TestCase
{
    use AssertsSnapshots;

    /**
     * @dataProvider leagues
     */
    public function testTableCalculationIsUnchanged(string $leagueFile): void
    {
        $fixture = Fixture::get();
        $json = $fixture->runner()->calc($leagueFile);
        $json = str_replace($fixture->app()->path(), '{LMO_PATH}', $json);

        $this->assertMatchesSnapshot('calc', $leagueFile, $json . "\n");
    }

    public static function leagues(): array
    {
        $cases = [];
        foreach (array_keys(Matrix::leagues(Fixture::sourceRoot())) as $file) {
            $cases[$file] = [$file];
        }
        return $cases;
    }
}
