<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Die beiden CSS-Endpunkte. Sie werden von jeder Seite eingebunden, aber nie als
 * Seite aufgerufen, deshalb waren sie bisher ungetestet.
 */
final class StyleTest extends TestCase
{
    use AssertsSnapshots;

    /**
     * @dataProvider endpoints
     */
    public function testStyleSheetIsUnchanged(string $script, string $query): void
    {
        $fixture = Fixture::get();
        $result = $fixture->runner()->http(['script' => $script, 'query' => $query]);
        $css = str_replace($fixture->app()->path(), '{LMO_PATH}', str_replace("\r\n", "\n", $result['body']));

        $this->assertMatchesSnapshot('style', $script . ($query === '' ? '' : '?' . $query), $css . "\n");
    }

    public static function endpoints(): array
    {
        return [
            'lmo-style.php' => ['lmo-style.php', ''],
            'lmo-style-nc.php' => ['lmo-style-nc.php', ''],
            'lmo-style.php (Englisch)' => ['lmo-style.php', 'lmouserlang=English'],
        ];
    }
}
