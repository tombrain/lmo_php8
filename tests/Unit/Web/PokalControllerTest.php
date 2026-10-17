<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Addon\Addons;
use Lmo\Addon\AltesSpielInclude;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\PokalErgebnisseController;
use Lmo\Web\Controller\PokalSpielplanController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Ergebnisse und Spielplan im Pokal: vier Mannschaften (B heisst "B & C"), Halbfinale mit Hin-
 * und Rueckspiel (A - B 2:0 und 1:1, C - D offen), Finale A - C. Wappen als "[W:Name]".
 */
class PokalControllerTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     */
    private static function kontext(string $action, array $parameter = [], array $optionen = [], ?Addons $addons = null): Kontext
    {
        $zeilen = array_merge(['[Options]', 'Type=1', 'Teams=4', 'Rounds=2', 'Actual=1'], $optionen, [
            '[Teams]', '1=A', '2=B & C', '3=C', '4=D',
            '[Team1]', 'URL=https://a.example/',
            '[Round1]', 'MO=2', 'TA1=1', 'TB1=2', 'GA11=2', 'GB11=0', 'GA12=1', 'GB12=1', 'NT12=Regen',
            'TA2=3', 'TB2=4', 'GA21=-1', 'GB21=-1', 'GA22=-1', 'GB22=-1',
            '[Round2]', 'MO=1', 'TA1=1', 'TB1=3', 'GA11=-1', 'GB11=-1',
        ]);
        $text = [];
        for ($i = 0; $i <= 5100; $i++) {
            $text[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage($parameter),
            $action,
            new GeladeneLiga('x.l98', Liga::ausEintraegen(LigaDatei::zerlege($zeilen)), ''),
            new Konfiguration(['defdateformat' => 'd.m.Y']),
            $text,
            '/lmo.php',
            new Pfade('/lmo', dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '[W:' . $name . ']'),
            $addons ?? new Addons()
        );
    }

    public function testZustaendigNurImPokal(): void
    {
        self::assertTrue((new PokalErgebnisseController())->zustaendig(self::kontext('results')));
        self::assertFalse((new PokalErgebnisseController())->zustaendig(self::kontext('program')));
        self::assertTrue((new PokalSpielplanController())->zustaendig(self::kontext('program')));

        $liga = new Kontext(new Anfrage([]), 'results', new GeladeneLiga('l.l98', Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Teams=2'])), ''), new Konfiguration([]), [], '', new Pfade('', '', ''), new Darstellung(static fn (string $d, string $n, string $a): string => ''));
        self::assertFalse((new PokalErgebnisseController())->zustaendig($liga), 'keine Liga');
        self::assertSame('', (new PokalErgebnisseController())->zeige(self::kontext('results', [], ['Ergebnis=0']))->html, 'abgeschaltet');
    }

    public function testErgebnisseDerRunde(): void
    {
        $html = (new PokalErgebnisseController())->zeige(self::kontext('results'))->html;

        self::assertStringContainsString('T373', $html, 'Halbfinale');
        self::assertStringContainsString('class="lmoTurnierSieger nobr" align="right"><a href="/lmo.php?action=program&amp;file=x.l98&amp;selteam=1" title="T269">A</a>&nbsp;[W:A]&nbsp;</td>', $html, 'A hat die Paarung gewonnen');
        self::assertStringContainsString('[W:B & C]&nbsp;<a href="/lmo.php?action=program&amp;file=x.l98&amp;selteam=2" title="T269">B &amp; C</a>', $html);
        self::assertStringContainsString('selteam=4', $html, 'C - D, noch offen');
    }

    public function testZweitesSpielMitGewechseltemHeimrecht(): void
    {
        $html = (new PokalErgebnisseController())->zeige(self::kontext('results'))->html;

        // im Rueckspiel steht B & C links (rechtsbuendig), im Hinspiel rechts
        self::assertSame(1, substr_count($html, 'class="nobr" align="right"><a href="/lmo.php?action=program&amp;file=x.l98&amp;selteam=2"'));
        self::assertStringContainsString('T22:&lt;/strong&gt; Regen', $html);
        self::assertStringContainsString('<td class="nobr" colspan="12">&nbsp;</td>', $html, 'Trennzeile nach Hin- und Rueckspiel');
    }

    public function testFinaleMitSpielUmPlatz3(): void
    {
        $html = (new PokalErgebnisseController())->zeige(self::kontext('results', ['st' => '2'], ['KlFin=1']))->html;

        self::assertStringContainsString('T374', $html, 'Finale');
        self::assertStringContainsString('<th class="nobr" colspan="12">T419</th>', $html, 'Kopfzeile vor dem Finalspiel');
        self::assertStringContainsString('title="T6">T5&nbsp;T6</a>', $html, 'zurueck zum Halbfinale');
        self::assertStringNotContainsString('title="T8"', $html, 'keine Runde danach');
    }

    public function testAddonZwischenBerichtUndNotiz(): void
    {
        $datei = tempnam(sys_get_temp_dir(), 'lmo');
        file_put_contents($datei, '<?php echo "[P$i S$n]";');
        try {
            $addons = new Addons([new AltesSpielInclude('stats', $datei, true)]);
            $html = (new PokalErgebnisseController())->zeige(self::kontext('results', [], [], $addons))->html;
        } finally {
            unlink($datei);
        }

        self::assertStringContainsString('[P0 S0]&nbsp;</td>', $html);
        self::assertStringContainsString('[P0 S1]<span', $html, 'vor der Notiz');
    }

    public function testSpielplanDerGewaehltenMannschaft(): void
    {
        $html = (new PokalSpielplanController())->zeige(self::kontext('program', ['selteam' => '1']))->html;

        self::assertStringContainsString('title="T377">T362</a>', $html, 'Halbfinale');
        self::assertStringContainsString('title="T377">T364</a>', $html, 'Finale');
        self::assertStringContainsString('<strong>A</strong>&nbsp;[W:A] <a href="https://a.example/" target="_blank" title="T46">', $html, 'Vereinslink');
        self::assertStringContainsString('2&nbsp;:&nbsp;0', $html);
        self::assertStringContainsString('<td width="8">|&nbsp;&nbsp;</td>', $html, 'zweites Spiel');
        self::assertStringContainsString('class="lmoTurnierSieger nobr" align="right"><strong>A</strong>', $html);
        self::assertStringContainsString('<td align="left" class="lmoTurnierVerlierer nobr">[W:B & C]&nbsp;B &amp; C', $html);
    }

    public function testSpielplanOhneGewaehlteMannschaft(): void
    {
        $html = (new PokalSpielplanController())->zeige(self::kontext('program'))->html;

        self::assertStringContainsString('&nbsp;<br>T24<br>&nbsp;', $html);
        self::assertStringContainsString('>A</a>', $html, 'linke Auswahl: 1 und 2');
        self::assertStringContainsString('>D</a>', $html, 'rechte Auswahl: 3 und 4');
    }
}
