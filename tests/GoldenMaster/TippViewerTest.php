<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Tippspiel aus Sicht eines angemeldeten Tippers: Anmeldung, die Tippuebersicht ueber alle Ligen
 * ("Spiele der naechsten Tage", lmo-tippviewer.php mit lmo-tippopenfileviewer.php) und das
 * Speichern der Tipps von dort.
 *
 * Getippt werden die Bundesliga (Spieltag 26 liegt im Zeitfenster der Testuhr, 15.03.2025) und ein
 * kleiner Pokal mit Hin- und Rueckspiel, dessen Mannschaftsname und Notiz ein "|" enthalten.
 */
final class TippViewerTest extends TestCase
{
    use AssertsSnapshots;

    private const POKAL = <<<'L98'
[Options]
Title=Liga Manager Online 4
Name=Tipp-Pokal
Type=1
Teams=4
KlFin=0
favTeam=2

[Teams]
1=Alpha
2=B | C
3=Gamma
4=O\'Neill

[Round1]
D1=16.03.2025
D2=18.03.2025
MO=2
TA1=1
TB1=2
GA11=-1
GB11=-1
NT11=Hinspiel | Flutlicht
BE11=
TI11=
AT11=1742135400
GA12=-1
GB12=-1
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
        $client = Fixture::anonymousClient();
        $path = $client->instance()->path();
        // ein freigeschalteter Tipper; er darf alle Ligen tippen, fuer die er eine Tippdatei hat
        file_put_contents($path . '/addon/tipp/lmo-tippauth.txt', "<?php exit; ?>\ntester|geheim|5|Tina Test|tester@example.org||||1|1|EOL\n");
        $cfg = $path . '/config/tipp/cfg.txt';
        file_put_contents($cfg, (string) preg_replace('/^immeralle=.*$/m', 'immeralle=1', (string) file_get_contents($cfg), 1, $count));
        self::assertSame(1, $count, 'immeralle steht in der Konfiguration des Tippspiels');

        file_put_contents($client->instance()->ligenDir() . '/tipppokal.l98', self::POKAL);
        $client->instance()->freezeLeagueTimes();
        // Bundesliga: zwei Spiele getippt, Joker auf Spiel 3; Pokal: das Hinspiel der ersten Paarung
        file_put_contents($path . '/addon/tipp/tipps/1l_2024-25_tester.tip', "[Round26]\n@3@\nGA1=2\nGB1=1\nGA3=0\nGB3=0\n");
        file_put_contents($path . '/addon/tipp/tipps/tipppokal_tester.tip', "[Round1]\nGA11=3\nGB11=1\n");
        // "Dein letzter Tipp" zeigt die Dateizeit
        foreach (['1l_2024-25_tester.tip', 'tipppokal_tester.tip'] as $tippdatei) {
            touch($path . '/addon/tipp/tipps/' . $tippdatei, mktime(12, 0, 0, 3, 10, 2025));
        }
        $client->resetChanges();

