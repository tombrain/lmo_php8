<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Rauchtest fuer lmo-adminrndprogram.php: erzeugt echte Zufallsspielplaene.
 *
 * Bewusst KEIN Golden-Master-Vergleich (das Ergebnis ist nicht deterministisch, siehe
 * CoreGapsTest-Klassenkommentar).
 *
 * Dieser Test hat im Original einen Fehler gefunden: Der Fisher-Yates-Shuffle zog mit
 * `mt_rand(0, $i+1)` statt `mt_rand(0, $i)`. Mit Wahrscheinlichkeit 1/(Teamzahl+1) lag der Index
 * ausserhalb des Arrays, eine Teamposition wurde NULL und der Spielplan verlor ein Team (bei
 * 6 Teams in etwa jedem siebten Aufruf). Weil der Fehler nur manchmal auftrat, werden mehrere
 * Spielplaene erzeugt.
 */
final class RandomScheduleSmokeTest extends TestCase
{
    private const SCHEDULES = 30;

    public function testRandomSchedulesAreCompleteAndWithoutErrors(): void
    {
        $c = Fixture::loggedInClient();

        for ($n = 1; $n <= self::SCHEDULES; $n++) {
            $name = 'zufallsliga' . $n;
            $c->get('lmoadmin.php', 'action=admin&todo=new');
            $c->submitFromLast('xfile', ['xfile' => $name, 'xtitel' => 'Zufallsliga', 'xtype' => '0']);
            $c->submitFromLast('xteams', ['xteams' => '6', 'xanzst' => '10', 'xanzsp' => '3']);
            $response = $c->submitFromLast('xprogram', ['xprogram' => 'random']);

            self::assertStringNotContainsString('STDERR', $response, "PHP-Warnung/Fehler beim Erzeugen von Zufallsspielplan $n");
            self::assertStringNotContainsString('EXIT', $response, "Abnormaler Abbruch beim Erzeugen von Zufallsspielplan $n");

            $content = (string)file_get_contents($c->instance()->path() . '/ligen/' . $name . '.l98');
            self::assertScheduleIsComplete($content, $n);
        }

        $edit = $c->get('lmoadmin.php', 'action=admin&todo=edit&file=zufallsliga1.l98&st=1');
        self::assertStringNotContainsString('STDERR', $edit);
    }

    /** An jedem Spieltag spielt jede der sechs Mannschaften genau einmal. */
    private static function assertScheduleIsComplete(string $content, int $n): void
    {
        $sections = preg_split('/^\[Round\d+\]$/m', $content);
        array_shift($sections); // Teil vor dem ersten [RoundN]
        self::assertCount(10, $sections, "Spieltage in Zufallsspielplan $n");

        foreach ($sections as $index => $section) {
            preg_match_all('/^TA\d+=(\d*)$/m', $section, $home);
            preg_match_all('/^TB\d+=(\d*)$/m', $section, $away);
            $teams = array_merge($home[1], $away[1]);
            sort($teams, SORT_STRING);
            self::assertSame(['1', '2', '3', '4', '5', '6'], $teams, "Zufallsspielplan $n, Spieltag " . ($index + 1));
        }
    }
}
