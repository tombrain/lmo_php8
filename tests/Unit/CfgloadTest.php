<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * lmo-cfgload.php ohne config/cfg.txt: eine klare Meldung ohne Warnungen und ohne Serverpfad
 * (die Sprachtexte sind zu dem Zeitpunkt noch nicht geladen).
 */
class CfgloadTest extends TestCase
{
    public function testFehlendeKonfiguration(): void
    {
        $ordner = sys_get_temp_dir() . '/lmo-cfgload-' . bin2hex(random_bytes(4));
        mkdir($ordner);
        $lmo = dirname(__DIR__, 2) . '/lmo';
        $code = 'error_reporting(E_ALL); ini_set("display_errors", "1");'
            . ' define("PATH_TO_LMO", ' . var_export($lmo, true) . '); define("PATH_TO_CONFIGDIR", ' . var_export($ordner, true) . ');'
            . ' require PATH_TO_LMO . "/lmo-cfgload.php";';

        $ausgabe = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');
        rmdir($ordner);

        self::assertSame('LMO: config/cfg.txt nicht gefunden. / config/cfg.txt not found.', $ausgabe);
    }
}
