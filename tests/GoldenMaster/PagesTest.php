<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Matrix;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * HTML-Ausgabe aller oeffentlichen Seiten (Ligenuebersicht, Tabelle, Ergebnisse,
 * Spielplan, Kreuztabelle, Statistik, Fieberkurve, Kalender, Info) im Vergleich
 * zum aufgezeichneten Originalstand.
 */
final class PagesTest extends TestCase
{
    use AssertsSnapshots;

    /**
     * @dataProvider pages
     */
    public function testPageOutputIsUnchanged(string $query): void
    {
        $fixture = Fixture::get();
        $html = $fixture->runner()->request($query);
        $normalized = Normalizer::html($html, $fixture->app()->path(), $query);

        $this->assertMatchesSnapshot('pages', $query === '' ? '(Ligenuebersicht)' : $query, $normalized);
    }

    public static function pages(): array
    {
        return Matrix::pages(Fixture::sourceRoot());
    }
}
