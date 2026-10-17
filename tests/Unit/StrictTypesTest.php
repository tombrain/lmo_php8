<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Jede Klasse unter src/ und unter src/ der Addons (lmo/addon/<addon>/src) prueft Typen streng: Die Datei beginnt mit declare(strict_types=1).
 * Aufrufer aus dem alten Code (lmo/) sind davon nicht betroffen; strict gilt fuer die Aufrufe
 * aus der Datei heraus.
 */
class StrictTypesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function dateien(): array
    {
        $dateien = [];
        $projekt = dirname(__DIR__, 2);
        foreach (array_merge([$projekt . '/src'], glob($projekt . '/lmo/addon/*/src') ?: []) as $wurzel) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wurzel, RecursiveDirectoryIterator::SKIP_DOTS)) as $datei) {
                if ($datei->getExtension() === 'php') {
                    $name = substr($datei->getPathname(), strlen($projekt) + 1);
                    $dateien[str_replace('\\', '/', $name)] = [$datei->getPathname()];
                }
            }
        }
        ksort($dateien);

        return $dateien;
    }

    /** @dataProvider dateien */
    public function testDateiBeginntMitStrictTypes(string $pfad): void
    {
        $anfang = (string) file_get_contents($pfad, false, null, 0, 64);

        self::assertMatchesRegularExpression('/^<\?php\R\Rdeclare\(strict_types=1\);\R/', $anfang);
    }
}
