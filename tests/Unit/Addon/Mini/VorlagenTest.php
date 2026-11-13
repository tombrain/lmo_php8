<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon\Mini;

use Lmo\Addon\Mini\Vorlagen;
use PHPUnit\Framework\TestCase;

/**
 * Die Vorlagen des Mini: mitgelieferte mit Twig, angepasste und eigene nach alter Art.
 */
class VorlagenTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-mini-vorlagen-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
        foreach (array_keys(Vorlagen::MITGELIEFERT) as $name) {
            copy(self::lmo() . '/template/mini/' . $name . '.tpl.php', $this->ordner . '/' . $name . '.tpl.php');
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    private static function lmo(): string
    {
        return dirname(__DIR__, 4) . '/lmo';
    }

    private function vorlagen(): Vorlagen
    {
        return new Vorlagen($this->ordner, self::lmo() . '/addon/mini/vorlagen');
    }

    /** HTML ohne Unterschiede im Leerraum */
    private static function kanonisch(string $html): string
    {
        return trim((string) preg_replace(['/\s+/', '/>\s*</'], [' ', '><'], $html));
    }

    public function testMitgelieferteMitTwig(): void
    {
        foreach (array_keys(Vorlagen::MITGELIEFERT) as $name) {
            self::assertTrue($this->vorlagen()->mitTwig($name), $name . ': die ausgelieferte Fassung');
            self::assertFileExists(self::lmo() . '/addon/mini/vorlagen/' . $name . '.html.twig');
        }
        // auch mit Windows-Zeilenenden
        file_put_contents($this->ordner . '/all.tpl.php', str_replace("\n", "\r\n", str_replace("\r\n", "\n", (string) file_get_contents($this->ordner . '/all.tpl.php'))));
        self::assertTrue($this->vorlagen()->mitTwig('all'));
    }

    public function testTwigWieAlteArt(): void
    {
        $werte = ['Link' => 'L', 'Tabelle' => 'Tab', 'ligaDatum' => 'Stand'];
        $zeile = ['Platz' => '<strong>1</strong>', 'TeamLang' => 'Lang', 'Team' => 'K', 'TeamMittel' => 'M', 'Punkte' => '3', 'Style' => 'color:red;', 'Tordifferenz' => '+1'];
        $bloecke = ['Inhalt' => [$zeile, ['Platz' => '2'] + $zeile]];
        foreach (['all', 'round', 'round_all', 'standard', 'standard_old'] as $name) {
            $twig = $this->vorlagen()->zeige($name . '.tpl.php', $werte, $bloecke);
            // nach alter Art: eine angepasste Datei (ein Leerzeichen mehr am Ende)
            file_put_contents($this->ordner . '/' . $name . '.tpl.php', ' ', FILE_APPEND);
            self::assertFalse($this->vorlagen()->mitTwig($name));
            $alt = $this->vorlagen()->zeige($name . '.tpl.php', $werte, $bloecke);

            self::assertSame(self::kanonisch($alt), self::kanonisch($twig), $name);
            self::assertStringContainsString('<strong>1</strong>', $twig);
            self::assertStringNotContainsString('<!--', str_replace(['<!-- ', '<!--['], '', $twig), $name . ': keine Platzhalter uebrig');
        }
    }

    public function testEigeneVorlage(): void
    {
        file_put_contents($this->ordner . '/meine.tpl.php', "<ul><!-- BEGIN Inhalt --><li><!--Team--></li><!-- END Inhalt --></ul><p><!--Tabelle--></p>");
        self::assertFalse($this->vorlagen()->mitTwig('meine'));
        self::assertSame('<ul><li>A</li><li>B</li></ul><p>T</p>', $this->vorlagen()->zeige('meine.tpl.php', ['Tabelle' => 'T'], ['Inhalt' => [['Team' => 'A'], ['Team' => 'B']]]));
    }
}
