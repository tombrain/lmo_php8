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
        file_put_contents($path . '/addon/tipp/lmo-tippauth.txt', "<?php exit; ?>\ntester|geheim|5|Tina Test|tester@example.org|||||1|1|EOL\n");
        // alle Ligen tippbar: sonst bietet "Ligen hinzufuegen" keine an
        $cfg = $path . '/config/tipp/cfg.txt';
        file_put_contents($cfg, (string) preg_replace('/^immeralle=.*$/m', 'immeralle=1', (string) file_get_contents($cfg), 1, $count));
        self::assertSame(1, $count, 'immeralle steht in der Konfiguration des Tippspiels');
        // eine zweite Liga im obersten Ligenordner: nur dort sucht "Ligen hinzufuegen"
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/basic.l98');
        $client->instance()->freezeLeagueTimes();
        file_put_contents($path . '/addon/tipp/tipps/1l_2024-25_tester.tip', "[Round26]\nGA1=2\nGB1=1\n");
        touch($path . '/addon/tipp/tipps/1l_2024-25_tester.tip', mktime(12, 0, 0, 3, 10, 2025));
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
