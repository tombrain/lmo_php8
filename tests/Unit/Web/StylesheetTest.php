<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Stylesheet;
use PHPUnit\Framework\TestCase;

/**
 * Die Stylesheets aus der Konfiguration: gesetzte und leere Werte, die eigene style.css.
 */
class StylesheetTest extends TestCase
{
    private string $eigene;

    protected function setUp(): void
    {
        $this->eigene = sys_get_temp_dir() . '/lmo-style-' . bin2hex(random_bytes(4)) . '.css';
    }

    protected function tearDown(): void
    {
        if (is_file($this->eigene)) {
            unlink($this->eigene);
        }
    }

    /** @param array<string, mixed> $konfiguration */
    private function css(array $konfiguration, string $vorlage = 'style.css.twig'): string
    {
        return Stylesheet::css(dirname(__DIR__, 3) . '/lmo/vorlagen', $vorlage, $konfiguration, 'IMG', $this->eigene);
    }

    public function testWerte(): void
    {
        $css = $this->css(['lmo_main_background1' => '#fff', 'lmo_main_color1' => '#123', 'lmo_main_underline1' => 'underline', 'tipp' => ['x' => '1']]);

        self::assertStringContainsString(".lmoMain {\n  background: #fff repeat;\n  color: #123;  border: 0;", $css);
        self::assertStringContainsString("text-decoration: underline;", $css);
        self::assertStringContainsString('IMG/', $css, 'der Bilderordner');
    }

    public function testLeereWerte(): void
    {
        $css = $this->css(['lmo_main_background1' => '#fff', 'lmo_main_color1' => '0']);

        self::assertStringContainsString(".lmoMain {\n  background: #fff repeat;\n    border: 0;", $css, 'ohne Farbe (auch "0" wie empty()) keine Zeile');
        self::assertStringContainsString("text-decoration: none;", $css, 'ohne Unterstreichung: none');
    }

    public function testEigeneDatei(): void
    {
        file_put_contents($this->eigene, ".eigen { color: red; }\n<?php echo '/* aus PHP */'; ?>\n");

        self::assertStringEndsWith(".eigen { color: red; }\n/* aus PHP */", rtrim($this->css([], 'style-nc.css.twig')));
        self::assertStringNotContainsString('.eigen', (function (): string {
            unlink($this->eigene);

            return $this->css([]);
        })(), 'ohne die Datei nichts');
    }
}
