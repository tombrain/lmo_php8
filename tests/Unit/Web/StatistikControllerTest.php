<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\StatistikController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Die Statistik: vier Mannschaften (D heisst "D & Co"), zwei Spieltage gespielt (A - B 2:0,
 * C - D 1:1; B - C 3:1, D - A 0:1), zwei offen (A - C mit Anstoss, D - B ohne; C - A, B - D).
 */
class StatistikControllerTest extends TestCase
{
    private static ?string $ordner = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$ordner !== null) {
            array_map('unlink', glob(self::$ordner . '/*/*') ?: []);
            array_map('rmdir', glob(self::$ordner . '/*') ?: []);
            @rmdir(self::$ordner);
        }
    }

    /**
     * @param array<string, string> $parameter
     * @param array<string, string> $konfiguration
     */
    private static function kontext(array $parameter = [], array $konfiguration = [], array $optionen = []): Kontext
    {
        if (self::$ordner === null) {
            self::$ordner = sys_get_temp_dir() . '/lmo-statistik-' . bin2hex(random_bytes(4));
            mkdir(self::$ordner . '/ligen', 0777, true);
            mkdir(self::$ordner . '/output');
            file_put_contents(self::$ordner . '/ligen/x.l98', '');
        }
        $zeilen = array_merge(['[Options]', 'Teams=4', 'Rounds=4', 'Matches=2', 'Actual=2'], $optionen, [
            '[Teams]', '1=A', '2=B', '3=C', '4=D & Co',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=0', 'TA2=3', 'TB2=4', 'GA2=1', 'GB2=1',
            '[Round2]', 'TA1=2', 'TB1=3', 'GA1=3', 'GB1=1', 'TA2=4', 'TB2=1', 'GA2=0', 'GB2=1',
            '[Round3]', 'TA1=1', 'TB1=3', 'AT1=' . mktime(15, 0, 0, 3, 1, 2025), 'TA2=4', 'TB2=2',
            '[Round4]', 'TA1=3', 'TB1=1', 'AT1=' . mktime(15, 0, 0, 2, 1, 2025), 'TA2=2', 'TB2=4',
        ]);
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter),
            'stats',
            new GeladeneLiga('x.l98', Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), ''),
            new Konfiguration($konfiguration + ['dirliga' => 'ligen/', 'diroutput' => 'output/', 'einzustats' => '0']),
            $text,
            '/lmo.php',
            new Pfade(self::$ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '[W:' . $name . ']')
        );
    }

    public function testOhneMannschaftDerHinweis(): void
    {
        $html = (new StatistikController())->zeige(self::kontext())->html;

        self::assertStringContainsString('&nbsp;<br>T24<br>&nbsp;', $html);
        self::assertStringContainsString('<a href="/lmo.php?action=stats&amp;file=x.l98&amp;stat1=4&amp;stat2=0">D &amp; C</a>', $html, 'Kurzname');
        self::assertStringNotContainsString('T4009', $html, 'ohne einzustats keine Saisonstatistik');
    }

    public function testVergleichZweierMannschaften(): void
    {
        $html = (new StatistikController())->zeige(self::kontext(['stat1' => '1', 'stat2' => '2']))->html;

        self::assertStringContainsString('<th class="text-right">A</th>', $html);
        self::assertStringContainsString('<th class="text-left">B</th>', $html);
        self::assertMatchesRegularExpression('#<td align="right">\d+,\d\d%</td>\s*<th class="text-center">T60</th>#', $html, 'Chance');
        self::assertMatchesRegularExpression('#<td align="right">1</td>\s*<th class="text-center">T61</th>\s*<td align="left">2</td>#', $html, 'Plaetze');
        self::assertMatchesRegularExpression('#<td align="right">6</td>\s*<th class="text-center">T37</th>#', $html, 'Punkte von A');
        self::assertStringContainsString('2 (100,00%)', $html, 'Siege von A');
    }

    public function testRestprogramm(): void
    {
        $html = (new StatistikController())->zeige(self::kontext(['stat1' => '1', 'stat2' => '2']))->html;

        // A: erst C - A (Februar), dann A - C (Maerz); C steht auf Platz 4
        self::assertStringContainsString('<td align="right" valign="top">T74&nbsp;C&nbsp;(4.)<br>T73&nbsp;C&nbsp;(4.)<br></td>', $html);
        // B: ohne Anstoss nach Spieltag: D - B, dann B - D
        self::assertStringContainsString('<td align="left" valign="top">(3.)&nbsp;D &amp; Co&nbsp;T74<br>(3.)&nbsp;D &amp; Co&nbsp;T73<br></td>', $html);
        self::assertStringContainsString('T4021', $html, 'Vergleich der Restprogramme');
    }

    public function testZweiteOhneSpieleNurDasEndeDerZelle(): void
    {
        $mitSpielen = (new StatistikController())->zeige(self::kontext(['stat1' => '1', 'stat2' => '4']))->html;
        self::assertMatchesRegularExpression('#T37T64</th>\s*<td align="left">0.5</td>#', $mitSpielen, 'D: 1 Punkt aus 2 Spielen');

        // Mannschaft 5 hat noch nicht gespielt: wie bisher steht nur das Ende ihrer Zelle da
        $ohne = (new StatistikController())->zeige(self::kontext(['stat1' => '1', 'stat2' => '5'], [], ['Teams=5']))->html;
        self::assertMatchesRegularExpression('#T37T64</th>\s*</td>\s*</tr>#', $ohne);
    }

    public function testSaisonstatistikWirdErzeugt(): void
    {
        $erzeugt = 0;
        $erzeugen = static function (Liga $liga, string $datei, array $text) use (&$erzeugt): void {
            $erzeugt++;
            file_put_contents(self::$ordner . '/output/' . $datei . '.php', '<?php $gzusieg1=3; $gzusieg2=1; $gzuunent=2; $gbeide=0; $gzutore=9; $gdstore=1.5; $gheimtore=5; $ggasttore=4; $dsheimtore=0.8; $dsgasttore=0.7;');
        };
        @unlink(self::kontext()->pfade->lmo . '/output/x.l98.php');

        $html = (new StatistikController($erzeugen))->zeige(self::kontext([], ['einzustats' => '1']))->html;

        self::assertSame(1, $erzeugt, 'fehlende Statistikdatei');
        self::assertStringContainsString('<h1>T4009</h1>', $html);
        self::assertStringContainsString('<strong>6</strong>', $html, 'Spiele gesamt');
        self::assertStringContainsString('<strong>3</strong> (50%) ', $html, 'Heimsiege');
        self::assertStringContainsString('<strong>9</strong> (T5171.5)', $html, 'Tore');
        self::assertStringNotContainsString('T4013', $html, 'ohne Rekordspiele');

        (new StatistikController($erzeugen))->zeige(self::kontext([], ['einzustats' => '1']));
        self::assertSame(1, $erzeugt, 'aktuelle Datei wird nicht neu geschrieben');
    }
}
