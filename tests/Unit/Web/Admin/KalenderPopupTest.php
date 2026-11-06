<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\KalenderPopup;
use PHPUnit\Framework\TestCase;

/**
 * Der Kalender zur Datumswahl: Monat, Tage, heute, Blaettern.
 */
class KalenderPopupTest extends TestCase
{
    /** @param array<string, string> $anfrage */
    private static function html(array $anfrage): string
    {
        $datum = array_fill(0, 43, '');
        $datum[16] = 'Maerz';
        $datum[18] = 'Mai';
        foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $i => $kurz) {
            $datum[7 + $i] = $kurz;
        }
        $text = [255 => 'Kalender', 347 => 'schliessen', 798 => 'de', 'date' => $datum];

        // heute: Mittwoch, 12. Maerz 2025
        return (new KalenderPopup($text, dirname(__DIR__, 4) . '/lmo/vorlagen'))
            ->html($anfrage + ['abs' => 'lmoedit', 'feld' => 'xdatum1'], '/lmo/lmo-admincal.php', (int) mktime(12, 0, 0, 3, 12, 2025));
    }

    public function testDieserMonat(): void
    {
        $html = self::html([]);

        self::assertStringContainsString('<td align="center" class="caltz">Maerz 2025</td>', $html);
        self::assertSame(31, substr_count($html, "onclick='lmogeben("), 'alle Tage');
        self::assertStringContainsString('class="calht"><a href="#" onclick=\'lmogeben("12.03.2025")\'', $html, 'heute markiert');
        self::assertStringContainsString('class="calwe"><a href="#" onclick=\'lmogeben("01.03.2025")\'', $html, 'Samstag');
        self::assertSame(5, substr_count($html, '<td class="calat">&nbsp;</td>'), 'vorn bis Samstag leer');
        self::assertStringContainsString('opener.document.forms.lmoedit.xdatum1.value=x;', $html);
        self::assertStringContainsString('href="/lmo/lmo-admincal.php?abs=lmoedit&amp;feld=xdatum1&amp;calshow=February 2025"', $html);
        self::assertStringContainsString('calshow=April 2025"', $html);
    }

    public function testAndererMonat(): void
    {
        $html = self::html(['calshow' => 'May 2025']);

        self::assertStringContainsString('class="caltz">Mai 2025</td>', $html);
        self::assertSame(31, substr_count($html, "onclick='lmogeben("));
        self::assertStringNotContainsString('calht', str_replace('td.calht', '', $html), 'heute liegt nicht im Mai');
        self::assertStringContainsString('lmogeben("31.05.2025")', $html);
        self::assertSame(28, substr_count(self::html(['calshow' => 'February 2025']), "onclick='lmogeben("), 'Februar');
    }
}
