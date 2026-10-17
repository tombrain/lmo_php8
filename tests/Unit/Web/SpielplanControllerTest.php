<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Addon\Addons;
use Lmo\Addon\MitZusatzUnterSeite;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\SpielplanController;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Der Spielplan einer Mannschaft: drei Mannschaften (B heisst "B & Co"), am 1. Spieltag A - B
 * 2:1, am 2. Spieltag C - A mit Notiz, am 3. Spieltag B - C. Wappen als "[W:Name]".
 */
class SpielplanControllerTest extends TestCase
{
    /**
     * @param array<string, string> $parameter
     * @param list<string> $optionen weitere Zeilen fuer [Options]
     */
    private static function kontext(string $action = 'program', array $parameter = [], array $optionen = [], ?Addons $addons = null): Kontext
    {
        $zeilen = array_merge(['[Options]', 'Teams=3', 'Rounds=3', 'Matches=1'], $optionen, [
            '[Teams]', '1=A', '2=B & Co', '3=C',
            '[Round1]', 'TA1=1', 'TB1=2', 'GA1=2', 'GB1=1',
            '[Round2]', 'TA1=3', 'TB1=1', 'NT1=Regen',
            '[Round3]', 'TA1=2', 'TB1=3',
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

    /** @param array<string, string> $parameter */
    private static function seite(array $parameter = [], array $optionen = []): string
    {
        return (new SpielplanController())->zeige(self::kontext('program', $parameter, $optionen))->html;
    }

    public function testZustaendigNurFuerDenSpielplanEinerLiga(): void
    {
        $c = new SpielplanController();

        self::assertTrue($c->zustaendig(self::kontext()));
        self::assertFalse($c->zustaendig(self::kontext('cross')));
        self::assertFalse($c->zustaendig(self::kontext('program', [], ['Type=1'])), 'Pokal');
        self::assertSame('', $c->zeige(self::kontext('program', [], ['Plan=0']))->html, 'in der Liga abgeschaltet');
    }

    public function testOhneGewaehlteMannschaftDerHinweis(): void
    {
        $html = self::seite();

        self::assertStringContainsString('&nbsp;<br>T24<br>&nbsp;', $html);
        self::assertStringContainsString('<acronym title="T23&nbsp;B &amp; Co"><a href="/lmo.php?action=program&amp;file=x.l98&amp;selteam=2">B &amp; C</a></acronym>', $html);
    }

    public function testSpieleDerGewaehltenMannschaft(): void
    {
        $html = self::seite(['selteam' => '1']);

        self::assertStringNotContainsString('T24', $html);
        self::assertStringContainsString('<acronym title="T23&nbsp;A">A</acronym>', $html, 'die gewaehlte ohne Link');
        self::assertStringContainsString('title="T25">1</a>', $html);
        self::assertStringContainsString('title="T25">2</a>', $html);
        self::assertStringNotContainsString('title="T25">3</a>', $html, 'B - C ohne A');
        self::assertStringContainsString('<strong>A</strong>&nbsp;[W:A]', $html, 'die gewaehlte fett');
        self::assertStringContainsString('[W:B & Co]&nbsp;B &amp; Co', $html);
        self::assertStringContainsString('lmo-st2.svg', $html, 'Hinweis zur Notiz');
        self::assertStringContainsString('T22:&lt;/strong&gt;Regen&lt;br&gt;" class="tooltip-trigger"', $html, 'Umbruch nach der Notiz wie bisher');
    }

    public function testVorgewaehlteMannschaftDerLiga(): void
    {
        self::assertStringContainsString('<acronym title="T23&nbsp;C">C</acronym>', self::seite([], ['selTeam=3']));
    }

    public function testZusatzDerAddonsUnterDerSeite(): void
    {
        $addon = new class implements MitZusatzUnterSeite {
            public function name(): string
            {
                return 'pdf';
            }

            public function zusatzUnterSeite(Kontext $kontext): string
            {
                return '<p>PDF ' . $kontext->anfrage->text('selteam') . '</p>';
            }
        };

        $html = (new SpielplanController())->zeige(self::kontext('program', ['selteam' => '2'], [], new Addons([$addon])))->html;

        self::assertStringEndsWith("</table>\n<p>PDF 2</p>", $html);
    }
}
