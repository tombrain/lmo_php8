<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Auswertung des Tippspiels: Der Admin laesst die Tipps einer Liga auswerten
 * (todo=tipp mit save=2, Lmo\Addon\Tipp\AdminAuswertungController -> lmo-tippsavewert.php schreibt tipps/auswert/<liga>.aus), die
 * Tipper sehen danach Rangliste (todo=wert), Fieberkurve (todo=fieber) und die Tabelle nach ihren
 * Tipps (todo=tabelle).
 *
 * Zwei Tipper tippen die ersten Spieltage einer Liga mit Ergebnissen (eine Kopie der Testliga golden/basic.l98) und einen kleinen
 * Pokal mit einem gespielten Spiel.
 */
final class TippAuswertungTest extends TestCase
{
    use AssertsSnapshots;

    private const POKAL = <<<'L98'
[Options]
Title=Liga Manager Online 4
Name=Tipp-Pokal
Type=1
Teams=4
KlFin=0

[Teams]
1=Alpha
2=Beta
3=Gamma
4=Delta

[Round1]
D1=16.03.2025
D2=18.03.2025
MO=2
TA1=1
TB1=2
GA11=3
GB11=1
NT11=
BE11=
TI11=
AT11=1742135400
GA12=0
GB12=0
NT12=
BE12=
TI12=
AT12=1742324400
TA2=3
TB2=4
GA21=2
GB21=1
NT21=
BE21=
TI21=
AT21=1742135400
GA22=-1
GB22=-1
NT22=
BE22=
TI22=
AT22=1742324400

[Round2]
D1=01.06.2025
D2=
MO=1
TA1=0
TB1=0
GA11=-1
GB11=-1
NT11=
BE11=
TI11=
AT11=0

L98;

    private static function client(): AdminClient
    {
        $client = Fixture::loggedInClient();
        $path = $client->instance()->path();
        file_put_contents($path . '/daten/tipp/lmo-tippauth.txt', "<?php exit; ?>\n"
            . "tester|geheim|5|Tina Test|tester@example.org||||1|1|EOL\n"
            . "zweiter|geheim|5|Zoe Zweite|zweite@example.org||||1|1|EOL\n");
        $cfg = $path . '/config/tipp/cfg.txt';
        file_put_contents($cfg, (string) preg_replace('/^immeralle=.*$/m', 'immeralle=1', (string) file_get_contents($cfg), 1, $count));
        self::assertSame(1, $count, 'immeralle steht in der Konfiguration des Tippspiels');

        file_put_contents($client->instance()->ligenDir() . '/tipppokal.l98', self::POKAL);
        // eine Liga mit Ergebnissen direkt im Ligenordner (die mitgelieferte Liga hat keine)
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/tippliga.l98');
        $client->instance()->freezeLeagueTimes();
        $tipps = $path . '/daten/tipp/tipps/';
        // Liga: Spieltag 1 und 2 mit je drei Partien, mit Joker; der zweite Tipper tippt weniger und anders
        file_put_contents($tipps . 'tippliga_tester.tip', "[Round1]\n@2@\nGA1=2\nGB1=1\nGA2=1\nGB2=1\nGA3=0\nGB3=2\n"
            . "[Round2]\n@0@\nGA1=1\nGB1=1\nGA2=2\nGB2=0\nGA3=0\nGB3=1\n");
        file_put_contents($tipps . 'tippliga_zweiter.tip', "[Round1]\n@0@\nGA1=0\nGB1=0\nGA2=3\nGB2=1\n[Round2]\n@1@\nGA1=2\nGB1=2\nGA3=1\nGB3=0\n");
        // Pokal: Hin- und Rueckspiel der ersten Paarung, das gespielte Hinspiel der zweiten
        file_put_contents($tipps . 'tipppokal_tester.tip', "[Round1]\n@0@\nGA11=3\nGB11=1\nGA12=1\nGB12=0\nGA21=2\nGB21=2\n");
        file_put_contents($tipps . 'tipppokal_zweiter.tip', "[Round1]\n@0@\nGA11=1\nGB11=0\nGA21=2\nGB21=1\n");
        foreach (glob($tipps . '*.tip') ?: [] as $tippdatei) {
            touch($tippdatei, mktime(12, 0, 0, 3, 10, 2025));
        }
        $client->resetChanges();

        return $client;
    }

