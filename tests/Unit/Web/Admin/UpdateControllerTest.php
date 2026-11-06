<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\UpdateController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Update-Seite im Admin-Bereich bei einer neueren Version: Der LMO wird per Composer
 * aktualisiert, nicht mehr per Download.
 */
class UpdateControllerTest extends TestCase
{
    private const NEUESTE = ['version' => '4.3.0', 'time' => '2026-11-01', 'php' => '>=8.0', 'changelog' => 'https://example.org/changelog'];

    private static function kontext(int $rolle = 2): Kontext
    {
        $text = [];
        foreach ([20, 80, 314, 590, 591, 592, 593, 594, 595, 596, 597, 598, 599, 600, 601, 602, 603, 604, 605] as $nr) {
            $text[$nr] = 'text' . $nr;
        }
        $text[314] = 'Download';

        return new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'update']),
            '',
            null,
            new Konfiguration([]),
            $text,
            '/lmo/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
    }

    /** @param array{version: string, time: string, php: string, changelog: string}|null $neueste */
    private static function seite(?array $neueste, bool $neuer): string
    {
        $controller = new UpdateController($neueste, $neuer, '4.2.1', 'GPL-2.0-only');
        self::assertTrue($controller->zustaendig(self::kontext()));

        return $controller->zeige(self::kontext())->html;
    }

    public function testNurDerHauptadmin(): void
    {
        self::assertFalse((new UpdateController(null, false, '4.2.1', ''))->zustaendig(self::kontext(1)));
    }

    public function testNeuereVersionVerweistAufComposerStattAufEinenDownload(): void
    {
        $html = self::seite(self::NEUESTE, true);

        self::assertStringContainsString('<code>composer update</code>', $html);
        self::assertStringContainsString('LMO v.4.2.1', $html);
        self::assertStringContainsString('LMO v.4.3.0', $html);
        self::assertStringContainsString('<dd>&gt;=8.0</dd>', $html, 'maskiert');
        self::assertStringContainsString('<dd>GPL-2.0-only</dd>', $html);
        self::assertStringNotContainsString('Download', $html);
        self::assertStringNotContainsString('.zip', $html);
        self::assertStringContainsString('href="https://example.org/changelog"', $html);

        self::assertStringNotContainsString('text600', self::seite(['changelog' => ''] + self::NEUESTE, true), 'ohne Changelog keine Zeile');
    }

    public function testAktuelleVersionOhneHinweis(): void
    {
        $html = self::seite(self::NEUESTE, false);

        self::assertStringNotContainsString('composer update', $html);
        self::assertStringContainsString('<h1>text596</h1>', $html);
        self::assertStringContainsString('<a href="/lmo/lmoadmin.php?action=admin&amp;todo=update">text595</a>', $html, 'erneut pruefen');
        self::assertStringNotContainsString('composer update', self::seite(null, true), 'ohne Angabe von Packagist');
    }
}
