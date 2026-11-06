<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Gesamtwertung;
use PHPUnit\Framework\TestCase;

/**
 * Die Gesamtwertung aus den Auswertungen der Ligen in auswert/gesamt.aus.
 */
class GesamtwertungTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-gesamtwertung-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/tipper.txt', "<?php exit; ?>\nanna|x|5|Anna A|anna@x|Rot||||1|1|EOL\n\nbert|x|5|Bert B|bert@x\n");
        file_put_contents($this->ordner . '/tipps/auswert/bula.aus', "\n[anna]\nTeam=Rot\nSG1=2\nP11=1\nTP1=4.5\nSG2=1\nP11=2\nTP2=3\n\n[cleo]\nSG1=9\nTP1=9\n");
        file_put_contents($this->ordner . '/tipps/auswert/cup.aus', "\n[bert]\nTeam=\nSG1=1\nTP1=2\n");
        file_put_contents($this->ordner . '/tipps/auswert/gesamt.aus', 'alt');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $cfg */
    private function werte(array $cfg): string
    {
        self::assertTrue((new Gesamtwertung($this->ordner . '/tipps/', $this->ordner . '/tipper.txt', $cfg))->werte());

        return (string) file_get_contents($this->ordner . '/tipps/auswert/gesamt.aus');
    }

    public function testAlleLigen(): void
    {
        self::assertSame("[Options]\nAnzLigen=2\nTT1=bula.aus\nTT2=cup.aus\n"
            . "\n[anna]\nTeam=Rot\nTP1=7\nSG1=3\nTP2=0\nSG2=0\n"
            . "\n[bert]\nTeam=\nTP1=0\nSG1=0\nTP2=2\nSG2=1\n",
            $this->werte(['immeralle' => '1']), 'Kommapunkte fallen wie bis 4.5 weg, cleo ist kein Tipper, die Leerzeile auch nicht');
    }

    public function testNurLigenZumTippenMitZusatz(): void
    {
        self::assertSame("[Options]\nAnzLigen=1\nTT1=bula.aus\n"
            . "\n[anna]\nTeam=Rot\nName=Anna A\nEmail=anna@x\nTP1=7\nSG1=3\nP11=3\n"
            . "\n[bert]\nTeam=\nName=Bert B\nEmail=bert@x\nTP1=0\nSG1=0\n",
            $this->werte(['immeralle' => '0', 'ligenzutippen' => 'bula', 'showzus' => '1', 'showname' => '1', 'showemail' => '1']));
    }
}