    private static function auswertung(AdminClient $client, string $liga): string
    {
        $datei = $client->instance()->path() . '/daten/tipp/tipps/auswert/' . $liga . '.aus';

        return is_file($datei) ? str_replace("\r\n", "\n", (string) file_get_contents($datei)) : '(nicht geschrieben)';
    }

    public function testAdminWertetAusUndTipperSehenDieRangliste(): void
    {
        $c = self::client();
        $t = new Transcript();

        $t->add('Auswertung Liga, nur Spieltag 1', $c->post('lmoadmin.php', '', [
            'action' => 'admin', 'todo' => 'tipp', 'save' => '2', 'liga' => 'tippliga', 'st' => '1',
        ]), $c->changes());
        $t->add('Auswertungsdatei nach Spieltag 1', self::auswertung($c, 'tippliga'), '');
        $t->add('Auswertung Liga, alle Spieltage', $c->post('lmoadmin.php', '', [
            'action' => 'admin', 'todo' => 'tipp', 'save' => '2', 'liga' => 'tippliga', 'st' => '0',
        ]), $c->changes());
        $t->add('Auswertungsdatei Liga', self::auswertung($c, 'tippliga'), '');
        $t->add('Auswertung Pokal, Runde 1', $c->post('lmoadmin.php', '', [
            'action' => 'admin', 'todo' => 'tipp', 'save' => '2', 'liga' => 'tipppokal', 'st' => '1',
        ]), $c->changes());
        $t->add('Auswertungsdatei Pokal', self::auswertung($c, 'tipppokal'), '');

        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->changes();
        $t->add('Rangliste Liga', $c->get('lmo.php', 'action=tipp&todo=wert&file=tippliga.l98'), $c->changes());
        $t->add('Rangliste Liga, Spieltag 2', $c->get('lmo.php', 'action=tipp&todo=wert&file=tippliga.l98&endtab=2'), $c->changes());
        $t->add('Rangliste Pokal', $c->get('lmo.php', 'action=tipp&todo=wert&file=tipppokal.l98'), $c->changes());
        $t->add('Tipp-Fieberkurve Liga', $c->get('lmo.php', 'action=tipp&todo=fieber&file=tippliga.l98'), $c->changes());
        $t->add('Tabelle nach den eigenen Tipps', $c->get('lmo.php', 'action=tipp&todo=tabelle&file=tippliga.l98'), $c->changes());

        // Speichern im Adminbereich wertet die Liga gleich mit aus (aktauswert=1), und zwar mit dem
        // gerade eingegebenen Stand: das erste Spiel endet jetzt so, wie "tester" es getippt hat
        $t->add('Admin aendert ein Ergebnis von Spieltag 1', $c->submitForm('lmoadmin.php', 'action=admin&todo=edit&file=tippliga.l98&st=1', 'xgoala0', [
            'xgoala0' => '2', 'xgoalb0' => '1',
        ]), $c->changes());
        $t->add('Auswertungsdatei nach dem Speichern', self::auswertung($c, 'tippliga'), '');

        $this->assertMatchesSnapshot('tipp', 'auswertung', $t->text());
    }

