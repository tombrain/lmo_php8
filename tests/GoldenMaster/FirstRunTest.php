<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Ersteinrichtung ohne Installer (lmo-setup.php, lmo-adminsetup.php): LMO wird hochgeladen
 * und laeuft sofort; Pfad/URL werden ermittelt, die Standardkonfiguration wird angelegt und
 * das erste Admin-Konto beim ersten Aufruf von lmoadmin.php festgelegt.
 *
 * Kein Golden Master (neues Verhalten), daher gezielte Pruefungen statt Snapshots.
 */
final class FirstRunTest extends TestCase
{
    private const PASSWORD = 'geheim123';

    private function client(): AdminClient
    {
        [$app, $runner] = Fixture::uninstalledInstance();
        return new AdminClient($app, $runner);
    }

    private function path(AdminClient $c, string $file): string
    {
        return $c->instance()->path() . '/' . $file;
    }

    private function assertNoPhpMessages(string $html): void
    {
        $this->assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen in der Ausgabe');
    }

    private function submitSetup(AdminClient $c, string $user, string $pass, string $pass2): string
    {
        return $c->post('lmoadmin.php', '', [
            'setup_user' => $user, 'setup_pass' => $pass, 'setup_pass2' => $pass2, 'setup_submit' => '1',
        ]);
    }

    public function testPublicPageRunsWithoutInstallerAndCreatesConfiguration(): void
    {
        $c = $this->client();
        $this->assertFileDoesNotExist($this->path($c, 'config/cfg.txt'));

        $html = $c->get('lmo.php');

        $this->assertNoPhpMessages($html);
        $this->assertStringNotContainsString('Installation des Liga Manager Online', $html);
        $this->assertStringContainsString('1l_2024-25', $html, 'Ligenuebersicht mit der Beispielliga');
        // URL ermittelt aus Host + Skriptpfad (Harness: http://lmo.test/lmo/lmo.php)
        $this->assertStringContainsString('href="http://lmo.test/lmo/lmo-style-nc.php"', $html);

        foreach (['config/cfg.txt', 'config/tipp/cfg.txt', 'config/spieler/cfg.txt', 'config/ticker/cfg.txt',
            'config/mini/cfg.txt', 'config/classlib/cfg.txt', 'addon/tipp/lmo-tippauth.txt'] as $file) {
            $this->assertFileExists($this->path($c, $file));
        }
        $this->assertFileEquals($this->path($c, 'config-default/cfg.txt'), $this->path($c, 'config/cfg.txt'));
        $this->assertDirectoryExists($this->path($c, 'ligen/archiv'));
        $this->assertDirectoryExists($this->path($c, 'output'));
        // kein Standardkonto admin/lmo und keine festen Pfade mehr
        $this->assertFileDoesNotExist($this->path($c, 'config/lmo-auth.php'));
        $this->assertFileDoesNotExist($this->path($c, 'config/init-parameters.php'));
    }

    public function testExistingConfigurationIsNotOverwritten(): void
    {
        $c = $this->client();
        $this->assertDirectoryExists($this->path($c, 'config/tipp'));
        $custom = file_get_contents($this->path($c, 'config-default/tipp/cfg.txt')) . "\n; eigene Aenderung\n";
        file_put_contents($this->path($c, 'config/tipp/cfg.txt'), $custom);

        $this->assertNoPhpMessages($c->get('lmo.php'));

        $this->assertStringEqualsFile($this->path($c, 'config/tipp/cfg.txt'), $custom);
        $this->assertFileExists($this->path($c, 'config/cfg.txt'));
    }

    public function testFixedPathAndUrlFromInitParametersStillWin(): void
    {
        $c = $this->client();
        file_put_contents(
            $this->path($c, 'config/init-parameters.php'),
            "<?php\n\$lmo_dateipfad='" . $c->instance()->path() . "';\n\$lmo_url='https://fest.example/liga';\n"
        );

        $html = $c->get('lmo.php');

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString('href="https://fest.example/liga/lmo-style-nc.php"', $html);
    }

