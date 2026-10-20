<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\InfoController;
use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Info-Seite im Tippspiel, auch ohne Liga und fuer Gaeste.
 */
class InfoControllerTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param array<string, int|string> $sitzung
     */
    private static function kontext(array $parameter = [], array $sitzung = ['lmotipperok' => '0']): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'info']),
            'tipp',
            null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => ['regeln' => '0']]),
            $texte,
            '/lmo.php',
            new Pfade(sys_get_temp_dir(), dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung($sitzung)
        );
    }

    public function testInfo(): void
    {
        $controller = new InfoController(new Tipprahmen(static fn (string $datei): string => ''), '4.6');

        self::assertFalse($controller->zustaendig(self::kontext(['todo' => 'wert'])));
        self::assertFalse($controller->zustaendig(self::kontext(['viewermode' => '1'])));
        self::assertTrue($controller->zustaendig(self::kontext()), 'ohne Liga');

        $gast = $controller->zeige(self::kontext())->html;
        self::assertStringContainsString('Vers. 4.6', $gast);
        self::assertStringContainsString('<h1>T0&nbsp;', $gast, 'im Rahmen fuer Gaeste');

        $tipper = $controller->zeige(self::kontext(sitzung: ['lmotipperok' => '5', 'lmotippername' => 'tester', 'lmotipperverein' => '']))->html;
        self::assertStringContainsString('Vers. 4.6', $tipper);
        self::assertStringContainsString('todo=logout', $tipper);
    }
}
