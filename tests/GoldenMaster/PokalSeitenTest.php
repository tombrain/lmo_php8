<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Instance;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use Lmo\Tests\GoldenMaster\Support\Runner;
use PHPUnit\Framework\TestCase;

/**
 * HTML-Ausgabe der oeffentlichen Pokalseiten (Ergebnisse, Spielplan, Kalender) fuer erzeugte
 * Pokale, im Vergleich zum aufgezeichneten Originalstand. Die Pokale liegen in einer eigenen
 * Instanz unter ligen/pokal/, damit Ligenlisten anderer Tests unveraendert bleiben.
 *
 *  - standard:   8 Mannschaften, Hin- und Rueckspiel im Viertelfinale, Spiel um Platz 3,
 *                Notizen, Spielberichte, Elfmeterschiessen, Vereinslinks, Lieblingsmannschaft
 *  - playoff221: Playoff-Modus 2-2-1 (bis zu fuenf Spiele je Paarung)
 *  - playoff232: Playoff-Modus 2-3-2 (bis zu sieben Spiele je Paarung)
 */
final class PokalSeitenTest extends TestCase
{
    use AssertsSnapshots;

    /** 3. Maerz 2025, 15:00 Uhr (UTC); weitere Anstosszeiten jeweils Tage danach */
    private const ANSTOSS = 1741014000;
    private const TAG = 86400;

    /** @var array{0:Instance,1:Runner}|null */
    private static ?array $instanz = null;

    /**
     * @dataProvider seiten
     */
    public function testPokalseiteIstUnveraendert(string $query): void
    {
        [$app, $runner] = self::instanz();
        $html = $runner->request($query);

        $this->assertMatchesSnapshot('pokal-seiten', $query, Normalizer::html($html, $app->path(), $query));
    }

    /** @return array<string, array{0:string}> */
    public static function seiten(): array
    {
        $faelle = [];
        foreach (['standard' => 3, 'playoff221' => 2, 'playoff232' => 2] as $name => $runden) {
            $f = 'file=pokal/' . $name . '.l98';
            $abfragen = [$f, $f . '&action=program', $f . '&action=program&selteam=1', $f . '&action=program&selteam=3', $f . '&action=cal', $f . '&action=cal&cal=032025'];
            for ($st = 1; $st <= $runden; $st++) {
                $abfragen[] = $f . '&action=results&st=' . $st;
            }
            foreach ($abfragen as $abfrage) {
                $faelle[$abfrage] = [$abfrage];
            }
        }

        return $faelle;
    }

    /** @return array{0:Instance,1:Runner} */
    private static function instanz(): array
    {
        if (self::$instanz === null) {
            [$app, $runner] = Fixture::freshInstance();
            $ordner = $app->ligenDir() . '/pokal';
            mkdir($ordner, 0777, true);
            foreach (self::pokale() as $name => $inhalt) {
                file_put_contents($ordner . '/' . $name . '.l98', $inhalt);
            }
            $app->freezeLeagueTimes();
            self::$instanz = [$app, $runner];
        }

        return self::$instanz;
    }