    public function testInstallerAndFtpCodeAreGone(): void
    {
        $c = $this->client();
        $this->assertDirectoryDoesNotExist($this->path($c, 'install'));
        foreach (['FTP', 'Socket', 'PEAR', 'PEAR5', 'Lite', 'LiteOutput'] as $class) {
            $this->assertFileDoesNotExist($this->path($c, "includes/$class.php"));
        }
        $this->assertFileDoesNotExist($this->path($c, 'config-default/lmo-auth.php'), 'kein Standardkonto mehr');

        // frueherer Einstieg in den Installer fuehrt jetzt zur normalen Seite
        foreach (['lmo.php', 'lmoadmin.php'] as $script) {
            $html = $c->post($script, '', ['lmo_install_step' => '3', 'path' => '/tmp/boese', 'url' => 'http://boese.example']);
            $this->assertNoPhpMessages($html);
            $this->assertStringNotContainsString('Installation des Liga Manager Online', $html);
            $this->assertStringNotContainsString('boese', $html);
        }
        $this->assertFileDoesNotExist($this->path($c, 'config/init-parameters.php'));
    }

    public function testNoInstallerWarningInAdminArea(): void
    {
        $c = $this->client();
        $html = $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $this->assertStringContainsString('todo=logout', $html);
        $this->assertStringNotContainsString('Delete install folder', $html);
    }

    public function testMissingDefaultsShowAClearMessage(): void
    {
        $c = $this->client();
        exec('rm -rf ' . escapeshellarg($this->path($c, 'config-default')));

        $html = $c->get('lmo.php');

        $this->assertStringContainsString('LMO: config/cfg.txt fehlt und config-default/ ist nicht vorhanden.', $html);
    }

    public function testUpdateAddsNewOptionsAndKeepsExistingValues(): void
    {
        $c = $this->client();
        $c->get('lmo.php'); // Ersteinrichtung
        $cfg = $this->path($c, 'config/cfg.txt');
        $tipp = $this->path($c, 'config/tipp/cfg.txt');

        // Betreiber hat Werte geaendert ...
        file_put_contents($cfg, preg_replace('/^tabpkt=.*$/m', 'tabpkt=7', file_get_contents($cfg)));
        $tippBefore = file_get_contents($tipp);
        // ... dann kommt ein Update mit neuen Optionen (Hauptkonfiguration und Addon)
        file_put_contents($this->path($c, 'config-default/cfg.txt'), "neueoption=42\r\n", FILE_APPEND);
        file_put_contents($this->path($c, 'config-default/tipp/cfg.txt'), "tippneu=ja\r\n", FILE_APPEND);

        $this->assertNoPhpMessages($c->get('lmo.php'));

        $text = file_get_contents($cfg);
        $this->assertMatchesRegularExpression('/^tabpkt=7$/m', $text, 'eigener Wert bleibt');
        $this->assertSame(1, preg_match_all('/^neueoption=42$/m', $text), 'neue Option genau einmal ergaenzt');
        $this->assertSame(1, preg_match_all('/^tabpkt=/m', $text), 'keine doppelten Schluessel');
        $this->assertSame($tippBefore . "tippneu=ja\n", file_get_contents($tipp));
        $this->assertSame('42', parse_ini_file($cfg)['neueoption']);
    }

    public function testDefaultsAreOnlyComparedAfterAnUpdate(): void
    {
        $c = $this->client();
        $c->get('lmo.php');
        $cfg = $this->path($c, 'config/cfg.txt');
        file_put_contents($cfg, preg_replace('/^tabonres=.*\R/m', '', file_get_contents($cfg)));
        $before = file_get_contents($cfg);

        // config-default/ unveraendert: kein erneuter Abgleich bei jeder Anfrage
        $c->get('lmo.php');
        $this->assertSame($before, file_get_contents($cfg));

        // nach einem Update (config-default/ geaendert) wird die fehlende Option ergaenzt
        touch($this->path($c, 'config-default/cfg.txt'), time() + 60);
        $c->get('lmo.php');
        $this->assertMatchesRegularExpression('/^tabonres=/m', file_get_contents($cfg));
    }

