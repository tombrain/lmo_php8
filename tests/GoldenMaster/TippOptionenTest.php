<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Die Optionen des Tippspiels in der Administration (todo=tippoptions): jede der 13 Seiten (show)
 * ansehen und speichern, mit ungueltigen und abhaengigen Werten. Festgehalten werden Seite und
 * Aenderungen an config/tipp/cfg.txt.
 */
final class TippOptionenTest extends TestCase
{
    use AssertsSnapshots;

    private static function client(): AdminClient
    {
        $client = Fixture::loggedInClient();
        // eine zweite Liga im Ligenordner fuer die Auswahl der Ligen, die getippt werden
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/tippliga.l98');
        $client->instance()->freezeLeagueTimes();
        $client->resetChanges();

        return $client;
    }

    public function testAnsehenUndSpeichern(): void
    {
        $c = self::client();
        $t = new Transcript();
        $a = ['action' => 'admin', 'todo' => 'tippoptions', 'save' => '1'];
        $speichere = static function (string $titel, int $show, array $werte) use ($c, $t, $a): void {
            $t->add($titel, $c->post('lmoadmin.php', '', $a + ['show' => (string) $show] + $werte), $c->changes());
        };

        $t->add('Start ohne show', $c->get('lmoadmin.php', 'action=admin&todo=tippoptions'), $c->changes());
        for ($show = 1; $show <= 12; $show++) {
            $t->add('show=' . $show, $c->get('lmoadmin.php', 'action=admin&todo=tippoptions&show=' . $show), $c->changes());
        }

        $speichere('2: Punkte, Ergebnis kleiner als Tendenz und Differenz, Tor keine Zahl', 2, [
            'xrergebnis' => '2', 'xrtendenzdiff' => '1', 'xrtendenz' => '5', 'xrtor' => 'abc', 'xrtendenztor' => '0',
            'xrtendenzremis' => '0', 'xrremis' => '0', 'xentscheidungnv' => 'on', 'xgtpunkte' => '2',
        ]);
        $speichere('0: Tendenz-Modus', 0, ['xtippmodus' => '0']);
        $speichere('2: im Tendenz-Modus nur Remis und Entscheidungen', 2, ['xrergebnis' => '9', 'xrremis' => '3', 'xentscheidungie' => 'on', 'xgtpunkte' => '1']);
        $speichere('6: im Tendenz-Modus', 6, ['xshowtendenzpro' => '1', 'xviewertipp' => 'on', 'xviewertage' => '0', 'xakteinsicht' => 'on']);
        $speichere('1: Tippordner mit Backslash', 1, ['xdirtipp' => 'meine\\tipps']);
        $speichere('1: ohne Tippordner', 1, []);
        $speichere('3: Anzeige', 3, ['xtippeinsicht' => 'on', 'xregeln' => 'on', 'xregelnlink' => 'regeln&mehr.php', 'xshowname' => '1']);
        $speichere('3: ohne Regeln, nichts angezeigt', 3, ['xgesamt' => 'on', 'xregelnlink' => 'anders.php']);
        $speichere('4: Tippabgabe, ungueltige Zahlen', 4, ['xtippohne' => '0', 'xtippbis' => 'x', 'xtipperteam' => '5', 'ximvorraus' => 'abc',
            'xjokertipp' => 'on', 'xjokertippmulti' => '0.5']);
        $speichere('4: ohne Joker', 4, ['xtippohne' => '1', 'xtippbis' => '30', 'xtipperteam' => '0', 'ximvorraus' => '2', 'xjokertippmulti' => '3']);
        $speichere('5: Anmeldung', 5, ['xadresse' => 'on', 'xfreischaltung' => 'on']);
        $speichere('7: Einsicht, Tipper je Seite negativ', 7, ['xeinsichterst' => '2', 'xanzseite' => '-3']);
        $speichere('8: Tabelle', 8, ['xtipptabelle' => 'on']);
        $speichere('9: Gesamtwertung', 9, ['xnurgesamt' => 'on', 'xanzseite1' => '12', 'xshowzus' => 'on']);
        $speichere('10: Kriterien', 10, ['xkrit1' => '3', 'xkrit3' => '6']);
        $speichere('11: Auswertung', 11, ['xaktauswert' => 'on']);
        $speichere('12: zwei Ligen', 12, ['xtipperligen' => ['1l_2024-25', 'tippliga']]);
        $speichere('12: immer alle', 12, ['ximmeralle' => 'on', 'xtipperligen' => ['tippliga']]);

        $this->assertMatchesSnapshot('tipp', 'optionen', $t->text());
    }
}
