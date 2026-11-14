<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Installation;
use Lmo\Web\Konfiguration;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Anwendung der Installation: Seiten des Kerns, Ligenuebersicht, Statistikdatei.
 */
class InstallationTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-installation-' . bin2hex(random_bytes(4));
        foreach (['ligen', 'output', 'addon', 'img'] as $unterordner) {
            mkdir($this->ordner . '/' . $unterordner, 0777, true);
        }
        file_put_contents($this->ordner . '/ligen/a.l98', "[Options]\nName=Liga A\nType=0\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=A\n2=B\n\n[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    private function installation(): Installation
    {
        return new Installation($this->ordner, $this->ordner . '/addon', $this->ordner . '/img', 'IMG', 'LMO', dirname(__DIR__, 3) . '/lmo/vorlagen');
    }

    /**
     * @param array<string, string> $anfrage
     * @param array<string, mixed> $cfg
     */
    private function seite(array $anfrage, array $cfg, bool $darf = true): string
    {
        $text = array_fill(0, 1000, 'T');
        $text['date'] = array_fill(0, 50, 'D');
        $anwendung = $this->installation()->anwendung($cfg + ['dirliga' => 'ligen/', 'diroutput' => 'output/'], $text, static fn (string $datei): bool => $darf, false, '9.9');
        $kontext = $anwendung->kontext(new Anfrage($anfrage), '/lmo/lmo.php', (string) ($anfrage['file'] ?? ''), new Sitzung([]));

        return (string) $anwendung->zeige($kontext)?->html;
    }

    public function testSeitenDesKerns(): void
    {
        self::assertStringContainsString('9.9', $this->seite(['file' => 'a.l98', 'action' => 'info'], []), 'die Info mit der Version');
        $ergebnisse = $this->seite(['file' => 'a.l98', 'action' => 'results'], []);
        self::assertMatchesRegularExpression('~>2</td>\s*<td[^>]*>:</td>\s*<td[^>]*>1<~', $ergebnisse, 'Ergebnisse der Liga: A - B 2:1');
    }

    public function testLigenuebersicht(): void
    {
        self::assertStringContainsString('Liga A', $this->seite([], ['backlink' => '1']), 'mit backlink: die Liste der Ligen');
        self::assertSame('', $this->seite([], ['backlink' => '0']), 'ohne backlink keine Seite');
    }

    public function testStatistikdatei(): void
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", (string) file_get_contents($this->ordner . '/ligen/a.l98'))));
        ($this->installation()->statistikErzeugen(new Konfiguration(['diroutput' => 'output/'])))($liga, 'unter/a.l98', [145 => 'Sp']);

        self::assertStringContainsString('$gzutore=3;', (string) file_get_contents($this->ordner . '/output/a.l98.php'));
    }
}