    public function testUnwritableConfigFolderShowsAClearMessage(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Als root sind alle Ordner beschreibbar (Docker-Testlauf).');
        }
        $c = $this->client();
        chmod($this->path($c, 'config'), 0555);
        try {
            $html = $c->get('lmo.php');
        } finally {
            chmod($this->path($c, 'config'), 0777);
        }
        $this->assertStringContainsString('Der Ordner config/ ist nicht beschreibbar', $html);
    }

    public function testAdminSetupFormInsteadOfLogin(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin');

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString('name="setup_pass"', $html);
        $this->assertStringNotContainsString('name="xuserpass"', $html, 'kein Login ohne Konto');
        $this->assertStringNotContainsString('todo=logout', $html);
    }

    /** @return array<string,array{0:string,1:string,2:string,3:string}> */
    public static function invalidSetupInput(): array
    {
        return [
            'Passwort zu kurz' => ['admin', 'kurz', 'kurz', 'mindestens 8 Zeichen'],
            'Passwoerter verschieden' => ['admin', self::PASSWORD, 'anderes123', 'stimmen nicht'],
            'Benutzername zu kurz' => ['ab', self::PASSWORD, self::PASSWORD, 'Benutzername'],
            'Benutzername mit Trennzeichen' => ['ad|min', self::PASSWORD, self::PASSWORD, 'Benutzername'],
            'Passwort mit Trennzeichen' => ['admin', 'geheim|123', 'geheim|123', 'kein |'],
        ];
    }

    /** @dataProvider invalidSetupInput */
    public function testInvalidSetupInputIsRejected(string $user, string $pass, string $pass2, string $message): void
    {
        $c = $this->client();
        $html = $this->submitSetup($c, $user, $pass, $pass2);

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString($message, $html);
        $this->assertStringContainsString('name="setup_pass"', $html, 'Formular erneut anzeigen');
        $this->assertStringNotContainsString('todo=logout', $html);
        $this->assertFileDoesNotExist($this->path($c, 'config/lmo-auth.php'));
    }

    public function testSetupTextsExistInEveryLanguageFile(): void
    {
        $files = glob(Fixture::sourceRoot() . '/lmo/lang/lang-*.txt');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $keys = [];
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^(\d+)=(.*)$/', rtrim($line, "\r"), $m)) {
                    $keys[(int)$m[1]] = $m[2];
                }
            }
            foreach (range(5013, 5023) as $key) {
                $this->assertNotSame('', $keys[$key] ?? '', basename($file) . ": Text $key fehlt");
            }
            $this->assertTrue(mb_check_encoding((string)file_get_contents($file), 'UTF-8'), basename($file) . ': kein UTF-8');
        }
    }

    public function testSetupFormOffersLanguageSelection(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin');

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString('lmouserlang=English', $html, 'Link zur englischen Sprache');
        $this->assertStringContainsString('lmouserlang=Francais', $html);
        $this->assertStringContainsString('Deutsch.selected.svg', $html, 'aktuelle Sprache markiert');
        $this->assertStringNotContainsString('lmo-admintranslate.php', $html, 'Uebersetzungswerkzeug nur fuer angemeldete Admins');
    }

    public function testLoginFormOffersLanguageSelection(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));

        $html = $other->get('lmoadmin.php', 'action=admin');
        $this->assertStringContainsString('name="xuserpass"', $html);
        $this->assertStringContainsString('lmouserlang=English', $html);
        $this->assertStringContainsString('Deutsch.selected.svg', $html);
        $this->assertStringNotContainsString('lmo-admintranslate.php', $html, 'Uebersetzungswerkzeug erst nach dem Login');

        // Umschalten wirkt auf das Anmeldeformular und bleibt beim Anmelden erhalten
        $html = $other->get('lmoadmin.php', 'action=admin&lmouserlang=English');
        $this->assertStringContainsString('Log in to the Admin area', $html);
        $this->assertStringContainsString('English.selected.svg', $html);
        $html = $other->login('chef', self::PASSWORD);
        $this->assertStringContainsString('todo=logout', $html);
        $this->assertStringContainsString('English.selected.svg', $html);
    }

    public function testLanguageSelectionCanBeSwitchedOff(): void
    {
        $c = $this->client();
        $c->get('lmo.php'); // Ersteinrichtung legt cfg.txt an
        $cfg = $this->path($c, 'config/cfg.txt');
        file_put_contents($cfg, preg_replace('/^einsprachwahl=.*$/m', 'einsprachwahl=0', file_get_contents($cfg)));

        $html = $c->get('lmoadmin.php', 'action=admin');

        $this->assertStringContainsString('name="setup_pass"', $html);
        $this->assertStringNotContainsString('lmouserlang=English', $html);

        // ebenso im Anmeldeformular
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        $html = $other->get('lmoadmin.php', 'action=admin');
        $this->assertStringContainsString('name="xuserpass"', $html);
        $this->assertStringNotContainsString('lmouserlang=English', $html);
    }

    public function testSetupFormFollowsTheSelectedLanguage(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin&lmouserlang=English');

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString('First-time setup', $html);
        $this->assertStringContainsString('Create admin account', $html);
        $this->assertStringNotContainsString('Ersteinrichtung', $html);

        // Fehlermeldungen ebenfalls aus der Sprachdatei (Sprache bleibt in der Sitzung)
        $html = $this->submitSetup($c, 'admin', 'kurz', 'kurz');
        $this->assertStringContainsString('The password must be at least 8 characters long.', $html);
    }

    public function testSetupCreatesHashedAdminAndLogsIn(): void
    {
        $c = $this->client();
        $html = $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);

        $this->assertNoPhpMessages($html);
        $this->assertStringContainsString('todo=logout', $html, 'direkt angemeldet');
        $this->assertStringNotContainsString('name="setup_pass"', $html);

        $lines = file($this->path($c, 'config/lmo-auth.php'), FILE_IGNORE_NEW_LINES);
        $this->assertSame('<?php exit(); ?>', $lines[0], 'Schutzzeile gegen Abruf im Browser');
        $fields = explode('|', $lines[1]);
        $this->assertSame('chef', $fields[0]);
        $this->assertNotSame(self::PASSWORD, $fields[1], 'kein Klartext');
        $this->assertTrue(password_verify(self::PASSWORD, $fields[1]));
        $this->assertSame('2', $fields[2], 'Hauptadmin');

        // Sitzung bleibt bestehen
        $this->assertStringContainsString('todo=logout', $c->get('lmoadmin.php', 'action=admin'));
    }

    public function testLoginWithNewAccountInNewSession(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);

        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        $this->assertStringContainsString('name="xuserpass"', $other->get('lmoadmin.php', 'action=admin'));
        $this->assertStringNotContainsString('todo=logout', $other->login('admin', 'lmo'), 'altes Standardkonto gibt es nicht');
        $this->assertStringNotContainsString('todo=logout', $other->login('chef', 'falsch123'));
        $this->assertStringContainsString('todo=logout', $other->login('chef', self::PASSWORD));
    }

    public function testSecondSetupIsIgnored(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $before = file_get_contents($this->path($c, 'config/lmo-auth.php'));

        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        $html = $this->submitSetup($other, 'angreifer', 'boese12345', 'boese12345');

        $this->assertStringNotContainsString('todo=logout', $html, 'kein Zugang ueber das Setup-Formular');
        $this->assertStringEqualsFile($this->path($c, 'config/lmo-auth.php'), $before);
    }

    public function testDeletedAccountFileEndsAdminSession(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        unlink($this->path($c, 'config/lmo-auth.php'));

        $html = $c->get('lmoadmin.php', 'action=admin');

        $this->assertStringContainsString('name="setup_pass"', $html);
        $this->assertStringNotContainsString('todo=logout', $html, 'alte Sitzung gilt nicht mehr');
    }
}
