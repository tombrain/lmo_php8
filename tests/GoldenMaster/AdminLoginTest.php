<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Anmeldung, Abmeldung und Rollen (Hauptadmin, Hilfsadmin, erweiterter Hilfsadmin).
 *
 * Beim ersten erfolgreichen Login ersetzt das Original das Klartext-Kennwort in
 * config/lmo-auth.php durch einen Hash mit Zufalls-Salt (Silent Upgrade). Der Hash steht
 * im Protokoll als {HASH}.
 */
final class AdminLoginTest extends TestCase
{
    use AssertsSnapshots;

    public function testLoginAndLogoutFlow(): void
    {
        [$app, $runner] = Fixture::freshInstance();
        $c = new AdminClient($app, $runner);
        $t = new Transcript();

        $t->add('GET Anmeldeformular', $c->get('lmoadmin.php', 'action=admin'), $c->changes());
        $t->add('POST falsches Kennwort', $c->login('admin', 'falsch'), $c->changes());
        $t->add('POST unbekannter Benutzer', $c->login('niemand', 'lmo'), $c->changes());
        $t->add('POST richtiges Kennwort (Klartext in der Datei)', $c->login('admin', 'lmo'), $c->changes());
        $t->add('GET Startseite Admin', $c->get('lmoadmin.php', 'action=admin'), $c->changes());
        $t->add('GET Abmelden', $c->get('lmoadmin.php', 'action=admin&todo=logout'), $c->changes());
        $t->add('GET nach dem Abmelden', $c->get('lmoadmin.php', 'action=admin'), $c->changes());
        $t->add('POST Anmeldung mit bereits gehashtem Kennwort', $c->login('admin', 'lmo'), $c->changes());

        $this->assertMatchesSnapshot('admin-flows', 'login_logout', $t->text());
    }

    public function testHelperAdminRoles(): void
    {
        [$app, $runner] = Fixture::freshInstance();
        file_put_contents(
            $app->path() . '/config/lmo-auth.php',
            "<?php exit(); ?>\nadmin|lmo|2|||\nhilfe|geheim|1|basic|0\nerweitert|geheim|1|basic|1\n"
        );
        $c = new AdminClient($app, $runner);
        $t = new Transcript();
        $a = 'action=admin';

        $t->add('Hilfsadmin: Anmeldung', $c->login('hilfe', 'geheim'), $c->changes());
        $t->add('Hilfsadmin: Startseite', $c->get('lmoadmin.php', $a), $c->changes());
        $t->add('Hilfsadmin: eigene Liga bearbeiten', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/basic.l98&st=1'), $c->changes());
        $t->add('Hilfsadmin: Grunddaten der eigenen Liga', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/basic.l98&st=-1'), $c->changes());
        $t->add('Hilfsadmin: Mannschaften (nur erweitert)', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/basic.l98&st=-2'), $c->changes());
        $t->add('Hilfsadmin: fremde Liga', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/special.l98&st=1'), $c->changes());
        $t->add('Hilfsadmin: Liga anlegen (nicht erlaubt)', $c->get('lmoadmin.php', $a . '&todo=new'), $c->changes());
        $t->add('Hilfsadmin: Optionen (nicht erlaubt)', $c->get('lmoadmin.php', $a . '&todo=options'), $c->changes());
        $t->add('Hilfsadmin: Liga oeffnen', $c->get('lmoadmin.php', $a . '&todo=open'), $c->changes());
        $t->add('Hilfsadmin: Download', $c->get('lmoadmin.php', $a . '&todo=download&file=golden/basic.l98'), $c->changes());
        $t->add('Hilfsadmin: Tabellenkorrektur', $c->get('lmoadmin.php', $a . '&todo=tabs&file=golden/basic.l98'), $c->changes());
        $t->add('Hilfsadmin: Abmelden', $c->get('lmoadmin.php', $a . '&todo=logout'), $c->changes());

        $t->add('Erweiterter Hilfsadmin: Anmeldung', $c->login('erweitert', 'geheim'), $c->changes());
        $t->add('Erweiterter Hilfsadmin: Mannschaften', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/basic.l98&st=-2'), $c->changes());
        $t->add('Erweiterter Hilfsadmin: Anzahl', $c->get('lmoadmin.php', $a . '&todo=edit&file=golden/basic.l98&st=-3'), $c->changes());

        $this->assertMatchesSnapshot('admin-flows', 'helper_roles', $t->text());
    }
}
