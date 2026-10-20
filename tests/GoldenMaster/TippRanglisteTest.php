<?php

declare(strict_types=1);

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Die Rangliste des Tippspiels (todo=wert, lmo-tippwert.php mit lmo-tippcalcwert.php und
 * lmo-tippcalcwertteam.php) in ihren Ansichten: Einzel-, Team- und interne Wertung, bis oder nur
 * zu einem Spieltag, nach Punkten, Quote oder Spielen, in Seiten, mit Zusatzpunkten und
 * Spieltagssiegen, die Gesamtwertung, im Tendenz-Modus, ohne Auswertung und fuer Gaeste.
 *
 * Die Auswertungen (tipps/auswert/*.aus) schreibt der Test selbst: vier Tipper in zwei Teams,
 * zwei davon punktgleich.
 */
final class TippRanglisteTest extends TestCase
{
    use AssertsSnapshots;

    private const AUSWERTUNG = "[anna]\nTeam=Rot\nName=Anna Alt\nEmail=anna@example.org\nSG1=3\nP11=1\nP31=1\nP61=2\nTP1=5\nSG2=3\nP32=1\nTP2=2\n\n"
        . "[bert]\nTeam=Blau\nName=Bert Bunt\nEmail=\nSG1=3\nP11=1\nP31=1\nTP1=5\nSG2=2\nP12=1\nTP2=4\n\n"
        . "[tester]\nTeam=Rot\nName=Tina Test\nEmail=tester@example.org\nSG1=2\nP21=1\nTP1=1\nSG2=3\nP12=1\nP22=1\nTP2=6\n\n"
        . "[dora]\nTeam=\nName=Dora Dreh\nEmail=dora@example.org\nSG1=1\nTP1=0\n";

    private const GESAMT = "[Options]\nAnzLigen=2\n\n[anna]\nTeam=Rot\nSG1=6\nTP1=7\nSG2=2\nTP2=1\n\n[tester]\nTeam=Rot\nSG1=5\nTP1=7\n\n[bert]\nTeam=Blau\nSG1=5\nTP1=9\n";

    private static function client(): AdminClient
    {
        $client = Fixture::anonymousClient();
        $path = $client->instance()->path();
        file_put_contents($path . '/addon/tipp/lmo-tippauth.txt', "<?php exit; ?>\ntester|geheim|5|Tina Test|tester@example.org|Rot||||1|1|EOL\n");
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/tippliga.l98');
        copy($client->instance()->ligenDir() . '/golden/basic.l98', $client->instance()->ligenDir() . '/ohneauswertung.l98');
        $client->instance()->freezeLeagueTimes();
        self::einstellungen($client, ['tipperimteam' => '0', 'anzseite1' => '30', 'showname' => '1', 'showemail' => '1']);
        @mkdir($path . '/addon/tipp/tipps/auswert', 0777, true);
        file_put_contents($path . '/addon/tipp/tipps/auswert/tippliga.aus', self::AUSWERTUNG);
        file_put_contents($path . '/addon/tipp/tipps/auswert/gesamt.aus', self::GESAMT);
        $client->resetChanges();

        return $client;
    }

    /** @param array<string, string> $werte */
    private static function einstellungen(AdminClient $client, array $werte): void
    {
        $cfg = $client->instance()->path() . '/config/tipp/cfg.txt';
        $inhalt = (string) file_get_contents($cfg);
        foreach ($werte as $name => $wert) {
            $inhalt = (string) preg_replace('/^' . $name . '=.*$/m', $name . '=' . $wert, $inhalt, 1, $count);
            self::assertSame(1, $count, $name . ' steht in der Konfiguration des Tippspiels');
        }
        file_put_contents($cfg, $inhalt);
    }

    public function testRanglisteInAllenAnsichten(): void
    {
        $c = self::client();
        $t = new Transcript();
        $wert = 'action=tipp&todo=wert&file=tippliga.l98';

        $t->add('Gast, alle Spieltage', $c->get('lmo.php', $wert), $c->changes());
        $c->post('lmo.php', '', ['action' => 'tipp', 'xtippername' => 'tester', 'xtipperpass' => 'geheim']);
        $c->changes();
        $t->add('Einzelwertung, alle Spieltage', $c->get('lmo.php', $wert), $c->changes());
        $t->add('Bis Spieltag 2', $c->get('lmo.php', $wert . '&endtab=2'), $c->changes());
        $t->add('Nur Spieltag 2', $c->get('lmo.php', $wert . '&endtab=2&stwertmodus=nur'), $c->changes());
        $t->add('Nach Quote', $c->get('lmo.php', $wert . '&gewicht=relativ'), $c->changes());
        $t->add('Nach Spielen, bis Spieltag 2', $c->get('lmo.php', $wert . '&gewicht=spiele&endtab=2'), $c->changes());
        $t->add('Teamwertung', $c->get('lmo.php', $wert . '&wertung=team'), $c->changes());
        $t->add('Teamwertung, bis Spieltag 2', $c->get('lmo.php', $wert . '&wertung=team&endtab=2'), $c->changes());
        $t->add('Interne Wertung', $c->get('lmo.php', $wert . '&wertung=intern&teamintern=Rot'), $c->changes());
        $t->add('Gesamtwertung mit Liga', $c->get('lmo.php', $wert . '&all=1'), $c->changes());
        $t->add('Gesamtwertung ohne Liga', $c->get('lmo.php', 'action=tipp&todo=wert&file=&all=1'), $c->changes());
        $t->add('Ohne Auswertung', $c->get('lmo.php', 'action=tipp&todo=wert&file=ohneauswertung.l98&endtab=2'), $c->changes());

        self::einstellungen($c, ['anzseite1' => '2', 'showzus' => '0', 'showname' => '', 'showemail' => '']);
        $t->add('Erste Seite', $c->get('lmo.php', $wert), $c->changes());
        $t->add('Zweite Seite', $c->get('lmo.php', $wert . '&start=3'), $c->changes());

        self::einstellungen($c, ['tippmodus' => '0', 'showstsiege' => '0', 'krit1' => '6', 'shownick' => '', 'showemail' => '1']);
        $t->add('Tendenz-Modus', $c->get('lmo.php', $wert . '&endtab=2'), $c->changes());

        $this->assertMatchesSnapshot('tipp', 'rangliste', $t->text());
    }
}