        return $client;
    }

    public function testLoggedInTipperSeesAndSavesUpcomingGames(): void
    {
        $c = self::client();
        $t = new Transcript();

        $t->add('Anmeldung als Tipper', $c->post('lmo.php', '', [
            'action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim',
        ]), self::ohneKennwort($c->changes()));
        $t->add('Tippuebersicht: Spiele der naechsten Tage aus beiden Ligen', $c->get('lmo.php', 'action=tipp&todo=edit&file=&viewermode=1'), $c->changes());
        $t->add('Tipps aus der Uebersicht speichern', $c->submitFromLast('xtippa0', [
            'xtippa0' => '4', 'xtippb0' => '0',
        ]), $c->changes());
        $t->add('Tippuebersicht ab uebermorgen', $c->get('lmo.php', 'action=tipp&todo=edit&file=&viewermode=1&start=2'), $c->changes());

        $path = $c->instance()->path() . '/addon/tipp/tipps/';
        $t->add('Tippdatei Bundesliga', (string) file_get_contents($path . '1l_2024-25_tester.tip'), '');
        $t->add('Tippdatei Pokal', (string) file_get_contents($path . 'tipppokal_tester.tip'), '');

        $this->assertMatchesSnapshot('tipp', 'viewer_logged_in', self::ohneUhrzeit($t->text()));
    }

    /**
     * Die Tippuebersicht im Detail: Tendenzen aus der Einsicht anderer Tipper, Joker und Tipps
     * speichern samt Einsichtsdateien, der Tendenz-Tippmodus ohne Pfeile, Joker und Statistik, und
     * ein Zeitraum ohne Spiele.
     */
    public function testUebersichtImDetail(): void
    {
        $c = self::client();
        $path = $c->instance()->path();
        $tipps = $path . '/addon/tipp/tipps/';
        @mkdir($tipps . 'einsicht', 0777, true);
        file_put_contents($tipps . 'einsicht/1l_2024-25_26.ein', "[anna]\nGA1=1\nGB1=1\nGA2=3\nGB2=0\n\n[bert]\n@2@\nGA1=2\nGB1=0\nGA2=1\nGB2=2\n");
        file_put_contents($tipps . 'einsicht/tipppokal_1.ein', "[tester]\nGA11=1\nGB11=1\n\n[anna]\nGA11=0\nGB11=2\n");
        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->resetChanges();
        $t = new Transcript();
        $uebersicht = 'action=tipp&todo=edit&file=&viewermode=1';

        $t->add('Mit Tendenzen anderer Tipper', $c->get('lmo.php', $uebersicht), $c->changes());
        $t->add('Joker, Tipps und ein geloeschter Tipp speichern', $c->submitFromLast('xtippa0', [
            'xtippa0' => '', 'xtippb0' => '', 'xtippa1' => '2', 'xtippb1' => '2', 'xjokerspiel_1l_2024-25_26' => '2', 'xtippa10' => '1', 'xtippb10' => '0',
        ]), $c->changes());
        foreach (['1l_2024-25_26', 'tipppokal_1'] as $einsicht) {
            $datei = $tipps . 'einsicht/' . $einsicht . '.ein';
            $t->add('Einsicht ' . $einsicht, is_file($datei) ? str_replace("\r\n", "\n", (string) file_get_contents($datei)) : '(keine)', '');
        }

        $cfg = $path . '/config/tipp/cfg.txt';
        $einstellungen = (string) file_get_contents($cfg);
        foreach (['tippmodus' => '0', 'pfeiltipp' => '0', 'jokertipp' => '0', 'showtendenzabs' => '0', 'showtendenzpro' => '0', 'showdurchschntipp' => '0', 'tippeinsicht' => '0'] as $name => $wert) {
            $einstellungen = (string) preg_replace('/^' . $name . '=.*$/m', $name . '=' . $wert, $einstellungen, 1);
        }
        file_put_contents($cfg, $einstellungen);
        $c->resetChanges();
        $t->add('Tendenz-Modus', $c->get('lmo.php', $uebersicht), $c->changes());
        $t->add('Tendenzen speichern', $c->submitFromLast('xtipp0', ['xtipp0' => '2', 'xtipp1' => '3', 'xtipp9' => '1']), $c->changes());
        $t->add('Ohne Spiele im Zeitraum', $c->get('lmo.php', $uebersicht . '&start=100'), $c->changes());

        $this->assertMatchesSnapshot('tipp', 'viewer_detail', self::ohneUhrzeit($t->text()));
    }

    public function testTippingASingleRoundOfLeagueAndCup(): void
    {
        $c = self::client();
        $t = new Transcript();

        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->changes();
        $t->add('Tippabgabe Bundesliga, Spieltag 26', $c->get('lmo.php', 'action=tipp&todo=edit&file=1l_2024-25.l98&st=26'), $c->changes());
        $t->add('Tippabgabe Pokal, Runde 1', $c->get('lmo.php', 'action=tipp&todo=edit&file=tipppokal.l98&st=1'), $c->changes());
        $t->add('Tipptabelle Bundesliga', $c->get('lmo.php', 'action=tipp&todo=tabelle&file=1l_2024-25.l98'), $c->changes());

        $this->assertMatchesSnapshot('tipp', 'single_round_logged_in', $t->text());
    }

    /** Tipps fuer einen einzelnen Spieltag abgeben (lmo-tippedit.php), in Liga und Pokal, und die Einsicht danach. */
    public function testSavingTipsOfASingleRound(): void
    {
        $c = self::client();
        $t = new Transcript();

        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->changes();
        $c->get('lmo.php', 'action=tipp&todo=edit&file=1l_2024-25.l98&st=26');
        $t->add('Bundesliga, Spieltag 26: zwei Tipps und Joker speichern', $c->submitFromLast('xtippa0', [
            'xtippa0' => '2', 'xtippb0' => '2', 'xtippa4' => '1', 'xtippb4' => '0', 'xjokerspiel' => '5',
        ]), $c->changes());
        $c->get('lmo.php', 'action=tipp&todo=edit&file=tipppokal.l98&st=1');
        $t->add('Pokal, Runde 1: Hin- und Rueckspiel der zweiten Paarung tippen', $c->submitFromLast('xtippa00', [
            'xtippa10' => '0', 'xtippb10' => '1', 'xtippa11' => '2', 'xtippb11' => '2',
        ]), $c->changes());
        $t->add('Einsicht Bundesliga, Spieltag 26', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=1l_2024-25.l98&st=26'), $c->changes());
        $t->add('Einsicht Pokal, Runde 1', $c->get('lmo.php', 'action=tipp&todo=einsicht&file=tipppokal.l98&st=1'), $c->changes());

        $path = $c->instance()->path() . '/addon/tipp/tipps/';
        $t->add('Tippdatei Bundesliga', (string) file_get_contents($path . '1l_2024-25_tester.tip'), '');
        $t->add('Tippdatei Pokal', (string) file_get_contents($path . 'tipppokal_tester.tip'), '');

        $this->assertMatchesSnapshot('tipp', 'save_single_round', $t->text());
    }

    /** Nach dem Speichern traegt die Tippuebersicht die Sekunde des Aufrufs im Formular (xnow); die Testuhr laeuft weiter. */
    private static function ohneUhrzeit(string $text): string
    {
        return (string) preg_replace(['/(name="xnow" value=")\d+/', '/^(xnow=)\d+$/m'], '$1{JETZT}', $text);
    }

    /** Beim ersten Anmelden wird das Kennwort gehasht (zufaelliges Salz): die Zeile des Tippers aendert sich jedes Mal anders. */
    private static function ohneKennwort(string $changes): string
    {
        return (string) preg_replace('/\$(argon2id?|2y)\$[^|\s]+/', '{HASH}', $changes);
    }
}
