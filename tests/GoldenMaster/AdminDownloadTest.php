<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Download von Ligadateien im Admin-Bereich (lmo-admindownload.php): eine Liga als .l98,
 * alle Ligen als Zip.
 */
final class AdminDownloadTest extends TestCase
{
    private static AdminClient $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = Fixture::loggedInClient();
    }

    /** Der Hinweis zu session_start() stammt vom Testrahmen, der die Sitzung schon gestartet hat. */
    private static function download(string $down, ?AdminClient $client = null): string
    {
        $body = ($client ?? self::$client)->get('lmo-admindownload.php', 'action=admin&todo=download&down=' . $down);
        $body = (string)preg_replace('/Notice: session_start\(\): Ignoring session_start\(\)[^>]*? on line \d+ ?/', '', $body);
        return (string)preg_replace('/<!-- STDERR\s*-->\n?/', '', $body);
    }

    public function testSingleLeagueIsDeliveredUnchanged(): void
    {
        // Nummer = Platz in der sortierten Liste der Ligen im Ligenverzeichnis
        $expected = (string)file_get_contents(self::$client->instance()->ligenDir() . '/1l_2024-25.l98');

        // der Testrahmen fasst Leerraum zusammen
        $words = static fn (string $text): string => trim((string)preg_replace('/\s+/', ' ', $text));
        self::assertSame($words($expected), $words(self::download('1')));
    }

    public function testUnknownLeagueNumberDeliversNothing(): void
    {
        self::assertSame('', trim(self::download('99')));
    }

    public function testAllLeaguesAsZip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }

        $body = self::download('-1');

        self::assertStringStartsWith('PK', $body, 'Zip-Datei');
        self::assertStringContainsString('1l_2024-25.l98', $body, 'Liga im Archiv');
        self::assertFileDoesNotExist(self::$client->instance()->path() . '/output/ligen.zip', 'temporaere Datei entfernt');
        self::assertFileDoesNotExist(self::$client->instance()->path() . '/ligen.zip');
    }

    public function testAllLeaguesWithoutZipExtensionShowsMessage(): void
    {
        if (class_exists(\ZipArchive::class)) {
            self::markTestSkipped('nur ohne die PHP-Erweiterung zip');
        }

        $body = self::download('-1');

        self::assertStringNotContainsString('STDERR', $body, 'PHP-Meldungen');
        self::assertStringContainsString('class="error"', $body, 'Fehlermeldung statt Abbruch');
        self::assertStringContainsString('zip', $body);
    }

    public function testNoDownloadWithoutLogin(): void
    {
        $anonymous = Fixture::anonymousClient();

        self::assertSame('', trim(self::download('1', $anonymous)));
        self::assertSame('', trim(self::download('-1', $anonymous)));
    }
}