    /**
     * Die Tabelle nach den Tipps (lmo-tipptabelle.php mit lmo-tippcalctable.php) in ihren Ansichten:
     * gesamt, heim, auswaerts, Hin- und Rueckrunde, bis zu einem Spieltag, fuer einen anderen und
     * fuer alle Tipper; dazu eine Liga mit Minuspunkten, Strafen und direktem Vergleich
     * (lmo-tippcalctable1.php) und die Wertung nach Vereinen (lmo-tippcalcwertverein.php).
     */
    public function testTabelleNachTippsInAllenAnsichten(): void
    {
        $c = self::client();
        $path = $c->instance()->path();
        copy($c->instance()->ligenDir() . '/golden/penalties.l98', $c->instance()->ligenDir() . '/tippstrafe.l98');
        $c->instance()->freezeLeagueTimes();
        // viele gleiche Tipps: Mannschaften liegen gleichauf, der direkte Vergleich entscheidet
        $tipp = $path . '/daten/tipp/tipps/tippstrafe_tester.tip';
        file_put_contents($tipp, "[Round1]\n@0@\nGA1=1\nGB1=1\nGA2=1\nGB2=1\nGA3=2\nGB3=0\nGA4=0\nGB4=2\n"
            . "[Round2]\n@0@\nGA1=1\nGB1=0\nGA2=0\nGB2=1\nGA3=1\nGB3=1\nGA4=2\nGB4=2\n"
            . "[Round3]\n@0@\nGA1=0\nGB1=0\nGA2=3\nGB2=1\nGA3=1\nGB3=3\n");
        touch($tipp, mktime(12, 0, 0, 3, 10, 2025));
        foreach (['tippliga', 'tippstrafe'] as $liga) {
            $c->post('lmoadmin.php', '', ['action' => 'admin', 'todo' => 'tipp', 'save' => '2', 'liga' => $liga, 'st' => '0']);
        }
        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->resetChanges();
        $t = new Transcript();

        $tabelle = 'action=tipp&todo=tabelle&file=';
        foreach ([1 => 'heim', 2 => 'auswaerts', 4 => 'Hinrunde', 3 => 'Rueckrunde'] as $art => $name) {
            $t->add('Liga, ' . $name, $c->get('lmo.php', $tabelle . 'tippliga.l98&tabtype=' . $art), $c->changes());
        }
        $t->add('Liga, bis Spieltag 2', $c->get('lmo.php', $tabelle . 'tippliga.l98&st=2'), $c->changes());
        $t->add('Liga, anderer Tipper', $c->get('lmo.php', $tabelle . 'tippliga.l98&nick=zweiter'), $c->changes());
        $t->add('Liga, alle Tipper', $c->get('lmo.php', $tabelle . 'tippliga.l98&nick='), $c->changes());
        $t->add('Strafen und direkter Vergleich', $c->get('lmo.php', $tabelle . 'tippstrafe.l98'), $c->changes());
        $t->add('Strafen und direkter Vergleich, bis Spieltag 3', $c->get('lmo.php', $tabelle . 'tippstrafe.l98&st=3'), $c->changes());
        $t->add('Strafen und direkter Vergleich, heim', $c->get('lmo.php', $tabelle . 'tippstrafe.l98&tabtype=1'), $c->changes());

        $this->assertMatchesSnapshot('tipp', 'tabelle', $t->text());
    }

    /**
     * Die Userverwaltung der Administration (todo=tippuser): Liste, sortiert, einen Tipper
     * loeschen. "ter" ist nicht freigeschaltet und hat keine E-Mail-Adresse.
     */
    public function testAdminUserverwaltung(): void
    {
        $c = self::client();
        $pfad = $c->instance()->path() . '/daten/tipp/';
        file_put_contents($pfad . 'lmo-tippauth.txt', "ter|geheim|1|Ter Kurz||||||1|1|EOL\n", FILE_APPEND);
        file_put_contents($pfad . 'tipps/tippliga_ter.tip', "[Round1]\n@0@\nGA1=1\nGB1=0\n");
        touch($pfad . 'tipps/tippliga_ter.tip', mktime(18, 30, 0, 3, 12, 2025));
        $c->resetChanges();
        $t = new Transcript();
        $a = 'action=admin&todo=tippuser';

        $t->add('Liste', $c->get('lmoadmin.php', $a), $c->changes());
        $t->add('nach Nick absteigend', $c->get('lmoadmin.php', $a . '&tipper_sort=nick&tipper_sort_direction=desc'), $c->changes());
        $t->add('ter loeschen', $c->get('lmoadmin.php', $a . '&tipper_sort=nick&del=ter'), $c->changes());

        $this->assertMatchesSnapshot('tipp', 'userverwaltung', $t->text());
    }

