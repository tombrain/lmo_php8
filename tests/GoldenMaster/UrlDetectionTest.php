<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * lmo_detect_url() aus lmo-setup.php: ermittelt die URL des LMO-Ordners zur Laufzeit,
 * damit config/init-parameters.php nicht mehr noetig ist.
 *
 * Kein Golden Master (neue Funktion): echte Ordner im Temp-Verzeichnis, weil die Funktion
 * mit realpath() arbeitet.
 */
final class UrlDetectionTest extends TestCase
{
    private string $root;
    private string $lmo;
    private array $server;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('lmo_detect_url')) { // evtl. schon ueber Instance geladen
            require_once Fixture::sourceRoot() . '/lmo/lmo-setup.php';
        }
    }

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->root = sys_get_temp_dir() . '/lmo-url-' . bin2hex(random_bytes(3));
        $this->lmo = $this->root . '/htdocs/vereine/liga';
        mkdir($this->lmo . '/addon/tipp', 0777, true);
        touch($this->lmo . '/lmo.php');
        touch($this->lmo . '/addon/tipp/lmo-tipp.php');
        touch($this->root . '/htdocs/index.php');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function request(string $scriptName, string $scriptFile, array $extra = []): void
    {
        foreach (['HTTPS', 'HTTP_X_FORWARDED_PROTO', 'SERVER_PORT', 'DOCUMENT_ROOT'] as $key) {
            unset($_SERVER[$key]);
        }
        $_SERVER = array_merge($_SERVER, [
            'HTTP_HOST' => 'www.example.org',
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => $scriptFile,
            'SERVER_PORT' => '80',
        ], $extra);
    }

    public function testScriptInLmoFolder(): void
    {
        $this->request('/vereine/liga/lmo.php', $this->lmo . '/lmo.php');
        self::assertSame('http://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testScriptInAddonSubfolder(): void
    {
        $this->request('/vereine/liga/addon/tipp/lmo-tipp.php', $this->lmo . '/addon/tipp/lmo-tipp.php');
        self::assertSame('http://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testLmoFolderIsWebRoot(): void
    {
        $this->request('/lmo.php', $this->lmo . '/lmo.php');
        self::assertSame('http://www.example.org', lmo_detect_url($this->lmo));
    }

    public function testAliasWithDifferentUrlPath(): void
    {
        // Apache-Alias: URL-Pfad hat nichts mit dem Dateipfad zu tun
        $this->request('/ergebnisse/addon/tipp/lmo-tipp.php', $this->lmo . '/addon/tipp/lmo-tipp.php');
        self::assertSame('http://www.example.org/ergebnisse', lmo_detect_url($this->lmo));
    }

    public function testEmbeddedFromPageOutsideLmoUsesDocumentRoot(): void
    {
        $this->request('/index.php', $this->root . '/htdocs/index.php', ['DOCUMENT_ROOT' => $this->root . '/htdocs']);
        self::assertSame('http://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testEmbeddedOutsideDocumentRootIsUnknown(): void
    {
        $this->request('/index.php', $this->root . '/htdocs/index.php', ['DOCUMENT_ROOT' => $this->root . '/anderswo']);
        self::assertSame('', lmo_detect_url($this->lmo));
    }

    public function testHttps(): void
    {
        $this->request('/vereine/liga/lmo.php', $this->lmo . '/lmo.php', ['HTTPS' => 'on', 'SERVER_PORT' => '443']);
        self::assertSame('https://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testHttpsOffIsHttp(): void
    {
        $this->request('/vereine/liga/lmo.php', $this->lmo . '/lmo.php', ['HTTPS' => 'off']);
        self::assertSame('http://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testHttpsBehindProxy(): void
    {
        $this->request('/vereine/liga/lmo.php', $this->lmo . '/lmo.php', ['HTTP_X_FORWARDED_PROTO' => 'https']);
        self::assertSame('https://www.example.org/vereine/liga', lmo_detect_url($this->lmo));
    }

    public function testHostWithPort(): void
    {
        $this->request('/liga/lmo.php', $this->lmo . '/lmo.php', ['HTTP_HOST' => 'localhost:8081', 'SERVER_PORT' => '8081']);
        self::assertSame('http://localhost:8081/liga', lmo_detect_url($this->lmo));
    }

    public function testCommandLineWithoutHostIsUnknown(): void
    {
        $this->request('/vereine/liga/lmo.php', $this->lmo . '/lmo.php');
        unset($_SERVER['HTTP_HOST']);
        self::assertSame('', lmo_detect_url($this->lmo));
    }
}
