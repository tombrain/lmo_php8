<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Die beiden Admin-Funktionen, die ueber die classlib speichern (liga::writeFile()):
 * "Rueckrundenspielplan erstellen" (lmo-rueckrunde.php) und Partien in einen anderen Spieltag
 * verschieben (lmo-adminrounds.php). Geprueft wird die gespeicherte Ligadatei, nicht das HTML.
 */
final class AdminScheduleToolsTest extends TestCase
{
    /** 4 Teams, 3 Spieltage: ungerade Anzahl, daraus laesst sich keine Hin- und Rueckrunde bilden */
    private const UNGERADE = <<<'L98'
[Options]
Title=Liga Manager Online 4
Name=Ungerade
Type=0
Teams=4
Rounds=3
Matches=2
Actual=1
Kegel=0
HandS=0
PointsForWin=3
PointsForDraw=1
PointsForLost=0
Spez=0
HideDraw=0
OnRun=0
MinusPoints=1
Direct=0
Champ=1
CL=0
CK=0
UC=0
AR=0
AB=1
namePkt=Pkt.
nameTor=Tore
DatC=1
DatS=1
DatM=1
DatF=d.m.Y H:i
urlT=1
urlB=1
Plan=1
Ergebnis=1
mittore=1
favTeam=0
selTeam=0
ticker=0
Graph=1
Kreuz=1
Tabelle=1
Ligastats=1
kurve1=0
kurve2=0

[Teams]
1=Alpha
2=Beta
3=Gamma
4=Delta

[Teamk]
1=ALP
2=BET
3=GAM
4=DEL

[Team1]
SP=0

[Team2]
SP=0

[Team3]
SP=0

[Team4]
SP=0

[Round1]
D1=
D2=
TA1=1
TB1=2
GA1=1
GB1=0
TA2=3
TB2=4
GA2=-1
GB2=-1

[Round2]
D1=
D2=
TA1=1
TB1=3
GA1=-1
GB1=-1
TA2=2
TB2=4
GA2=-1
GB2=-1

[Round3]
D1=
D2=
TA1=4
TB1=1
GA1=-1
GB1=-1
TA2=3
TB2=2
GA2=-1
GB2=-1

L98;

