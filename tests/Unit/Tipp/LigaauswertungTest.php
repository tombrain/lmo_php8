<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Ligaauswertung;
use Lmo\Liga\Rohdaten\Liga;
use PHPUnit\Framework\TestCase;

/**
 * Die Auswertung der Tipps einer Liga in auswert/<liga>.aus und auswert/vereine/*.ver.
 *
 * Die Liga: Spieltag 1 Mannschaft 1 - 2 2:1 und 3 - 4 1:1, Spieltag 2 1 - 3 0:2 und 2 - 4 noch
 * offen. Regeln: Ergebnis 4, Tordifferenz 3, Tendenz 2 Punkte, Joker doppelt.
 */
class LigaauswertungTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-ligaauswertung-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert/vereine', 0777, true);
        file_put_contents($this->ordner . '/liga.l98', "[Options]\nName=Liga\nType=0\nTeams=4\nRounds=2\nMatches=2\n\n[Teams]\n1=A\n2=B\n3=C\n4=D\n\n"
            . "[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\nTA2=3\nTB2=4\nGA2=1\nGB2=1\n\n[Round2]\nTA1=1\nTB1=3\nGA1=0\nGB1=2\nTA2=2\nTB2=4\nGA2=-1\nGB2=-1\n");
        file_put_contents($this->ordner . '/tipper.txt', "<?php exit; ?>\nanna|x|5|Anna A|anna@x|Rot||||1|1|EOL\nbert|x|5|Bert B|bert@x|||||1|1|EOL\n");
        // anna: Spieltag 1 mit Joker auf Spiel 1 (Ergebnis: 8) und 0:0 (Tendenz: 2), Spieltag 2 1:2 (Tendenz: 2)
        file_put_contents($this->ordner . '/tipps/liga_anna.tip', "[Round1]\n@1@\nGA1=2\nGB1=1\nGA2=0\nGB2=0\n[Round2]\n@0@\nGA1=1\nGB1=2\nGA2=3\nGB2=0\n");
        // bert: Spieltag 1 daneben
        file_put_contents($this->ordner . '/tipps/liga_bert.tip', "[Round1]\nGA1=0\nGB1=1\n");
        // cleo hat Tipps, steht aber nicht in der Tipperdatei
        file_put_contents($this->ordner . '/tipps/liga_cleo.tip', "[Round1]\nGA1=2\nGB1=1\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $cfg */
    private function werte(int $st, array $cfg = [], ?int $von = null, ?int $bis = null, bool $mitLiga = true): string
    {
        $auswertung = new Ligaauswertung($this->ordner . '/tipps/', $this->ordner . '/tipper.txt', $cfg + [
            'tippmodus' => '1', 'rergebnis' => '4', 'rtendenzdiff' => '3', 'rtendenz' => '2', 'rtor' => '0', 'rtendenztor' => '1',
            'rtendenzremis' => '1', 'rremis' => '0', 'entscheidungnv' => '1', 'entscheidungie' => '1', 'gtpunkte' => '0',
            'jokertipp' => '1', 'jokertippmulti' => '2', 'showzus' => '0', 'wertverein' => '0', 'showname' => '0', 'showemail' => '0',
        ], 'nV', 'iE');
        self::assertTrue($auswertung->werte('liga', $mitLiga ? Liga::lies($this->ordner . '/liga.l98') : null, $st, $von, $bis));

        return (string) file_get_contents($this->ordner . '/tipps/auswert/liga.aus');
    }

    public function testAlleSpieltage(): void
    {
        self::assertSame("\n[anna]\nTeam=Rot\nSG1=2\nTP1=10\nSG2=1\nTP2=2\n\n[bert]\nTeam=\nSG1=1\n", $this->werte(0), 'cleo ohne Tipper nicht');
    }

    public function testMitNameEmailUndZusatz(): void
    {
        self::assertStringStartsWith("\n[anna]\nTeam=Rot\nName=Anna A\nEmail=anna@x\nSG1=2\nP11=1\nP31=1\nP61=4\nTP1=10\n\n[bert]",
            $this->werte(1, ['showname' => '1', 'showemail' => '1', 'showzus' => '1']));
    }

    public function testEinSpieltagFuehrtDieAlteDateiFort(): void
    {
        file_put_contents($this->ordner . '/tipps/auswert/liga.aus', "[anna]\nTeam=Alt\nName=Alt\nSG1=9\nTP1=9\nSG2=5\n\n[weg]\nSG1=1\n");

        self::assertSame("\n[anna]\nTeam=Rot\nSG2=1\nTP2=2\nSG1=9\nTP1=9\n\n[bert]\nTeam=\n", $this->werte(2), 'ohne den alten Spieltag 2, Team und Name; weg hat keine Tipps mehr');
    }

    public function testVonBis(): void
    {
        file_put_contents($this->ordner . '/tipps/auswert/liga.aus', "[anna]\nTeam=Alt\nSG1=9\n\n[bert]\nTeam=\nSG1=7\n");

        self::assertSame("[anna]\nTeam=Alt\nSG1=9\n\n[bert]\nTeam=\nSG1=1\n", $this->werte(0, [], 2, 3), 'anna wie sie war, nach Tippdateien gezaehlt');
    }

    public function testVereine(): void
    {
        $this->werte(0, ['wertverein' => '1']);

        self::assertSame("[1]\nTP1=8\nSG1=1\nTP2=2\nSG2=1\n[2]\nTP1=8\nSG1=1\n[3]\nTP1=2\nSG1=1\nTP2=2\nSG2=1\n[4]\nTP1=2\nSG1=1\n",
            file_get_contents($this->ordner . '/tipps/auswert/vereine/liga_anna.ver'));

        // ein Spieltag: die Werte der anderen bleiben
        file_put_contents($this->ordner . '/tipps/liga_anna.tip', "[Round1]\nGA1=0\nGB1=3\n[Round2]\nGA1=0\nGB1=2\n");
        $this->werte(2, ['wertverein' => '1']);
        self::assertSame("[1]\nTP1=8\nSG1=1\nTP2=4\nSG2=1\n[2]\nTP1=8\nSG1=1\n[3]\nTP1=2\nSG1=1\nTP2=4\nSG2=1\n[4]\nTP1=2\nSG1=1\n",
            file_get_contents($this->ordner . '/tipps/auswert/vereine/liga_anna.ver'));
    }

    public function testOhneLigaLeer(): void
    {
        file_put_contents($this->ordner . '/tipps/auswert/liga.aus', "[anna]\nSG1=9\n");

        self::assertSame('', $this->werte(0, mitLiga: false), 'wie bis 4.5');
    }
}
