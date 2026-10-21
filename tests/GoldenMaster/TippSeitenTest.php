<?php

declare(strict_types=1);

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Die Seiten des Tippspiels rund um Anmeldung und Konto, die die anderen Tippspiel-Tests nicht
 * zeigen: Anmeldung (lmo-tippauth.php), Startseite (lmo-tipppad.php), Info, Abmelden, Kennwort
 * vergessen, Registrierung (lmo-tippernew.php), persoenliche Daten (lmo-tippdaten.php), Kennwort
 * aendern (lmo-tipppwchange.php), Ligen hinzufuegen und entfernen (lmo-tippnewligen.php,
 * lmo-tippdelligen.php), Konto loeschen (lmo-tippdelaccount.php) und die Tippregeln
 * (tippregeln.php, direkt aufgerufen).
 *
 * Ein freigeschalteter Tipper tippt die Bundesliga; alle Ligen sind tippbar (immeralle), eine
 * nimmt er dazu und entfernt sie wieder.
 */
final class TippSeitenTest extends TestCase
{
    use AssertsSnapshots;

    private static function client(): AdminClient
    {
        $client = Fixture::anonymousClient();
        $path = $client->instance()->path();
        // nick|kennwort|status|name|email|verein|strasse|plz|ort|newsletter|erinnerung|EOL
        file_put_contents($path . '/daten/tipp/lmo-tippauth.txt', "<?php exit; ?>\ntester|geheim|5|Tina Test|tester@example.org|||||1|1|EOL\n");
        // alle Ligen tippbar: sonst bietet "Ligen hinzufuegen" keine an
        $cfg = $path . '/config/tipp/cfg.txt';
        file_put_contents($cfg, (string) preg_replace('/^immeralle=.*$/m', 'immeralle=1', (string) file_get_contents($cfg), 1, $count));
        self::assertSame(1, $count, 'immeralle steht in der Konfiguration des Tippspiels');
        // eine zweite Liga im obersten Ligenordner: nur dort sucht "Ligen hinzufuegen"
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/basic.l98');
        $client->instance()->freezeLeagueTimes();
        file_put_contents($path . '/daten/tipp/tipps/1l_2024-25_tester.tip', "[Round26]\nGA1=2\nGB1=1\n");
        touch($path . '/daten/tipp/tipps/1l_2024-25_tester.tip', mktime(12, 0, 0, 3, 10, 2025));
        $client->resetChanges();

        return $client;
    }

