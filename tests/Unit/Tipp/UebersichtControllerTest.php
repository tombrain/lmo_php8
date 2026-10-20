<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Addon\Tipp\UebersichtController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Tippuebersicht (viewermode) eines angemeldeten Tippers.
 */
class UebersichtControllerTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param array<string, string> $tipp
     */
    private static function kontext(array $parameter = [], string $ok = '5', array $tipp = []): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'edit', 'file' => '', 'viewermode' => '1']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'dirliga' => 'gibtsnicht/', 'tipp' => $tipp + ['viewertipp' => '1', 'viewertage' => '8', 'tippmodus' => '1', 'regeln' => '0']]),
            $texte,
            '/lmo.php',
            new Pfade(sys_get_temp_dir(), dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung(['lmotipperok' => $ok, 'lmotippername' => 'tester', 'lmotipperverein' => 'FC'])
        );
    }

    public function testZustaendigUndOhneSpiele(): void
    {
        $controller = new UebersichtController(sys_get_temp_dir(), new Tipprahmen(static fn (string $datei): string => ''));

        self::assertTrue($controller->zustaendig(self::kontext(['todo' => 'einsicht'])), 'viewermode zeigt die Uebersicht, gleich welches todo');
        self::assertFalse($controller->zustaendig(self::kontext(ok: '0')), 'Gaeste nicht');
        $ohneViewer = self::kontext();
        self::assertFalse($controller->zustaendig(new Kontext(new Anfrage(['action' => 'tipp', 'todo' => 'edit']), 'tipp', null, $ohneViewer->konfiguration, $ohneViewer->text, '/lmo.php', $ohneViewer->pfade, $ohneViewer->darstellung, sitzung: $ohneViewer->sitzung)));

        $html = $controller->zeige(self::kontext())->html;
        self::assertStringContainsString('<h1>tester - FC</h1>', $html);
        self::assertStringContainsString('T262', $html, 'keine Spiele im Zeitraum');
        self::assertStringContainsString('viewermode=1&amp;start=-8', $html);
        self::assertStringNotContainsString('name="best"', $html, 'nichts zu speichern');

        self::assertStringNotContainsString('<form', $controller->zeige(self::kontext(tipp: ['viewertipp' => '0']))->html, 'abgeschaltet');
    }
}
