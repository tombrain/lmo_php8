<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Sicherheitsprobe: wie verhaelt sich der Admin-Bereich ohne Anmeldung?
 *
 * Hintergrund: init.php uebernimmt $_GET, $_POST und $_COOKIE per extract() in den globalen
 * Scope (Nachbau von register_globals). extract() schuetzt nur $this und $GLOBALS, nicht $_SESSION.
 * Ob Sitzungswerte dadurch ueberschreibbar sind, wird hier an einer lokalen Testinstanz geprueft.
 *
 * Bis 4.5 meldete ?_SESSION[lmouserok]=2 als Hauptadmin an, dauerhaft in der Sitzung. Seitdem
 * uebernimmt init.php keine Namen mit _ am Anfang mehr: ohne Anmeldung kommt die Anmeldeseite, der
 * Download liefert nichts.
 */
final class AdminAuthProbeTest extends TestCase
{
    use AssertsSnapshots;

    public function testAdminAreaWithoutLogin(): void
    {
        $c = Fixture::anonymousClient();
        $t = new Transcript();
        $a = 'action=admin';

        $t->add('ohne Anmeldung: Optionen', $c->get('lmoadmin.php', $a . '&todo=options'), $c->changes());
        $t->add('ohne Anmeldung: Parameter lmouserok', $c->get('lmoadmin.php', $a . '&todo=options&lmouserok=2'), $c->changes());
        $t->add('ohne Anmeldung: Sitzungswert im Parameter', $c->get('lmoadmin.php', $a . '&todo=options&_SESSION[lmouserok]=2'), $c->changes());
        $t->add('falsches Kennwort plus Parameter', $c->post('lmoadmin.php', '', [
            'action' => 'admin', 'xusername' => 'admin', 'xuserpass' => 'falsch', 'lmouserok' => '2',
        ]), $c->changes());
        $download = $c->get('lmo-admindownload.php', $a . '&todo=download&down=1');
        self::assertStringNotContainsString('[Options]', $download, 'keine Liga ohne Anmeldung, auch nicht nach den Versuchen oben');
        $t->add('Download ohne Anmeldung', $download, $c->changes());
        $download = $c->get('lmo-admindownload.php', $a . '&todo=download&down=1&_SESSION[lmouserok]=2');
        self::assertStringNotContainsString('[Options]', $download, 'der Sitzungswert im Parameter meldet nicht an');
        $t->add('Download mit Sitzungswert im Parameter', $download, $c->changes());

        $this->assertMatchesSnapshot('security-probe', 'admin_without_login', $t->text());
    }

    public function testDirectAccessToIncludeFiles(): void
    {
        $c = Fixture::anonymousClient();
        $t = new Transcript();

        foreach ([
            'lmo-adminmain.php' => 'action=admin',
            'lmo-adminmimesend.php' => '',
            'lmo-admincal.php' => '',
            'lmo-openfile.php' => 'file=golden/basic.l98',
        ] as $script => $query) {
            $t->add('direkter Aufruf ' . $script, $c->get($script, $query), $c->changes());
        }

        $this->assertMatchesSnapshot('security-probe', 'direct_include_access', $t->text());
    }
}