    private static function anmelden(AdminClient $c, string $kennwort = 'geheim'): string
    {
        return $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => $kennwort]);
    }

    public function testAnmeldungStartseiteUndAbmelden(): void
    {
        $c = self::client();
        $t = new Transcript();

        $t->add('Tippspiel ohne Anmeldung', $c->get('lmo.php', 'action=tipp'), $c->changes());
        $t->add('Info ohne Anmeldung', $c->get('lmo.php', 'action=tipp&todo=info'), $c->changes());
        $t->add('Anmeldung mit falschem Kennwort', self::anmelden($c, 'falsch'), $c->changes());
        $t->add('Anmeldung', self::anmelden($c), self::ohneKennwort($c->changes()));
        $t->add('Startseite des Tippers', $c->get('lmo.php', 'action=tipp'), $c->changes());
        $t->add('Info', $c->get('lmo.php', 'action=tipp&todo=info'), $c->changes());
        $t->add('Abmelden', $c->get('lmo.php', 'action=tipp&todo=logout'), $c->changes());
        $t->add('Kennwort vergessen', $c->get('lmo.php', 'action=tipp&todo=getpass'), $c->changes());

        $this->assertMatchesSnapshot('tipp-seiten', 'anmeldung', $t->text());
    }

    public function testRegistrierung(): void
    {
        $c = self::client();
        $t = new Transcript();

        $t->add('Formular', $c->get('lmo.php', 'action=tipp&todo=newtipper'), $c->changes());
        $t->add('Ohne Angaben abschicken', $c->submitFromLast('xtippernick'), $c->changes());
        $c->get('lmo.php', 'action=tipp&todo=newtipper');
        $t->add('Neuer Tipper', $c->submitFromLast('xtippernick', [
            'xtippernick' => 'neuling', 'xtippervorname' => 'Nora', 'xtippernachname' => 'Neu', 'xtipperemail' => 'nora@example.org',
            'xtipperemail2' => 'nora@example.org', 'xtipperpass' => 'neu123', 'xtipperpassw' => 'neu123', 'xtippervereinradio' => '0',
        ]), self::ohneKennwort($c->changes()));

        $this->assertMatchesSnapshot('tipp-seiten', 'registrierung', $t->text());
    }

    public function testKonto(): void
    {
        $c = self::client();
        $t = new Transcript();
        self::anmelden($c);
        $c->changes();

        $t->add('Persoenliche Daten', $c->get('lmo.php', 'action=tipp&todo=daten'), $c->changes());
        // unveraendert speichern: eine neue E-Mail-Adresse setzt ein zufaelliges Kennwort und verschickt es
        $t->add('Daten unveraendert speichern', $c->submitFromLast('xtipperemail'), self::ohneKennwort($c->changes()));
        $t->add('Kennwort aendern: Formular', $c->get('lmo.php', 'action=tipp&todo=pwchange'), $c->changes());
        $t->add('Kennwort aendern: neue Kennwoerter verschieden', $c->submitFromLast('xtipperpassneu', [
            'xtipperpass' => 'geheim', 'xtipperpassneu' => 'neu1', 'xtipperpassneuw' => 'neu2',
        ]), self::ohneKennwort($c->changes()));
        $c->get('lmo.php', 'action=tipp&todo=pwchange');
        $t->add('Kennwort aendern', $c->submitFromLast('xtipperpassneu', [
            'xtipperpass' => 'geheim', 'xtipperpassneu' => 'anders', 'xtipperpassneuw' => 'anders',
        ]), self::ohneKennwort($c->changes()));
        $t->add('Ligen hinzufuegen: Auswahl', $c->get('lmo.php', 'action=tipp&todo=newligen'), $c->changes());
        $t->add('Ligen hinzufuegen', $c->submitFromLast('xtippersub', ['xtipperligen[]' => ['basic']]), $c->changes());
        $t->add('Ligen entfernen: Auswahl', $c->get('lmo.php', 'action=tipp&todo=delligen'), $c->changes());
        $t->add('Ligen entfernen', $c->submitFromLast('xtippersub', ['xtipperligen[]' => ['basic']]), $c->changes());
        $t->add('Konto loeschen: Formular', $c->get('lmo.php', 'action=tipp&todo=delaccount'), $c->changes());
        $t->add('Konto loeschen', $c->submitFromLast('xtipperpass', ['xtipperpass' => 'anders']), self::ohneKennwort($c->changes()));

        $this->assertMatchesSnapshot('tipp-seiten', 'konto', $t->text());
    }

    /**
     * Eine neue E-Mail-Adresse bekommt ein neues Kennwort per Mail; gespeichert wird nur sein Hash,
     * und mit dem Kennwort aus der Mail kann sich der Tipper anmelden.
     */
    public function testNeueEmailAdresseBekommtEinNeuesKennwort(): void
    {
        $c = self::client();
        self::anmelden($c);
        $c->get('lmo.php', 'action=tipp&todo=daten');
        $c->submitFromLast('xtipperemail', ['xtipperemail' => 'tina@example.org']);

        $path = $c->instance()->path();
        $zeilen = file($path . '/daten/tipp/lmo-tippauth.txt', FILE_IGNORE_NEW_LINES) ?: [];
        $hash = explode('|', $zeilen[1] ?? '')[1] ?? '';
        $mails = glob($path . '/.mails/*.eml') ?: [];
        self::assertCount(1, $mails, 'Mail mit dem neuen Kennwort');
        $teile = preg_split('/\R\R/', (string) file_get_contents($mails[0]), 2) ?: [];
        self::assertSame(1, preg_match('/Passwort: (\S+)/', (string) base64_decode($teile[1] ?? ''), $treffer), 'Kennwort in der Mail');

        self::assertStringStartsWith('$', $hash, 'gespeichert ist ein Hash');
        self::assertTrue(password_verify($treffer[1], $hash), 'der Hash passt zum Kennwort aus der Mail');
        $c->get('lmo.php', 'action=tipp&todo=logout');
        self::assertStringContainsString('todo=logout', self::anmelden($c, $treffer[1]), 'Anmeldung mit dem Kennwort aus der Mail');
    }

    /** "Kennwort vergessen": ein neues Kennwort per Mail; mit ihm meldet sich der Tipper an. */
    public function testKennwortVergessen(): void
    {
        $c = self::client();
        $c->get('lmo.php', 'action=tipp&todo=getpass');
        $antwort = $c->post('lmo.php', '', ['action' => 'tipp', 'todo' => 'getpass', 'xtippername2' => 'tester@example.org', 'xtippersub' => 'x']);

        self::assertStringContainsString('class="message"', $antwort, 'Erfolgsmeldung');
        $path = $c->instance()->path();
        $mails = glob($path . '/.mails/*.eml') ?: [];
        self::assertCount(1, $mails);
        $teile = preg_split('/\R\R/', (string) file_get_contents($mails[0]), 2) ?: [];
        self::assertSame(1, preg_match('/Passwort: (\S+)/', (string) base64_decode($teile[1] ?? ''), $treffer), 'Kennwort in der Mail');
        self::assertStringStartsWith("<?php exit; ?>\n", (string) file_get_contents($path . '/daten/tipp/lmo-tippauth.txt'), 'die Schutzzeile bleibt');
        self::assertStringContainsString('todo=logout', self::anmelden($c, $treffer[1]), 'Anmeldung mit dem neuen Kennwort');

        $unbekannt = $c->post('lmo.php', '', ['action' => 'tipp', 'todo' => 'getpass', 'xtippername2' => 'niemand']);
        self::assertStringContainsString('name="xtippername2"', $unbekannt);
        self::assertCount(1, glob($path . '/.mails/*.eml') ?: [], 'keine weitere Mail');
    }

    /**
     * Die Einsicht ohne Anmeldung (lmo-tippeinsicht.php): ohne Einsichtsdatei, mit drei Tippern auf
     * zwei Seiten (einer mit Joker), auf der zweiten Seite und im Tendenz-Modus.
     */
    public function testEinsichtOhneAnmeldung(): void
    {
        $c = self::client();
        $path = $c->instance()->path();
        $cfg = $path . '/config/tipp/cfg.txt';
        $einstellungen = (string) file_get_contents($cfg);
        foreach (['einsichterst' => '0', 'anzseite' => '2'] as $name => $wert) {
            $einstellungen = (string) preg_replace('/^' . $name . '=.*$/m', $name . '=' . $wert, $einstellungen, 1);
        }
        file_put_contents($cfg, $einstellungen);
        @mkdir($path . '/daten/tipp/tipps/einsicht', 0777, true);
        file_put_contents($path . '/daten/tipp/tipps/einsicht/1l_2024-25_26.ein', "\n[zora]\nGA1=1\nGB1=1\nGA2=-1\nGB2=-1\n\n[anna]\n@2@\nGA1=2\nGB1=1\nGA2=0\nGB2=3\n\n[Bert]\nGA1=0\nGB1=2\nGA2=1\nGB2=1\n");
        $c->resetChanges();
        $t = new Transcript();

        $t->add('Ohne Einsichtsdatei', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=1l_2024-25.l98&st=25'), $c->changes());
        $t->add('Erste Seite', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=1l_2024-25.l98&st=26'), $c->changes());
        $t->add('Zweite Seite', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=1l_2024-25.l98&st=26&start=2'), $c->changes());
        file_put_contents($cfg, (string) preg_replace('/^tippmodus=.*$/m', 'tippmodus=0', $einstellungen, 1));
        $t->add('Tendenz-Modus', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=1l_2024-25.l98&st=26'), $c->changes());

        $this->assertMatchesSnapshot('tipp-seiten', 'einsicht-gaeste', $t->text());
    }

    /**
     * Die Tabelle nach den Tipps ohne Anmeldung (lmo-tipptabelle.php): mit den Tipps aller und
     * eines Tippers ohne Auswertung nach Vereinen, im Tendenz-Modus mit den Punkten in der Mitte.
     */
    public function testTabelleOhneAnmeldung(): void
    {
        $c = self::client();
        $path = $c->instance()->path();
        $cfg = $path . '/config/tipp/cfg.txt';
        $einstellungen = (string) file_get_contents($cfg);
        $c->resetChanges();
        $t = new Transcript();

        $t->add('Alle Tipper', $c->get('lmo.php', 'action=tipp&todo=tabelle&file=1l_2024-25.l98'), $c->changes());
        $t->add('Ein Tipper ohne Auswertung', $c->get('lmo.php', 'action=tipp&todo=tabelle&file=1l_2024-25.l98&nick=tester&st=26'), $c->changes());
        file_put_contents($cfg, (string) preg_replace('/^tippmodus=.*$/m', 'tippmodus=0', $einstellungen, 1));
        $haupt = $path . '/config/cfg.txt';
        file_put_contents($haupt, (string) preg_replace('/^tabpkt=.*$/m', 'tabpkt=0', (string) file_get_contents($haupt), 1));
        $t->add('Tendenz-Modus, Punkte in der Mitte', $c->get('lmo.php', 'action=tipp&todo=tabelle&file=1l_2024-25.l98&nick=tester&st=26&tabtype=1'), $c->changes());

        $this->assertMatchesSnapshot('tipp-seiten', 'tabelle-gaeste', $t->text());
    }

    public function testTippregeln(): void
    {
        $c = self::client();

        $this->assertMatchesSnapshot('tipp-seiten', 'tippregeln', $c->get('addon/tipp/tippregeln.php'));
    }

    /** Beim Anmelden und Aendern wird das Kennwort gehasht (zufaelliges Salz). */
    private static function ohneKennwort(string $changes): string
    {
        return (string) preg_replace('/\$(argon2id?|2y)\$[^|\s]+/', '{HASH}', $changes);
    }
}