    /**
     * Einen Tipper in der Administration bearbeiten und anlegen (todo=tippuseredit): Formular,
     * Speichern mit Verein und Ligen, ein neuer Tipper, Fehler. Zufallskennwort und Kennwort-Hash
     * sind ersetzt.
     */
    public function testAdminTipperBearbeiten(): void
    {
        $c = self::client();
        $t = new Transcript();
        $a = ['action' => 'admin', 'todo' => 'tippuseredit'];
        $ohneZufall = static fn (string $text): string => (string) preg_replace(
            ['/\$(argon2id|argon2i|2y)\$[^|\s]+/', '/(name="xtipperpass" value=")[a-z0-9]{8,16}(")/'],
            ['{HASH}', '$1{ZUFALL}$2'],
            $text
        );
        $add = static fn (string $titel, string $html) => $t->add($titel, $ohneZufall($html), $ohneZufall($c->changes()));

        $add('tester bearbeiten', $c->get('lmoadmin.php', 'action=admin&todo=tippuseredit&nick=tester'));
        $add('tester speichern: neuer Verein, nur noch die Liga', $c->post('lmoadmin.php', '', $a + [
            'nick' => 'tester', 'newpage' => '0', 'xtipperpass' => '', 'xtippervorname' => 'Tina', 'xtippernachname' => 'Neu',
            'xtipperemail' => 'tina@example.org', 'xfrei' => 'on', 'xremind' => 'on', 'xtippervereinradio' => '2', 'xtippervereinneu' => 'Club',
            'xtipperligen' => ['tippliga'], 'xtippersub' => 'x',
        ]));
        $add('neuer Tipper: Formular', $c->get('lmoadmin.php', 'action=admin&todo=tippuseredit&save=-1'));
        $add('neuer Tipper: Verein schon voll', $c->post('lmoadmin.php', '', $a + [
            'nick' => 'neu', 'newpage' => '1', 'xtipperpass' => 'abc123', 'xtippervorname' => 'Nora', 'xtippernachname' => 'Neu',
            'xtipperemail' => 'nora@example.org', 'xnews' => 'on', 'xtippervereinradio' => '1', 'xtippervereinalt' => 'Club',
            'xtipperligen' => ['tipppokal'], 'xtippersub' => 'x',
        ]));
        $add('neuer Tipper: anlegen', $c->post('lmoadmin.php', '', $a + [
            'nick' => 'neu', 'newpage' => '1', 'xtipperpass' => 'abc123', 'xtippervorname' => 'Nora', 'xtippernachname' => 'Neu',
            'xtipperemail' => 'nora@example.org', 'xnews' => 'on', 'xtippervereinradio' => '0',
            'xtipperligen' => ['tipppokal'], 'xtippersub' => 'x',
        ]));
        $add('neuer Tipper: Nick schon vergeben', $c->post('lmoadmin.php', '', $a + [
            'nick' => 'zweiter', 'newpage' => '1', 'xtipperpass' => 'abc', 'xtippervorname' => 'Zoe', 'xtippernachname' => 'Zwei',
            'xtipperemail' => '', 'xtippervereinradio' => '0', 'xtippersub' => 'x',
        ]));
        $add('neuer Tipper: Name mit Leerzeichen', $c->post('lmoadmin.php', '', $a + [
            'nick' => 'dritter', 'newpage' => '1', 'xtipperpass' => 'abc', 'xtippervorname' => 'Anna Lena', 'xtippernachname' => 'Drei',
            'xtipperemail' => '', 'xtippervereinradio' => '2', 'xtippervereinneu' => '', 'xtippersub' => 'x',
        ]));

        $this->assertMatchesSnapshot('tipp', 'tipper_bearbeiten', $t->text());
    }
}
