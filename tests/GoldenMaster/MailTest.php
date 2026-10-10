<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Mailversand (PHPMailer aus Composer, PHP mail()): "Liga per E-Mail" im Admin-Bereich und
 * "Passwort vergessen" im Tippspiel. Die Mails landen ueber bin/sendmail.php als Dateien in der
 * Testinstanz; eine Datei "fail" im Mailordner stellt einen fehlgeschlagenen Versand nach.
 */
final class MailTest extends TestCase
{
    private static AdminClient $client;

    public static function setUpBeforeClass(): void
    {
        self::$client = Fixture::loggedInClient();
        // ein freigeschalteter Tipper mit Mailadresse
        file_put_contents(
            self::$client->instance()->path() . '/addon/tipp/lmo-tippauth.txt',
            "<?php exit; ?>\ntester|altesPasswort|5|Tina Test|tester@example.org||||1|1|EOL\n"
        );
    }

    protected function setUp(): void
    {
        $dir = self::$client->instance()->mailDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        array_map('unlink', glob($dir . '/*'));
    }

    /** @return string[] verschickte Mails (Kopfzeilen und Inhalt) */
    private static function mails(): array
    {
        return array_map('file_get_contents', glob(self::$client->instance()->mailDir() . '/*.eml'));
    }

    private static function failSending(): void
    {
        touch(self::$client->instance()->mailDir() . '/fail');
    }

    /** Text einer Mail: base64-Teile dekodiert */
    private static function body(string $mail): string
    {
        [, $body] = explode("\n\n", str_replace("\r\n", "\n", $mail), 2);
        return (string)base64_decode($body);
    }

    /** Der Hinweis zu session_start() stammt vom Testrahmen, der die Sitzung schon gestartet hat. */
    private static function sendLeague(): string
    {
        $html = self::$client->get('lmo-adminmimesend.php', 'action=admin&todo=email&down=1&madr=empfaenger@example.org');
        $html = (string)preg_replace('/Notice: session_start\(\): Ignoring session_start\(\)[^>]*? on line \d+ ?/', '', $html);
        return (string)preg_replace('/<!-- STDERR\s*-->\n?/', '', $html);
    }

    private static function requestPassword(string $name): string
    {
        return self::$client->post('lmo.php', '', ['action' => 'tipp', 'todo' => 'getpass', 'xtippername2' => $name]);
    }

    private static function tipperRecord(): string
    {
        return (string)file_get_contents(self::$client->instance()->path() . '/addon/tipp/lmo-tippauth.txt');
    }

    public function testLeagueIsSentAsZipAttachment(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }

        $html = self::sendLeague();

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        $mails = self::mails();
        self::assertCount(1, $mails, 'eine Mail verschickt');
        self::assertMatchesRegularExpression('/^To: empfaenger@example\.org\r?$/m', $mails[0]);
        self::assertMatchesRegularExpression('/^From: .*<local@domain\.invalid>\r?$/m', $mails[0]);
        self::assertStringContainsString('filename=1l_2024-25.l98.zip', $mails[0], 'Liga als Zip-Anhang');
    }

    public function testFailedLeagueMailShowsMessageInsteadOfError(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP-Erweiterung zip fehlt');
        }
        self::failSending();

        $html = self::sendLeague();

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertStringContainsString('class="error"', $html, 'Fehlermeldung statt Abbruch');
        self::assertStringContainsString('</html>', $html, 'Seite wird vollstaendig ausgegeben');
        self::assertCount(0, self::mails());
    }

    public function testLeagueMailWithoutZipExtensionShowsMessage(): void
    {
        if (class_exists(\ZipArchive::class)) {
            self::markTestSkipped('nur ohne die PHP-Erweiterung zip');
        }

        $html = self::sendLeague();

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertStringContainsString('class="error"', $html, 'Fehlermeldung statt Abbruch');
        self::assertStringContainsString('zip', $html);
        self::assertCount(0, self::mails());
    }

    public function testLeagueMailNeedsLogin(): void
    {
        Fixture::anonymousClient()->get('lmo-adminmimesend.php', 'action=admin&todo=email&down=1&madr=empfaenger@example.org');

        self::assertCount(0, self::mails());
    }

    public function testForgottenPasswordSendsNewPassword(): void
    {
        $html = self::requestPassword('tester');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        $mails = self::mails();
        self::assertCount(1, $mails, 'eine Mail verschickt');
        self::assertMatchesRegularExpression('/^To: tester@example\.org\r?$/m', $mails[0]);
        $body = self::body($mails[0]);
        self::assertStringContainsString('tester', $body);
        // Link ohne angehaengte Parameter der Anfrage, auch wenn der Webserver REQUEST_SCHEME nicht setzt
        self::assertMatchesRegularExpression('~http://lmo\.test/lmo/lmo\.php\?action=tipp\s*$~', $body);
        // das neue Passwort aus der Mail ist gespeichert, das alte gilt nicht mehr
        self::assertSame(1, preg_match('/: ([0-9a-f]{8})\s/', $body, $m), 'neues Passwort in der Mail');
        self::assertSame(1, preg_match('/^tester\|([^|]+)\|/m', self::tipperRecord(), $record));
        self::assertTrue(password_verify($m[1], $record[1]));
    }

    public function testFailedPasswordMailShowsMessageInsteadOfError(): void
    {
        self::failSending();

        $html = self::requestPassword('tester');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertStringContainsString('class="error"', $html, 'Fehlermeldung statt Abbruch');
        self::assertStringContainsString('</html>', $html, 'Seite wird vollstaendig ausgegeben');
    }

    public function testForgottenPasswordForUnknownNameSendsNothing(): void
    {
        $before = self::tipperRecord();

        $html = self::requestPassword('niemand');

        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertCount(0, self::mails());
        self::assertSame($before, self::tipperRecord());
    }
}
