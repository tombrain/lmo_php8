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
        self::assertSame(5 + 6, substr_count($html, '<td class="calat">&nbsp;</td>'), 'vorn bis Samstag leer, hinten nach Montag, dem 31.');
        self::assertStringContainsString('opener.document.forms.lmoedit.xdatum1.value=x;', $html);
        self::assertStringContainsString('href="/lmo/lmo-admincal.php?abs=lmoedit&amp;feld=xdatum1&amp;calshow=February 2025"', $html);
        self::assertStringContainsString('calshow=April 2025"', $html);
    }

    public function testParameterNurAlsBezeichner(): void
    {
        $html = self::html(['abs' => 'x;alert(1)//', 'feld' => '</script><script>alert(2)</script>']);

        self::assertStringNotContainsString('alert', $html);
        self::assertStringContainsString('opener.document.forms...value=x;', $html, 'ohne gueltigen Namen leer');
        self::assertStringContainsString('opener.document.forms.lmoedit.feld_1.value=x;', self::html(['feld' => 'feld_1']));
    }

    /** Jede Woche eine Zeile mit sieben Zellen, auch wenn der Monat am Montag beginnt oder nicht am Sonntag endet */
    public function testZeilen(): void
    {
        // September 2025: beginnt Montag, endet Dienstag; Juni 2025: beginnt Sonntag, endet Montag
        foreach (['September 2025' => 5, 'June 2025' => 6, 'March 2025' => 6] as $monat => $wochen) {
            $html = self::html(['calshow' => $monat]);
            $tage = substr($html, (int) strpos($html, 'class="calwt"'));
            $tage = substr($tage, (int) strpos($tage, '</tr>') + 5, (int) strpos($tage, '</table>') - (int) strpos($tage, '</tr>') - 5);

            self::assertSame($wochen, substr_count($tage, '<tr>'), $monat . ': Zeilen');
            self::assertSame($wochen, substr_count($tage, '</tr>'), $monat . ': geschlossen');
            self::assertSame(7 * $wochen, substr_count($tage, '<td '), $monat . ': volle Wochen');
            self::assertDoesNotMatchRegularExpression('~^\s*</td>\s*$~m', $tage, $monat . ': kein verwaistes </td> (bis 4.5)');
        }
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
