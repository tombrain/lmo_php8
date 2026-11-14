<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Seiten\Druckansicht;
use PHPUnit\Framework\TestCase;

/**
 * Die Links zur Druckansicht unter der Seite.
 */
class DruckansichtTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-druck-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/output', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/output/*') ?: []);
        rmdir($this->ordner . '/output');
        rmdir($this->ordner);
    }

    /** @param list<string> $optionen weitere Zeilen im Abschnitt Options der Liga */
    private function kontext(?string $datei, string $einsavehtml = '1', array $optionen = []): Kontext
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(array_merge(['[Options]', 'Teams=2'], $optionen)));

        return new Kontext(
            new Anfrage([]),
            'table',
            $datei !== null ? new GeladeneLiga($datei, $liga, '') : null,
            new Konfiguration(['einsavehtml' => $einsavehtml, 'diroutput' => 'output/']),
            [477 => 'Spieltag drucken', 478 => 'Druck ST', 479 => 'Spielplan drucken', 480 => 'Druck SP'],
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => '')
        );
    }

    private function dateien(string ...$endungen): void
    {
        foreach ($endungen as $endung) {
            touch($this->ordner . '/output/x.l98' . $endung);
        }
    }

    public function testBeideLinks(): void
    {
        $this->dateien('-st.html', '-sp.html');

        $html = Druckansicht::html($this->kontext('archiv/x.l98'), true);

        self::assertStringContainsString('<a href="/lmo/output/x.l98-st.html" title="Spieltag drucken">Druck ST</a>&nbsp;', $html, 'Datei ohne Unterordner');
        self::assertStringContainsString('<a href="/lmo/output/x.l98-sp.html" title="Spielplan drucken">Druck SP</a>&nbsp;', $html);
        self::assertSame(2, substr_count($html, '<td align="center">'));
    }

    public function testNurVorhandeneDateien(): void
    {
        $this->dateien('-sp.html');

        $html = Druckansicht::html($this->kontext('x.l98'), true);

        self::assertStringNotContainsString('-st.html', $html);
        self::assertStringContainsString('x.l98-sp.html', $html);
    }

    public function testSeiteOhneDruckansichtUndPokal(): void
    {
        $this->dateien('-st.html', '-sp.html');

        foreach ([Druckansicht::html($this->kontext('x.l98'), false), Druckansicht::html($this->kontext('x.l98', '1', ['Type=1']), true)] as $html) {
            self::assertStringContainsString('<table', $html, 'die Tabelle bleibt, nur ohne Links');
            self::assertStringNotContainsString('<a ', $html);
        }
    }

    public function testAbgeschaltetOderOhneLiga(): void
    {
        $this->dateien('-st.html', '-sp.html');

        self::assertSame('', Druckansicht::html($this->kontext('x.l98', '0'), true));
        self::assertSame('', Druckansicht::html($this->kontext(null), true));
    }
}
