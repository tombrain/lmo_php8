<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * Sprachauswahl (getLangSelector) kommt in readdir()-Reihenfolge, die vom Dateisystem abhaengt.
 * Der Normalizer muss daraus eine feste Reihenfolge machen.
 */
final class NormalizerTest extends TestCase
{
    private static function link(string $lang): string
    {
        return '<a href="/lmo/lmo.php?action=table&amp;lmouserlang=' . $lang . '" title="' . $lang . '">'
            . '<img src="http://lmo.test/lmo/img/' . $lang . '.svg" height="16" style="margin-right:1mm" title="'
            . $lang . '" alt="' . $lang . '"></a>';
    }

    private static function selected(string $lang): string
    {
        return '<img src="http://lmo.test/lmo/img/' . $lang . '.selected.svg" height="16" border="0" title="'
            . $lang . '" alt="' . $lang . '">';
    }

    private static function footer(string $entries): string
    {
        return '<td class="lmoFooter" colspan="2" align="left">' . $entries
            . ' >> <a href="/lmo/lang/lmo-admintranslate.php" class="lmo-lang-edit" title="x"></a></td>';
    }

    public function testLanguageOrderDoesNotDependOnDirectoryOrder(): void
    {
        $a = Normalizer::html(self::footer(self::link('Magyar') . self::selected('English') . self::link('Bosanski')), '/tmp/x');
        $b = Normalizer::html(self::footer(self::link('Bosanski') . self::link('Magyar') . self::selected('English')), '/tmp/x');

        $this->assertSame($a, $b);
        preg_match_all('~lmouserlang=(\w+)"|img/(\w+)\.selected\.svg~', $a, $m);
        $order = array_map(static fn (string $x, string $y): string => $x . $y, $m[1], $m[2]);
        $this->assertSame(['Bosanski', 'English', 'Magyar'], $order);
        // Bearbeiten-Link bleibt hinter der Sprachauswahl (kanonisch haengt sein ">>" am letzten Eintrag)
        $this->assertMatchesRegularExpression('~Magyar">\n</a>>>\n<a href="[^"]*lmo-admintranslate~', $a);
    }

    public function testSortingIsIdempotentOnCanonicalForm(): void
    {
        $once = Normalizer::html(self::footer(self::link('Romanian') . self::link('Deutsch') . self::selected('English')), '/tmp/x');

        $this->assertSame($once, Normalizer::sortLangSelector($once));
    }

    public function testOtherContentIsUntouched(): void
    {
        $html = "<p>\n<a href=\"/x?a=1\" title=\"Zeta\">\n<img src=\"z.svg\">\n</a>\n<a href=\"/x?a=2\" title=\"Alpha\">\n<img src=\"a.svg\">\n</a>\n</p>\n";

        $this->assertSame($html, Normalizer::sortLangSelector($html));
    }
}
