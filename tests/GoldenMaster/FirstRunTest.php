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

    private static function assertNoPhpMessages(string $html): void
    {
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen in der Ausgabe');
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
        self::assertFileDoesNotExist($this->path($c, 'config/cfg.txt'));

        $html = $c->get('lmo.php');

        self::assertNoPhpMessages($html);
        self::assertStringNotContainsString('Installation des Liga Manager Online', $html);
        self::assertStringContainsString('1l_2024-25', $html, 'Ligenuebersicht mit der Beispielliga');
        // URL ermittelt aus Host + Skriptpfad (Harness: http://lmo.test/lmo/lmo.php)
        self::assertStringContainsString('href="http://lmo.test/lmo/lmo-style-nc.php"', $html);

        foreach (['config/cfg.txt', 'config/tipp/cfg.txt', 'config/spieler/cfg.txt', 'config/ticker/cfg.txt',
            'config/mini/cfg.txt', 'config/classlib/cfg.txt', 'addon/tipp/lmo-tippauth.txt'] as $file) {
            self::assertFileExists($this->path($c, $file));
        }
        self::assertFileEquals($this->path($c, 'config-default/cfg.txt'), $this->path($c, 'config/cfg.txt'));
        self::assertDirectoryExists($this->path($c, 'ligen/archiv'));
        self::assertDirectoryExists($this->path($c, 'output'));
        // kein Standardkonto admin/lmo und keine festen Pfade mehr
        self::assertFileDoesNotExist($this->path($c, 'config/lmo-auth.php'));
        self::assertFileDoesNotExist($this->path($c, 'config/init-parameters.php'));
    }

    public function testExistingConfigurationIsNotOverwritten(): void
    {
        $c = $this->client();
        self::assertDirectoryExists($this->path($c, 'config/tipp'));
        $custom = file_get_contents($this->path($c, 'config-default/tipp/cfg.txt')) . "\n; eigene Aenderung\n";
        file_put_contents($this->path($c, 'config/tipp/cfg.txt'), $custom);

        self::assertNoPhpMessages($c->get('lmo.php'));

        self::assertStringEqualsFile($this->path($c, 'config/tipp/cfg.txt'), $custom);
        self::assertFileExists($this->path($c, 'config/cfg.txt'));
    }

    public function testFixedPathAndUrlFromInitParametersStillWin(): void
    {
        $c = $this->client();
        file_put_contents(
            $this->path($c, 'config/init-parameters.php'),
            "<?php\n\$lmo_dateipfad='" . $c->instance()->path() . "';\n\$lmo_url='https://fest.example/liga';\n"
        );

        $html = $c->get('lmo.php');

        self::assertNoPhpMessages($html);
        self::assertStringContainsString('href="https://fest.example/liga/lmo-style-nc.php"', $html);
    }

    public function testInstallerAndFtpCodeAreGone(): void
    {
        $c = $this->client();
        self::assertDirectoryDoesNotExist($this->path($c, 'install'));
        foreach (['FTP', 'Socket', 'PEAR', 'PEAR5', 'Lite', 'LiteOutput'] as $class) {
            self::assertFileDoesNotExist($this->path($c, "includes/$class.php"));
        }
        self::assertFileDoesNotExist($this->path($c, 'config-default/lmo-auth.php'), 'kein Standardkonto mehr');

        // frueherer Einstieg in den Installer fuehrt jetzt zur normalen Seite
        foreach (['lmo.php', 'lmoadmin.php'] as $script) {
            $html = $c->post($script, '', ['lmo_install_step' => '3', 'path' => '/tmp/boese', 'url' => 'http://boese.example']);
            self::assertNoPhpMessages($html);
            self::assertStringNotContainsString('Installation des Liga Manager Online', $html);
            self::assertStringNotContainsString('boese', $html);
        }
        self::assertFileDoesNotExist($this->path($c, 'config/init-parameters.php'));
    }

    public function testNoInstallerWarningInAdminArea(): void
    {
        $c = $this->client();
        $html = $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        self::assertStringContainsString('todo=logout', $html);
        self::assertStringNotContainsString('Delete install folder', $html);
    }

    public function testMissingDefaultsShowAClearMessage(): void
    {
        $c = $this->client();
        exec('rm -rf ' . escapeshellarg($this->path($c, 'config-default')));

        $html = $c->get('lmo.php');

        self::assertStringContainsString('LMO: config/cfg.txt fehlt und config-default/ ist nicht vorhanden.', $html);
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

        self::assertNoPhpMessages($c->get('lmo.php'));

        $text = file_get_contents($cfg);
        self::assertMatchesRegularExpression('/^tabpkt=7$/m', $text, 'eigener Wert bleibt');
        self::assertSame(1, preg_match_all('/^neueoption=42$/m', $text), 'neue Option genau einmal ergaenzt');
        self::assertSame(1, preg_match_all('/^tabpkt=/m', $text), 'keine doppelten Schluessel');
        self::assertSame($tippBefore . "tippneu=ja\n", file_get_contents($tipp));
        self::assertSame('42', parse_ini_file($cfg)['neueoption']);
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
        self::assertSame($before, file_get_contents($cfg));

        // nach einem Update (config-default/ geaendert) wird die fehlende Option ergaenzt
        touch($this->path($c, 'config-default/cfg.txt'), time() + 60);
        $c->get('lmo.php');
        self::assertMatchesRegularExpression('/^tabonres=/m', file_get_contents($cfg));
    }

    public function testUnwritableConfigFolderShowsAClearMessage(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Als root sind alle Ordner beschreibbar (Docker-Testlauf).');
        }
        $c = $this->client();
        chmod($this->path($c, 'config'), 0555);
        try {
            $html = $c->get('lmo.php');
        } finally {
            chmod($this->path($c, 'config'), 0777);
        }
        self::assertStringContainsString('Der Ordner config/ ist nicht beschreibbar', $html);
    }

    public function testAdminSetupFormInsteadOfLogin(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin');

        self::assertNoPhpMessages($html);
        self::assertStringContainsString('name="setup_pass"', $html);
        self::assertStringNotContainsString('name="xuserpass"', $html, 'kein Login ohne Konto');
        self::assertStringNotContainsString('todo=logout', $html);
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

        self::assertNoPhpMessages($html);
        self::assertStringContainsString($message, $html);
        self::assertStringContainsString('name="setup_pass"', $html, 'Formular erneut anzeigen');
        self::assertStringNotContainsString('todo=logout', $html);
        self::assertFileDoesNotExist($this->path($c, 'config/lmo-auth.php'));
    }

    public function testSetupTextsExistInEveryLanguageFile(): void
    {
        $files = glob(Fixture::sourceRoot() . '/lmo/lang/lang-*.txt');
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $keys = [];
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^(\d+)=(.*)$/', rtrim($line, "\r"), $m)) {
                    $keys[(int)$m[1]] = $m[2];
                }
            }
            foreach (range(5013, 5023) as $key) {
                self::assertNotSame('', $keys[$key] ?? '', basename($file) . ": Text $key fehlt");
            }
            self::assertTrue(mb_check_encoding((string)file_get_contents($file), 'UTF-8'), basename($file) . ': kein UTF-8');
        }
    }

    public function testSetupFormOffersLanguageSelection(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin');

        self::assertNoPhpMessages($html);
        self::assertStringContainsString('lmouserlang=English', $html, 'Link zur englischen Sprache');
        self::assertStringContainsString('lmouserlang=Francais', $html);
        self::assertStringContainsString('Deutsch.selected.svg', $html, 'aktuelle Sprache markiert');
        self::assertStringNotContainsString('lmo-admintranslate.php', $html, 'Uebersetzungswerkzeug nur fuer angemeldete Admins');
    }

    public function testLoginFormOffersLanguageSelection(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));

        $html = $other->get('lmoadmin.php', 'action=admin');
        self::assertStringContainsString('name="xuserpass"', $html);
        self::assertStringContainsString('lmouserlang=English', $html);
        self::assertStringContainsString('Deutsch.selected.svg', $html);
        self::assertStringNotContainsString('lmo-admintranslate.php', $html, 'Uebersetzungswerkzeug erst nach dem Login');

        // Umschalten wirkt auf das Anmeldeformular und bleibt beim Anmelden erhalten
        $html = $other->get('lmoadmin.php', 'action=admin&lmouserlang=English');
        self::assertStringContainsString('Log in to the Admin area', $html);
        self::assertStringContainsString('English.selected.svg', $html);
        $html = $other->login('chef', self::PASSWORD);
        self::assertStringContainsString('todo=logout', $html);
        self::assertStringContainsString('English.selected.svg', $html);
    }

    public function testLanguageSelectionCanBeSwitchedOff(): void
    {
        $c = $this->client();
        $c->get('lmo.php'); // Ersteinrichtung legt cfg.txt an
        $cfg = $this->path($c, 'config/cfg.txt');
        file_put_contents($cfg, preg_replace('/^einsprachwahl=.*$/m', 'einsprachwahl=0', file_get_contents($cfg)));

        $html = $c->get('lmoadmin.php', 'action=admin');

        self::assertStringContainsString('name="setup_pass"', $html);
        self::assertStringNotContainsString('lmouserlang=English', $html);

        // ebenso im Anmeldeformular
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        $html = $other->get('lmoadmin.php', 'action=admin');
        self::assertStringContainsString('name="xuserpass"', $html);
        self::assertStringNotContainsString('lmouserlang=English', $html);
    }

    public function testSetupFormFollowsTheSelectedLanguage(): void
    {
        $c = $this->client();
        $html = $c->get('lmoadmin.php', 'action=admin&lmouserlang=English');

        self::assertNoPhpMessages($html);
        self::assertStringContainsString('First-time setup', $html);
        self::assertStringContainsString('Create admin account', $html);
        self::assertStringNotContainsString('Ersteinrichtung', $html);

        // Fehlermeldungen ebenfalls aus der Sprachdatei (Sprache bleibt in der Sitzung)
        $html = $this->submitSetup($c, 'admin', 'kurz', 'kurz');
        self::assertStringContainsString('The password must be at least 8 characters long.', $html);
    }

    public function testSetupCreatesHashedAdminAndLogsIn(): void
    {
        $c = $this->client();
        $html = $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);

        self::assertNoPhpMessages($html);
        self::assertStringContainsString('todo=logout', $html, 'direkt angemeldet');
        self::assertStringNotContainsString('name="setup_pass"', $html);

        $lines = file($this->path($c, 'config/lmo-auth.php'), FILE_IGNORE_NEW_LINES);
        self::assertSame('<?php exit(); ?>', $lines[0], 'Schutzzeile gegen Abruf im Browser');
        $fields = explode('|', $lines[1]);
        self::assertSame('chef', $fields[0]);
        self::assertNotSame(self::PASSWORD, $fields[1], 'kein Klartext');
        self::assertTrue(password_verify(self::PASSWORD, $fields[1]));
        self::assertSame('2', $fields[2], 'Hauptadmin');

        // Sitzung bleibt bestehen
        self::assertStringContainsString('todo=logout', $c->get('lmoadmin.php', 'action=admin'));
    }

    public function testLoginWithNewAccountInNewSession(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);

        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        self::assertStringContainsString('name="xuserpass"', $other->get('lmoadmin.php', 'action=admin'));
        self::assertStringNotContainsString('todo=logout', $other->login('admin', 'lmo'), 'altes Standardkonto gibt es nicht');
        self::assertStringNotContainsString('todo=logout', $other->login('chef', 'falsch123'));
        self::assertStringContainsString('todo=logout', $other->login('chef', self::PASSWORD));
    }

    public function testSecondSetupIsIgnored(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        $before = file_get_contents($this->path($c, 'config/lmo-auth.php'));

        $other = new AdminClient($c->instance(), new \Lmo\Tests\GoldenMaster\Support\Runner($c->instance()));
        $html = $this->submitSetup($other, 'angreifer', 'boese12345', 'boese12345');

        self::assertStringNotContainsString('todo=logout', $html, 'kein Zugang ueber das Setup-Formular');
        self::assertStringEqualsFile($this->path($c, 'config/lmo-auth.php'), $before);
    }

    public function testDeletedAccountFileEndsAdminSession(): void
    {
        $c = $this->client();
        $this->submitSetup($c, 'chef', self::PASSWORD, self::PASSWORD);
        unlink($this->path($c, 'config/lmo-auth.php'));

        $html = $c->get('lmoadmin.php', 'action=admin');

        self::assertStringContainsString('name="setup_pass"', $html);
        self::assertStringNotContainsString('todo=logout', $html, 'alte Sitzung gilt nicht mehr');
    }
}
