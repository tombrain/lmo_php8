<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Liga;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Saisonstatistik;
use Lmo\Web\Statistikdatei;
use PHPUnit\Framework\TestCase;

/**
 * Die Statistik einer Saison (output/<liga>.l98.php), gelesen wie von den Seiten.
 */
class SaisonstatistikTest extends TestCase
{
    // 1. Spieltag A-B 3:0, C-D 1:1; 2. C-A 0:2, B-D 2:2; 3. A-D 1:0, B-C offen
    private const LIGA = "[Options]\nName=Liga\nType=0\nTeams=4\nRounds=3\nMatches=2\n\n[Teams]\n1=A\n2=B\n3=C\n4=D\n\n"
        . "[Round1]\nTA1=1\nTB1=2\nGA1=3\nGB1=0\nTA2=3\nTB2=4\nGA2=1\nGB2=1\n\n"
        . "[Round2]\nTA1=3\nTB1=1\nGA1=0\nGB1=2\nTA2=2\nTB2=4\nGA2=2\nGB2=2\n\n"
        . "[Round3]\nTA1=1\nTB1=4\nGA1=1\nGB1=0\nTA2=2\nTB2=3\nGA2=-1\nGB2=-1\n";

    private string $datei;

    protected function setUp(): void
    {
        $this->datei = sys_get_temp_dir() . '/lmo-saisonstatistik-' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->datei)) {
            unlink($this->datei);
        }
    }

    private static function liga(string $text): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $text)));
    }

    private function statistik(string $text): Statistikdatei
    {
        Saisonstatistik::schreibe(self::liga($text), $this->datei, 'Sp');

        return Statistikdatei::lies($this->datei) ?? self::fail('keine Datei');
    }

    public function testSaison(): void
    {
        $s = $this->statistik(self::LIGA);

        $zahlen = [];
        foreach (['gzutore', 'gdstore', 'gzusieg1', 'gzusieg2', 'gzuunent', 'gbeide', 'gheimtore', 'ggasttore', 'dsheimtore', 'dsgasttore'] as $name) {
            $zahlen[$name] = $s->zahl($name);
        }
        self::assertEquals(['gzutore' => 12, 'gdstore' => 2.4, 'gzusieg1' => 2, 'gzusieg2' => 1, 'gzuunent' => 2, 'gbeide' => 0,
            'gheimtore' => 7, 'ggasttore' => 5, 'dsheimtore' => 1.4, 'dsgasttore' => 1], $zahlen);
        self::assertSame([5, 6, 1], array_map(static fn (int $st): int|float|string => $s->toreSpieltag($st), [1, 2, 3]), 'Tore je Spieltag');
    }

    public function testRekorde(): void
    {
        $s = $this->statistik(self::LIGA);

        self::assertSame(['A', 'B', '3', '0', '1', '0'], [$s->text('hheimsieg'), $s->text('hgastsieg'), $s->text('hheimsiegtor'), $s->text('hgastsiegtor'), $s->text('spieltagflag'), $s->text('counteranz')], 'hoechster Heimsieg');
        self::assertSame(['0', '0'], [$s->text('hheimsieg1'), $s->text('spieltagflag1')], 'kein zweiter mit demselben Ergebnis');
        self::assertSame(['C', 'A', '0', '2', '2'], [$s->text('aheimsieg'), $s->text('agastsieg'), $s->text('aheimsiegtor'), $s->text('agastsiegtor'), $s->text('spieltagflag2')], 'hoechster Auswaertssieg');
        self::assertSame(['B', 'D', '2', '2', '2', '1'], [$s->text('htorreichm1'), $s->text('htorreichm2'), $s->text('htorreicht1'), $s->text('htorreicht2'), $s->text('spieltagflag4'), $s->text('counteranz5')], 'torreichstes Spiel');
    }

    public function testSerien(): void
    {
        $s = $this->statistik(self::LIGA);

        self::assertSame(['3', 'A'], [$s->text('akt_gewonnen'), $s->text('team_akt_gewonnen')]);
        self::assertSame(['3', 'D'], [$s->text('akt_sieglos'), $s->text('team_akt_sieglos')]);
        self::assertSame(['1', "C\nD"], [$s->text('akt_verloren'), $s->text('team_akt_verloren')], 'gleich lang: alle');
        self::assertSame(['1', 'B'], [$s->text('akt_unentschieden'), $s->text('team_akt_unentschieden')]);
        self::assertSame(['3', 'A (1-3.Sp)'], [$s->text('max_gewonnen'), $s->text('team_max_gewonnen')]);
        self::assertSame(['2', 'D (1-2.Sp)'], [$s->text('max_unentschieden'), $s->text('team_max_unentschieden')]);
        self::assertSame(['1', "B (1-1.Sp)\nC (2-2.Sp)\nD (3-3.Sp)"], [$s->text('max_verloren'), $s->text('team_max_verloren')]);
    }

    public function testSonderfaelle(): void
    {
        // Wertung "gegen beide" mit nur einer Seite eingetragen: bis 4.5 brach das ab
        $s = $this->statistik("[Options]\nName=Liga\nType=0\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=A\n2=B\n\n[Round1]\nTA1=1\nTB1=2\nGA1=-1\nGB1=2\nET1=3\n");
        self::assertSame([1, 0], [(int) $s->zahl('gbeide'), (int) $s->zahl('gzutore')]);

        // ohne Spieltage: Durchschnitte 0
        self::assertSame('0', $this->statistik("[Options]\nName=Liga\nType=0\nTeams=2\nRounds=0\nMatches=1\n\n[Teams]\n1=A\n2=B\n")->text('gdstore'));

        unlink($this->datei);
        Saisonstatistik::schreibe(self::liga("[Options]\nName=Pokal\nType=1\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=A\n2=B\n"), $this->datei, 'Sp');
        self::assertFileDoesNotExist($this->datei, 'im Pokal keine Datei');
    }

    public function testNamenWerdenNichtAusgefuehrt(): void
    {
        $name = '{${print(7)}} $a';
        $this->expectOutputString('');
        $s = $this->statistik("[Options]\nName=Liga\nType=0\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=" . $name . "\n2=B\n\n[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=0\n");

        self::assertSame(htmlspecialchars($name), $s->text('hheimsieg'), 'der Name als Text');
    }
}
