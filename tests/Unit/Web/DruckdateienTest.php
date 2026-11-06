<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Bilder;
use Lmo\Web\Druckdateien;
use PHPUnit\Framework\TestCase;

/**
 * Die Druckansicht als HTML-Dateien: <liga>-st.html und <liga>-sp.html.
 */
class DruckdateienTest extends TestCase
{
    // 3 Mannschaften: je Spieltag eine spielfrei; 1. A-B 2:1, 2. C-A 1:1 (gegen beide), 3. B-C offen
    private const LIGA = "[Options]\nName=Testliga\nType=0\nTeams=3\nRounds=3\nMatches=1\nActual=2\n\n[Teams]\n1=A\n2=B\n3=C\n\n"
        . "[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\n\n[Round2]\nTA1=3\nTB1=1\nGA1=1\nGB1=1\nET1=3\n\n[Round3]\nTA1=2\nTB1=3\nGA1=-1\nGB1=-1\n";

    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-druckdateien-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    private function schreibe(string $liga, string $st): void
    {
        $text = [2 => 'Spieltag', 4 => 'bis', 16 => 'Tabelle', 33 => 'Sp', 37 => 'Pkt', 38 => 'Tore', 39 => 'Diff', 562 => 'zurueck', 568 => 'Saisonende', 576 => 'Stand', 4004 => 'Spielfrei', 'date' => []];
        $bilder = new Bilder($this->ordner . 'img', 'IMG');
        (new Druckdateien($this->ordner, $text, $bilder, 'd.m.Y', dirname(__DIR__, 3) . '/lmo/vorlagen'))
            ->schreibe(Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), 'unter/test.l98', $st);
    }

    private static function flach(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags($html))));
    }

    public function testSpieltag(): void
    {
        $this->schreibe(self::LIGA, '2');
        $html = (string) file_get_contents($this->ordner . 'test.l98-st.html');

        self::assertStringContainsString('<title>Testliga</title>', $html);
        self::assertStringContainsString('2. Spieltag', self::flach($html));
        self::assertStringContainsString('C - A 1 : 1 / 1 : 1', self::flach($html), 'gegen beide: das Ergebnis auch umgekehrt');
        self::assertStringContainsString('Spielfrei: B', self::flach($html));
        self::assertStringContainsString('Tabelle - 2. Spieltag', self::flach($html));
        self::assertStringContainsString('1 A 2 4 3:2 1 2 C 1 1 1:1 0 3 B 1 0 1:2 -1', self::flach($html), 'die Tabelle bis zum Spieltag');
        self::assertStringContainsString('3. Spieltag', self::flach($html), 'der folgende');
        self::assertStringNotContainsString('Saisonende', $html);
    }

    public function testLetzterSpieltag(): void
    {
        $this->schreibe(self::LIGA, '3');
        $html = (string) file_get_contents($this->ordner . 'test.l98-st.html');
        self::assertStringContainsString('<strong>Saisonende</strong>', $html);
        self::assertStringNotContainsString('4. Spieltag', $html);
    }

    public function testSpielplan(): void
    {
        $this->schreibe(self::LIGA, '');
        self::assertFileDoesNotExist($this->ordner . 'test.l98-st.html', 'ohne Spieltag keine Datei zum Spieltag');
        $text = self::flach((string) file_get_contents($this->ordner . 'test.l98-sp.html'));
        foreach (['1. Spieltag', 'A - B 2 : 1', 'Spielfrei: C', '2. Spieltag', '3. Spieltag', 'B - C _ : _', 'Spielfrei: A', 'Tabelle'] as $teil) {
            self::assertStringContainsString($teil, $text);
        }
    }

    public function testPokal(): void
    {
        $this->schreibe(str_replace('Type=0', 'Type=1', self::LIGA), '1');
        self::assertSame(['.', '..'], scandir($this->ordner), 'im Pokal nichts');
    }
}