    public static function setUpBeforeClass(): void
    {
        foreach ([
            'PATH_TO_ADDONDIR' => Fixture::sourceRoot() . '/lmo/addon',
            'CLASSLIB_VERSION_NR' => '2.8',
            'CLASSLIB_VERSION' => '(classlib&nbsp;2.8)',
        ] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        require_once PATH_TO_ADDONDIR . '/classlib/classes.php';
    }

    private static function load(AdminClient $client, string $file): \liga
    {
        $liga = new \liga();
        self::assertTrue($liga->loadFile($client->instance()->ligenDir() . '/' . $file), "$file nicht lesbar");
        return $liga;
    }

    /** @return array<int,string[]> Spieltag => Partien als "Heim-Gast Heimtore:Gasttore" */
    private static function schedule(\liga $liga): array
    {
        $rounds = [];
        foreach ($liga->spieltage as $spieltag) {
            $rounds[(int)$spieltag->nr] = array_map(
                static fn (\partie $p): string => $p->heim->nr . '-' . $p->gast->nr . ' ' . (int)$p->hTore . ':' . (int)$p->gTore,
                $spieltag->partien
            );
        }
        return $rounds;
    }

    private static function secondHalfRequest(string $file): array
    {
        return ['action' => 'admin', 'todo' => 'edit', 'save' => '990', 'file' => $file, 'st' => '1', 'rueckrundeButton' => 'x'];
    }

    public function testAdminCreatesSecondHalfOfSeason(): void
    {
        $client = Fixture::loggedInClient();
        $before = self::load($client, 'golden/basic.l98');
        $half = $before->spieltageCount() / 2;

        $html = $client->post('lmoadmin.php', '', self::secondHalfRequest('golden/basic.l98'));

        self::assertStringContainsString('Der Rückrundenspielplan wurde erfolgreich gespeichert.', $html);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen beim Speichern');

        $after = self::load($client, 'golden/basic.l98');
        $old = self::schedule($before);
        $new = self::schedule($after);
        self::assertCount(count($old), $new);
        for ($round = 1; $round <= $half; $round++) {
            self::assertSame($old[$round], $new[$round], "Hinrunde, Spieltag $round bleibt unveraendert");
            // Rueckrunde: dieselben Paarungen mit getauschtem Heimrecht, ohne Ergebnis
            $expected = array_map(static function (string $match): string {
                [$home, $away] = explode('-', explode(' ', $match)[0]);
                return "$away-$home -1:-1";
            }, $old[$round]);
            self::assertSame($expected, $new[$round + $half], 'Rueckrunde, Spieltag ' . ($round + $half));
        }
        // Einstellungen aus der Oberflaeche bleiben erhalten
        foreach (['Title', 'Name', 'Actual', 'Teams', 'Rounds', 'PointsForWin', 'goalfaktor', 'tableHinRueck'] as $key) {
            self::assertEquals($before->options->keyValues[$key], $after->options->keyValues[$key], "Option $key");
        }
        self::assertSame($before->teamNames(), $after->teamNames());
    }

    public function testSecondHalfNeedsAnEvenNumberOfRounds(): void
    {
        $client = Fixture::loggedInClient();
        $path = $client->instance()->ligenDir() . '/ungerade.l98';
        file_put_contents($path, self::UNGERADE);
        $before = self::schedule(self::load($client, 'ungerade.l98'));

        $html = $client->post('lmoadmin.php', '', self::secondHalfRequest('ungerade.l98'));

        self::assertStringContainsString('Die Liga hat eine ungerade Anzahl an Spieltagen.', $html);
        self::assertStringNotContainsString('erfolgreich gespeichert', $html);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen');
        self::assertSame($before, self::schedule(self::load($client, 'ungerade.l98')), 'Spielplan bleibt unveraendert');
    }

    public function testSecondHalfIsNotCreatedWithoutLogin(): void
    {
        $client = Fixture::anonymousClient();
        $path = $client->instance()->ligenDir() . '/golden/basic.l98';
        $before = file_get_contents($path);

        $html = $client->post('lmoadmin.php', '', self::secondHalfRequest('golden/basic.l98'));

        self::assertStringNotContainsString('erfolgreich gespeichert', $html);
        self::assertSame($before, file_get_contents($path), 'Ligadatei bleibt unveraendert');
    }

    public function testAdminMovesMatchToAnotherRound(): void
    {
        $client = Fixture::loggedInClient();
        $before = self::load($client, 'golden/handicap.l98');
        $match = $before->SpieltagForNumber(1)->partieForNumber(1);
        [$home, $away] = [(int)$match->heim->nr, (int)$match->gast->nr];
        $old = self::schedule($before);
        $handicap = array_map(static fn (\spieltag $s) => $s->getParameter('HS'), $before->spieltage);
        self::assertNotNull($handicap[0], 'Testliga hat Handicap-Angaben');

        $html = $client->post('lmoadmin.php', '', [
            'action' => 'admin', 'todo' => 'edit', 'save' => '1', 'file' => 'golden/handicap.l98', 'st' => '-10',
            "sp_1_{$home}_{$away}" => '2',
        ]);

        self::assertStringContainsString('verschoben', $html);
        self::assertStringNotContainsString('STDERR', $html, 'PHP-Meldungen beim Speichern');

        $after = self::load($client, 'golden/handicap.l98');
        $new = self::schedule($after);
        $moved = $old[1][0];
        self::assertSame(array_slice($old[1], 1), $new[1], 'Spieltag 1 ohne die verschobene Partie');
        self::assertSame(array_merge($old[2], [$moved]), $new[2], 'Spieltag 2 mit der verschobenen Partie');
        self::assertSame(array_slice($old, 2, null, true), array_slice($new, 2, null, true), 'uebrige Spieltage unveraendert');
        // die Handicap-Reihenfolge je Spieltag darf beim Speichern ueber die classlib nicht verloren gehen
        self::assertSame($handicap, array_map(static fn (\spieltag $s) => $s->getParameter('HS'), $after->spieltage));
        self::assertEquals($before->options->keyValues['Actual'], $after->options->keyValues['Actual']);
    }
}
