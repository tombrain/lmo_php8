<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\Ersteinrichtung;
use PHPUnit\Framework\TestCase;

/**
 * Ersteinrichtung: das erste Konto, abgewiesene Eingaben, das Formular.
 */
class ErsteinrichtungTest extends TestCase
{
    private string $datei;

    protected function setUp(): void
    {
        $this->datei = sys_get_temp_dir() . '/lmo-einrichtung-' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->datei)) {
            unlink($this->datei);
        }
    }

    public function testLegtDenHauptadminAn(): void
    {
        $einrichtung = new Ersteinrichtung($this->datei);
        self::assertFalse($einrichtung->hatKonto());

        self::assertSame(0, $einrichtung->legeAn('admin', 'geheim123', 'geheim123'));
        self::assertTrue($einrichtung->hatKonto());
        $zeilen = file($this->datei, FILE_IGNORE_NEW_LINES) ?: [];
        self::assertSame('<?php exit(); ?>', $zeilen[0]);
        [$name, $hash, $rang] = explode('|', $zeilen[1]);
        self::assertSame(['admin', '2'], [$name, $rang]);
        self::assertTrue(password_verify('geheim123', $hash));
        self::assertSame(5022, $einrichtung->legeAn('zweiter', 'geheim123', 'geheim123'), 'nur einmal');
    }

    public function testAbgewiesen(): void
    {
        $einrichtung = new Ersteinrichtung($this->datei);
        self::assertSame(
            [5018, 5018, 5019, 5020, 5021],
            [
                $einrichtung->legeAn('ab', 'geheim123', 'geheim123'),
                $einrichtung->legeAn('ad|min', 'geheim123', 'geheim123'),
                $einrichtung->legeAn('admin', 'kurz', 'kurz'),
                $einrichtung->legeAn('admin', 'geheim|123', 'geheim|123'),
                $einrichtung->legeAn('admin', 'geheim123', 'anderes123'),
            ]
        );
        self::assertFalse($einrichtung->hatKonto());
        self::assertSame(5023, (new Ersteinrichtung('/gibt/es/nicht/lmo-auth.php'))->legeAn('admin', 'geheim123', 'geheim123'), 'nicht schreibbar');
    }

    public function testFormular(): void
    {
        $text = array_fill(0, 5030, 'T');
        $text[5019] = 'ZU KURZ';
        $html = (new Ersteinrichtung($this->datei))->formular($text, '/lmo/lmoadmin.php', '"<b>x</b>', 5019, '', 'IMG', dirname(__DIR__, 4) . '/lmo/vorlagen');

        self::assertStringContainsString('value="&quot;&lt;b&gt;x&lt;/b&gt;"', $html, 'der Name maskiert');
        self::assertStringContainsString('<p class="error"><img src="IMG/wrong.svg" border="0" width="20" alt=""> ZU KURZ</p>', $html);
        self::assertStringContainsString('<form action="/lmo/lmoadmin.php" method="post">', $html);
    }
}
