<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\LigaSpeichern;
use Lmo\Web\Konfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Speichern einer Liga aus der Administration: Normalform, Datei, Statistik und Druckansicht.
 */
class LigaSpeichernTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga\nType=0\nTeams=2\nRounds=2\nMatches=1\nActual=1\n\n[Teams]\n1=A\n2=B\n\n[Round1]\nTA1=1\nTB1=2\nGA1=1\nGB1=0\n";

    private string $ordner;

    /** @var list<string> was Statistik und Druckansicht bekamen */
    private array $aufrufe = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-ligaspeichern-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner . 'ligen', 0777, true);
        mkdir($this->ordner . 'output', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $cfg */
    private function speichern(array $cfg = [], bool $darf = true): LigaSpeichern
    {
        return new LigaSpeichern($this->ordner . 'ligen/', $this->ordner . 'output/', new Konfiguration($cfg), [54 => 'LMO', 138 => 'GESPEICHERT', 283 => 'FEHLER'], 'IMG', $darf,
            function (Liga $liga, string $datei): void {
                $this->aufrufe[] = 'Statistik ' . $datei;
                echo 'S ';
            },
            function (Liga $liga, string $st, string $datei): void {
                $this->aufrufe[] = 'Druckansicht ' . $datei . ' ' . $st;
            });
    }

    private static function liga(): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", self::LIGA)));
    }

    public function testSpeichern(): void
    {
        [$ausgabe, $liga] = $this->speichern()->speichere(self::liga(), 'a.l98', '2');

        self::assertSame('<p class="message"><img src="IMG/right.svg" border="0" width="20" alt=""> GESPEICHERT</p>', $ausgabe);
        self::assertSame('2', $liga->optionen['Actual'] ?? null, 'der gewaehlte Spieltag als aktueller');
        self::assertSame($liga->alsText(), file_get_contents($this->ordner . 'ligen/a.l98'));
        self::assertSame([], $this->aufrufe, 'ohne Statistik und Druckansicht');
    }

    public function testMitStatistikUndDruckansicht(): void
    {
        file_put_contents($this->ordner . 'output//viewer_a_count.txt', '3');
        [$ausgabe] = $this->speichern(['einzustats' => '1', 'einsavehtml' => '1'])->speichere(self::liga(), 'a.l98', '');

        self::assertStringStartsWith('S <p class="message">', $ausgabe, 'die Ausgabe der Statistik zuerst');
        self::assertSame(['Statistik a.l98', 'Druckansicht a.l98 '], $this->aufrufe);
        self::assertFileDoesNotExist($this->ordner . 'output//viewer_a_count.txt', 'der Zaehler des Viewer-Caches');
    }

    public function testNichtGespeichert(): void
    {
        [$ausgabe, $liga] = $this->speichern(darf: false)->speichere(self::liga(), 'a.l98', '1');
        self::assertSame(['', self::liga()->alsText()], [$ausgabe, $liga->alsText()], 'ohne Anmeldung nichts, die Liga unveraendert');
        self::assertFileDoesNotExist($this->ordner . 'ligen/a.l98');
        self::assertSame('', $this->speichern()->speichere(self::liga(), 'a.txt', '1')[0], 'keine Ligadatei');

        [$ausgabe] = $this->speichern()->speichere(self::liga(), 'fehlt/a.l98', '1');
        self::assertStringContainsString('FEHLER', $ausgabe, 'nicht schreibbar');
    }
}
