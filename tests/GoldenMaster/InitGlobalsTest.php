<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Was init.php fuer den alten Code und die Einbindungen anlegt: die globalen Variablen
 * (Konfiguration, Sprachtexte, Sprache), Konstanten, Zeitzone, Locale, Sitzung und Funktionen
 * (bin/globals.php).
 */
final class InitGlobalsTest extends TestCase
{
    use AssertsSnapshots;

    /** @return array<string, array{string, string}> Name => [Abfrage, Standardsprache in cfg.txt ('' unveraendert)] */
    public static function faelle(): array
    {
        return [
            'Standard' => ['', ''],
            'Sprachwahl Englisch' => ['lmouserlang=English', ''],
            'unbekannte Sprache' => ['lmouserlang=../x', ''],
            'Standardsprache Englisch' => ['', 'English'],
            'Standardsprache unbekannt' => ['', 'Klingonisch'],
            'Parameter' => ['file=golden/basic.l98&st=3&dirliga=../', ''],
        ];
    }

    /** @dataProvider faelle */
    public function testGlobals(string $abfrage, string $sprache): void
    {
        [$app, $runner] = Fixture::freshInstance();
        if ($sprache !== '') {
            $cfg = $app->path() . '/config/cfg.txt';
            file_put_contents($cfg, (string) preg_replace('/^deflang=.*$/m', 'deflang=' . $sprache, (string) file_get_contents($cfg)));
        }

        $this->assertMatchesSnapshot('init-globals', (string) $this->dataName(), $runner->globals($abfrage) . "\n");
    }
}
