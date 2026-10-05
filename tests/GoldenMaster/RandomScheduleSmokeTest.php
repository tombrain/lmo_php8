<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Rauchtest fuer lmo-adminrndprogram.php: erzeugt einen echten Zufallsspielplan.
 *
 * Bewusst KEIN Golden-Master-Vergleich (das Ergebnis ist nicht deterministisch, siehe
 * CoreGapsTest-Klassenkommentar).
 *
 * BEKANNTER FEHLER IM ORIGINAL (gefunden durch diesen Test): Der Fisher-Yates-Shuffle in
 * lmo-adminrndprogram.php (~Zeile 160) zieht mit `mt_rand(0, $i+1)` statt `mt_rand(0, $i)`.
 * Bei JEDEM Aufruf besteht beim ersten Schleifendurchlauf eine Wahrscheinlichkeit von
 * 1/(Teamzahl+1), dass ein Index ausserhalb des Arrays gezogen wird. Betroffen ist das
 * Einlesen an sich nicht (nur eine Warnung), aber dadurch verschiebt sich ein Array-Schluessel:
 * eine Teamposition wird NULL statt einer Team-Nummer, der erzeugte Spielplan verliert ein Team.
 * Korrektur waere, $i+1 durch $i zu ersetzen - das ist aber eine Verhaltensaenderung und daher
 * nicht Teil der Golden-Master-Tests, sondern ein eigener, zu entscheidender Fix.
 *
 * Dieser Test toleriert NUR diese eine bekannte Warnung (exakter Text inkl. Zeilennummer) und
 * schlaegt bei jeder anderen, unerwarteten PHP-Meldung weiter an. Tritt der bekannte Fehler auf,
 * wird zusaetzlich geprueft, ob der Spielplan dadurch tatsaechlich ein Team verliert (siehe
 * assertKnownShuffleBugIfPresent) - das belegt die reale Auswirkung, nicht nur die Warnung.
 */
final class RandomScheduleSmokeTest extends TestCase
{
    private const KNOWN_WARNING = '<!-- STDERR Warning: Undefined array key 6 in {LMO_PATH}/lmo-adminrndprogram.php on line 163 -->';

    public function testRandomScheduleIsGeneratedWithoutUnexpectedErrors(): void
    {
        $c = Fixture::loggedInClient();

        $c->get('lmoadmin.php', 'action=admin&todo=new');
        $c->submitFromLast('xfile', ['xfile' => 'zufallsliga', 'xtitel' => 'Zufallsliga', 'xtype' => '0']);
        $c->submitFromLast('xteams', ['xteams' => '6', 'xanzst' => '10', 'xanzsp' => '3']);
        $response = $c->submitFromLast('xprogram', ['xprogram' => 'random']);

        $knownBugFired = strpos($response, self::KNOWN_WARNING) !== false;
        $response = str_replace(self::KNOWN_WARNING, '', $response);

        $this->assertStringNotContainsString('STDERR', $response, 'Unerwartete PHP-Warnung/Fehler beim Erzeugen des Zufallsspielplans');
        $this->assertStringNotContainsString('EXIT', $response, 'Abnormaler Abbruch beim Erzeugen des Zufallsspielplans');

        $edit = $c->get('lmoadmin.php', 'action=admin&todo=edit&file=zufallsliga.l98&st=1');
        $this->assertStringNotContainsString('STDERR', $edit);

        $content = (string)file_get_contents($c->instance()->path() . '/ligen/zufallsliga.l98');
        $this->assertSchedulePlausibility($content, $knownBugFired);
    }

    private function assertSchedulePlausibility(string $content, bool $knownBugFired): void
    {
        preg_match_all('/^\[Round(\d+)\]$/m', $content, $roundMatches);
        $this->assertNotEmpty($roundMatches[1], 'Keine Spieltags-Abschnitte in der erzeugten Ligadatei gefunden');

        $sections = preg_split('/^\[Round\d+\]$/m', $content);
        array_shift($sections); // Teil vor dem ersten [RoundN]

        $brokenDays = [];
        foreach ($sections as $index => $section) {
            preg_match_all('/^TA\d+=(\d*)$/m', $section, $home);
            preg_match_all('/^TB\d+=(\d*)$/m', $section, $away);
            $teamsOnThisDay = array_merge($home[1], $away[1]);
            sort($teamsOnThisDay, SORT_STRING);
            if ($teamsOnThisDay !== ['1', '2', '3', '4', '5', '6']) {
                $brokenDays[$index + 1] = $teamsOnThisDay;
            }
        }

        if ($knownBugFired) {
            // Der bekannte Fehler ist aufgetreten: wir erwarten (und dokumentieren) einen
            // kaputten Spielplan, statt den Test daran scheitern zu lassen.
            $this->assertNotEmpty(
                $brokenDays,
                'Die bekannte Zufalls-Warnung trat auf, aber der Spielplan ist dennoch vollstaendig - ' .
                'Annahme ueber die Fehlerwirkung war falsch, bitte Befund pruefen.'
            );
            return;
        }

        $this->assertEmpty(
            $brokenDays,
            "Spielplan unvollstaendig, OHNE dass die bekannte Warnung auftrat (Spieltage: " .
            implode(', ', array_keys($brokenDays)) . ") - das waere ein NEUER Befund."
        );
    }
}
