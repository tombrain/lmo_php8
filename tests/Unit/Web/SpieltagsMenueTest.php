<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Seiten\SpieltagsMenue;
use PHPUnit\Framework\TestCase;

/**
 * Die Auswahl der Spieltage: verlinkte Spieltage, der gewaehlte ohne Link, Pokalrunden mit Namen
 * und Zeilenumbrueche in langen Ligen.
 */
class SpieltagsMenueTest extends TestCase
{
    private static function menue(): SpieltagsMenue
    {
        $text = [];
        for ($i = 0; $i <= 400; $i++) {
            $text[$i] = 'T' . $i;
        }

        return new SpieltagsMenue($text, dirname(__DIR__, 3) . '/lmo/vorlagen');
    }

    /** @param list<string> $optionen */
    private static function liga(array $optionen): Liga
    {
        return Liga::ausEintraegen(LigaDatei::zerlege(array_merge(['[Options]'], $optionen)));
    }

    public function testGewaehlterSpieltagOhneLink(): void
    {
        $html = self::menue()->zeige(self::liga(['Teams=4', 'Rounds=3']), 2, 'L&amp;st=');

        self::assertStringContainsString('<a href="L&amp;st=1" title="T9">01</a>', $html);
        self::assertStringContainsString("<td align='center'>02&nbsp;</td>", $html);
        self::assertStringContainsString('<a href="L&amp;st=3" title="T9">03</a>', $html);
        self::assertStringNotContainsString('st=4', $html);
    }

    public function testImAdminbereichAuchDerGewaehlteMitLink(): void
    {
        $html = self::menue()->zeige(self::liga(['Teams=4', 'Rounds=3']), 2, 'A', true);

        self::assertStringContainsString('<a href="A2" title="T9">02</a>', $html);
    }

    public function testPokalrundenHeissenVomEndeHerFinaleUsw(): void
    {
        $html = self::menue()->zeige(self::liga(['Type=1', 'Teams=32', 'Rounds=5']), 0, 'L');

        self::assertStringContainsString('title="T366">1</a>', $html);
        self::assertStringContainsString('title="T359">T358</a>', $html);
        self::assertStringContainsString('title="T361">T360</a>', $html);
        self::assertStringContainsString('title="T363">T362</a>', $html);
        self::assertStringContainsString('title="T365">T364</a>', $html);
    }

    /** @return array<string, array{int, int}> Spieltage => Zeilen */
    public static function laengen(): array
    {
        return ['18 Spieltage' => [18, 1], '34 Spieltage' => [34, 2], '38 Spieltage' => [38, 3], '48 Spieltage' => [48, 4], '50 Spieltage' => [50, 3]];
    }

    /** @dataProvider laengen */
    public function testLangeLigenInMehrerenZeilen(int $spieltage, int $zeilen): void
    {
        $html = self::menue()->zeige(self::liga(['Teams=4', 'Rounds=' . $spieltage]), 1, 'L');

        self::assertSame($zeilen, substr_count($html, '<tr>'));
    }

    public function testOhneLigaEineLeereZeile(): void
    {
        $html = self::menue()->zeige(null, 1, 'L');

        self::assertStringContainsString('lmoSubmenu', $html);
        self::assertStringNotContainsString('<td', $html);
    }
}