    /** @return array<string, string> Name => Inhalt der Ligadatei */
    private static function pokale(): array
    {
        $a = static fn (int $tage, int $stunden = 0): int => self::ANSTOSS + $tage * self::TAG + $stunden * 3600;

        $standard = [
            '[Options]', 'Name=Standardpokal', 'Type=1', 'Teams=8', 'Rounds=3', 'Actual=2', 'KlFin=1',
            'DatC=1', 'DatM=1', 'DatS=1', 'urlB=1', 'urlT=1', 'favTeam=3', 'Plan=1', 'Ergebnis=1',
            '[Teams]', '1=Alpha', '2=B & C', '3=Gamma', "4=O'Neill", '5=Epsilon', '6=Zeta', '7=Eta', '8=Theta',
            '[Teamk]', '1=ALP', '2=B&C', '3=GAM', "4=O'N", '5=EPS', '6=ZET', '7=ETA', '8=THE',
            '[Team1]', 'URL=https://alpha.example/?a=1&b=2',
            '[Team5]', 'URL=https://epsilon.example/',
            // Viertelfinale mit Hin- und Rueckspiel
            '[Round1]', 'D1=03.03.2025', 'D2=11.03.2025', 'MO=2',
            'TA1=1', 'TB1=8', 'GA11=2', 'GB11=0', 'AT11=' . $a(0), 'GA12=1', 'GB12=1', 'AT12=' . $a(7), 'NT12=Rueckspiel & Regen',
            'TA2=2', 'TB2=7', 'GA21=0', 'GB21=1', 'AT21=' . $a(0, 2), 'GA22=3', 'GB22=0', 'AT22=' . $a(7, 2), 'BE22=https://bericht.example/2?x=1&y=2',
            'TA3=3', 'TB3=6', 'GA31=1', 'GB31=1', 'AT31=' . $a(1), 'GA32=1', 'GB32=1', 'AT32=' . $a(8), 'SP32=1',
            'TA4=4', 'TB4=5', 'GA41=-1', 'GB41=-1', 'AT41=' . $a(1, 3), 'GA42=-1', 'GB42=-1',
            // Halbfinale
            '[Round2]', 'D1=18.03.2025', 'MO=1',
            'TA1=1', 'TB1=2', 'GA11=3', 'GB11=2', 'AT11=' . $a(15), 'NT11=nach Verlaengerung', 'SP11=2',
            'TA2=3', 'TB2=0', 'GA21=-1', 'GB21=-1', 'AT21=' . $a(16),
            // Finale und Spiel um Platz 3
            '[Round3]', 'MO=1',
            'TA1=1', 'TB1=3', 'GA11=-1', 'GB11=-1', 'AT11=' . $a(21),
            'TA2=2', 'TB2=4', 'GA21=2', 'GB21=2', 'AT21=' . $a(20), 'SP21=2', 'NT21=Platz 3 nach Verlaengerung',
        ];

        $playoff = static function (string $name, int $modus, int $spiele) use ($a): array {
            $zeilen = [
                '[Options]', 'Name=' . $name, 'Type=1', 'Teams=4', 'Rounds=2', 'Actual=1', 'playoffmode=' . $modus,
                'DatC=1', 'DatM=1', 'favTeam=2',
                '[Teams]', '1=Nord', '2=Sued', '3=Ost', '4=West',
                '[Round1]', 'MO=' . $spiele,
                'TA1=1', 'TB1=4', 'TA2=2', 'TB2=3',
            ];
            // Paarung 1 ist entschieden, Paarung 2 laeuft noch
            for ($n = 1; $n <= $spiele; $n++) {
                $gespielt = $n <= intdiv($spiele, 2) + 1;
                array_push($zeilen, 'GA1' . $n . '=' . ($gespielt ? ($n % 2) + 1 : -1), 'GB1' . $n . '=' . ($gespielt ? 0 : -1), 'AT1' . $n . '=' . $a(2 * $n));
                array_push($zeilen, 'GA2' . $n . '=' . ($n <= 2 ? $n : -1), 'GB2' . $n . '=' . ($n <= 2 ? 1 : -1), 'AT2' . $n . '=' . $a(2 * $n, 3));
            }
            array_push($zeilen, '[Round2]', 'MO=' . $spiele, 'TA1=1', 'TB1=0');
            for ($n = 1; $n <= $spiele; $n++) {
                array_push($zeilen, 'GA1' . $n . '=-1', 'GB1' . $n . '=-1');
            }

            return $zeilen;
        };

        return [
            'standard' => implode("\n", $standard) . "\n",
            'playoff221' => implode("\n", $playoff('Playoff 2-2-1', 1, 5)) . "\n",
            'playoff232' => implode("\n", $playoff('Playoff 2-3-2', 3, 7)) . "\n",
        ];
    }
}
