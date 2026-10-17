<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Seiten\Fusszeile;
use Lmo\Web\Seiten\Sprachauswahl;
use PHPUnit\Framework\TestCase;

/**
 * Fusszeile des Rahmens und Auswahl der Sprache.
 */
class FusszeileTest extends TestCase
{
    private static function kontext(?string $datei, string $action = 'table'): Kontext
    {
        return new Kontext(
            new Anfrage([]),
            $action,
            $datei !== null ? new GeladeneLiga($datei, new Liga(), '17.05.2025 12:30') : null,
            new Konfiguration([]),
            [391 => 'Ligen', 392 => 'zur Uebersicht', 406 => 'Stand', 471 => 'Zeit', 799 => 'Sek'],
            '/lmo.php',
            new Pfade('/lmo', '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => '')
        );
    }

    public function testMitLiga(): void
    {
        $teile = Fusszeile::teile(self::kontext('x.l98'), true, 0.12345);

        self::assertSame('<a href="/lmo.php" title="zur Uebersicht">Ligen</a>&nbsp;&nbsp;&nbsp;', $teile['Ligenuebersicht']);
        self::assertSame('Stand:&nbsp;17.05.2025 12:30', $teile['LetzteAuswertung']);
        self::assertSame('Zeit: 0.1235 Sek.<br>', $teile['Berechnungszeit']);
    }

    public function testLigaImUnterordner(): void
    {
        self::assertStringContainsString('href="/lmo.php?subdir=archiv/2024/"', Fusszeile::teile(self::kontext('archiv/2024/x.l98'), true, null)['Ligenuebersicht']);
    }

    public function testOhneLiga(): void
    {
        $teile = Fusszeile::teile(self::kontext(null), true, null);

        self::assertSame(['Ligenuebersicht' => '', 'LetzteAuswertung' => '', 'Berechnungszeit' => ''], $teile);
        self::assertStringContainsString('href="/lmo.php"', Fusszeile::teile(self::kontext(null, 'tipp'), true, null)['Ligenuebersicht'], 'im Tippspiel auch ohne Liga');
        self::assertSame('', Fusszeile::teile(self::kontext('x.l98'), false, null)['Ligenuebersicht'], 'abgeschaltet (backlink)');
    }

    public function testSprachauswahl(): void
    {
        $ordner = sys_get_temp_dir() . '/lmo-sprachen-' . bin2hex(random_bytes(4));
        mkdir($ordner);
        foreach (['lang-English.txt', 'lang-Deutsch.txt', 'liesmich.txt'] as $datei) {
            touch($ordner . '/' . $datei);
        }
        try {
            $html = Sprachauswahl::html($ordner, 'IMG', '/lmo.php', 'file=x.l98&lmouserlang=English&action=table', 'Deutsch');
            $mitBearbeiten = Sprachauswahl::html($ordner, 'IMG', '/lmo.php', '', 'English', ['/lmo/lang/edit.php', 'Texte & mehr']);
            touch($ordner . '/lang.txt');
            $ohneSprache = Sprachauswahl::html($ordner, 'IMG', '/lmo.php', '', 'Deutsch');
        } finally {
            array_map('unlink', glob($ordner . '/*') ?: []);
            rmdir($ordner);
        }

        self::assertStringContainsString('<img src="IMG/Deutsch.selected.svg" height="16" border="0" title="Deutsch" alt="Deutsch">', $html, 'gewaehlt, ohne Link');
        self::assertStringContainsString('<a href="/lmo.php?file=x.l98&amp;action=table&amp;lmouserlang=English" title="English">', $html, 'lmouserlang ersetzt');
        self::assertLessThan(strpos($html, 'English'), strpos($html, 'Deutsch'), 'alphabetisch');
        self::assertStringNotContainsString('liesmich', $html);
        self::assertStringEndsWith(' >> <a href="/lmo/lang/edit.php" class="lmo-lang-edit" title="Texte &amp; mehr"><img src="IMG/pencil.svg" height="20" alt="Edit"></a>', $mitBearbeiten);
        self::assertSame('', $ohneSprache, 'lang.txt ohne Sprache: wie bisher keine Auswahl');
    }
}
