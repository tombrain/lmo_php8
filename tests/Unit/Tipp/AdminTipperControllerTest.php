<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AdminTipperController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Userverwaltung des Tippspiels: Liste, Sortierung, letzter Tipp, Loeschen.
 */
class AdminTipperControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-tipper-' . uniqid();
        mkdir($this->ordner . '/tipps', 0777, true);
        file_put_contents($this->ordner . '/lmo-tippauth.txt', "<?php exit; ?>\n"
            . "bert|x|5|Bert Beispiel|bert@example.org|FC A||||1|1|EOL\n"
            . "anna|x|1|Anna Ärger||FC B||||1|1|EOL\n");
        file_put_contents($this->ordner . '/tipps/liga_anna.tip', '');
        touch($this->ordner . '/tipps/liga_anna.tip', mktime(10, 0, 0, 3, 1, 2025));
        file_put_contents($this->ordner . '/tipps/cup_anna.tip', '');
        touch($this->ordner . '/tipps/cup_anna.tip', mktime(20, 15, 0, 3, 2, 2025));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): ?string
    {
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        $t = [];
        for ($i = 0; $i <= 300; $i++) {
            $t[$i] = 'P' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tippuser']),
            '',
            null,
            new Konfiguration(['tipp' => ['dirtipp' => 'tipps/', 'tippauthtxt' => 'lmo-tippauth.txt']]),
            $text,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $t],
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new AdminTipperController($this->ordner);

        return $controller->zustaendig($kontext) ? $controller->zeige($kontext)->html : null;
    }

    public function testEingabenDerTipperMaskiert(): void
    {
        file_put_contents($this->ordner . '/lmo-tippauth.txt', "<?php exit; ?>\n"
            . "x'onclick='a()|x|5|Ina \"<b>B</b>|i@x.org\"><i>|<s>FC</s>||||1|1|EOL\n");
        $html = (string) $this->seite(['tipper_sort' => "id'><u>"]);

        foreach (['<b>B</b>', '<i>', '<s>FC', "nick=x'onclick", "'><u>"] as $roh) {
            self::assertStringNotContainsString($roh, $html, $roh);
        }
        self::assertStringContainsString('<td align="left">Ina&nbsp;&quot;&lt;b&gt;B&lt;/b&gt;</td>', $html, 'Vor- und Nachname, getrennt mit &nbsp;');
        self::assertStringContainsString('nick=x%27onclick%3D%27a%28%29', $html, 'der Nick in Links kodiert');
    }

    public function testNurHauptadmin(): void
    {
        self::assertNull($this->seite([], 1));
    }

    public function testListe(): void
    {
        $html = (string) $this->seite([]);

        self::assertStringContainsString('<td align="center">P114', $this->seiteMenue($html), 'Menue: die Userverwaltung ohne Link');
        self::assertLessThan(strpos($html, '>anna '), strpos($html, '>bert</a>'), 'nach Nummer: bert (1) vor anna (2)');
        self::assertStringContainsString('<a href="mailto:bert@example.org">bert</a>', $html);
        self::assertStringContainsString('<td align="left">Anna&nbsp;Ärger</td>', $html);
        self::assertStringContainsString('<td align="left">' . date('d.m.Y H:i', mktime(20, 15, 0, 3, 2, 2025)) . '</td>', $html, 'letzter Tipp: die juengste Tippdatei');
        self::assertStringContainsString('wrong.svg', $html, 'anna ist nicht freigeschaltet');
        self::assertStringContainsString('todo=tippuseredit&amp;nick=anna', $html);
    }

    public function testNameOhneLeerzeichen(): void
    {
        file_put_contents($this->ordner . '/lmo-tippauth.txt', "cleo|x|5|Cleopatra||||||1|1|EOL\n", FILE_APPEND);

        self::assertStringContainsString('<td align="left">Cleopatra</td>', (string) $this->seite([]), 'bis 4.5 "&nbsp;leopatra"');
    }

    public function testSortierung(): void
    {
        $html = (string) $this->seite(['tipper_sort' => 'nick']);
        self::assertLessThan(strpos($html, '>bert</a>'), strpos($html, '>anna '), 'nach Nick');

        $html = (string) $this->seite(['tipper_sort' => 'nick', 'tipper_sort_direction' => 'desc']);
        self::assertLessThan(strpos($html, '>anna '), strpos($html, '>bert</a>'), 'absteigend');
        self::assertStringContainsString('tipper_sort=nick&amp;del=bert', $html, 'Loeschen behaelt die Sortierung');
    }

    public function testLoeschen(): void
    {
        $html = (string) $this->seite(['del' => 'anna']);

        self::assertStringStartsWith('<p class="message">', $html);
        self::assertStringContainsString('T138', $html);
        self::assertSame("<?php exit; ?>\nbert|x|5|Bert Beispiel|bert@example.org|FC A||||1|1|EOL\n", file_get_contents($this->ordner . '/lmo-tippauth.txt'));
        self::assertFileDoesNotExist($this->ordner . '/tipps/liga_anna.tip');
        self::assertFileDoesNotExist($this->ordner . '/tipps/cup_anna.tip');
        self::assertStringNotContainsString('>anna', $html);
        self::assertSame((string) $this->seite(['del' => 'niemand']), (string) $this->seite([]), 'unbekannter Nick: nichts geloescht');
    }

    public function testLoeschenNurDieEigenenTipps(): void
    {
        file_put_contents($this->ordner . '/lmo-tippauth.txt', "na|x|5|N A||||||1|1|EOL\n", FILE_APPEND);
        file_put_contents($this->ordner . '/tipps/liga_na.tip', '');

        $this->seite(['del' => 'na']);

        self::assertFileDoesNotExist($this->ordner . '/tipps/liga_na.tip');
        self::assertFileExists($this->ordner . '/tipps/liga_anna.tip', 'bis 4.5 auch geloescht: endet auf na.tip');
        self::assertFileExists($this->ordner . '/tipps/cup_anna.tip');
    }

    /** Das Menue ueber der Seite (bis zur ersten Tabelle der Seite). */
    private function seiteMenue(string $html): string
    {
        return substr($html, 0, (int) strpos($html, 'lmoMiddle'));
    }
}
