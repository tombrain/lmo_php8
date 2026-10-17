<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon;

use Lmo\Addon\Spieler\Druckansicht;
use Lmo\Addon\Spieler\Einstellungen;
use Lmo\Addon\Spieler\SpielerAddon;
use Lmo\Addon\Spieler\Statistik;
use Lmo\Addon\Spieler\StatistikController;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Addon "spieler": Statistikdatei, Einstellungen, Seite und Menuepunkt.
 */
class SpielerTest extends TestCase
{
    private const TEXTE = [12 => 'Statistiken', 13 => 'Sortierung', 18 => 'Spieler', 21 => 'Vorsortierung User', 22 => 'Anzeige pro Seite',
        23 => 'Nullwerte einblenden', 24 => 'Extra Sortierspalte', 25 => 'Verein', 32 => 'Spielerlink', 41 => 'Linkbezeichnung', 50 => 'Vereinsweise anzeigen'];
    private const ADDON = ['standard_sortierung' => '0', 'standard_richtung' => '1', 'vereinsweise_anzeigen' => '0', 'anzeige_pro_seite' => '10',
        'nullwerte_anzeigen' => '1', 'extra_sortierspalte' => '1'];

    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-spieler-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/*') ?: []);
        rmdir($this->ordner);
    }

    private function statistik(string $inhalt): string
    {
        file_put_contents($this->ordner . '/x.stat', $inhalt);

        return $this->ordner . '/x.stat';
    }

    public function testStatistikLesen(): void
    {
        $datei = $this->statistik("Name#Verein#Tore#Quote*_*-*\n###Tore/Spiele\nMüller#Eins#5#0.5\nAhrens#Zwei#3#0.75\nZander#Eins#0#0\n");

        $alle = Statistik::lies($datei, '', false, 'Verein');
        self::assertNotNull($alle);
        self::assertSame(['Name', 'Verein', 'Tore', 'Quote'], $alle->spalten, 'ohne Formel-Markierung, Formelzeile uebersprungen');
        self::assertCount(3, $alle->zeilen);
        self::assertSame([0 => true, 1 => true], $alle->textspalten);

        $verein = Statistik::lies($datei, 'Eins', true, 'Verein');
        self::assertSame(['Müller', 'Zander'], array_column($verein?->zeilen ?? [], 0), 'nur die Spieler des Vereins');
        self::assertSame([], Statistik::lies($datei, 'Eins', false, 'Verein')?->zeilen, 'nicht vereinsweise: ein Verein zeigt niemanden');
        self::assertNull(Statistik::lies($this->ordner . '/fehlt.stat', '', false, 'Verein'));
    }

    public function testSortieren(): void
    {
        $statistik = Statistik::lies($this->statistik("Name#Tore\nÖzil#3\nAhrens#10\nZander#0\nbauer#3\n"), '', false, 'Verein');
        self::assertNotNull($statistik);

        self::assertSame(['Ahrens', 'Özil', 'bauer', 'Zander'], array_column($statistik->sortiert(1, true, true), 0), 'Zahlen absteigend, gleiche in der Reihenfolge der Datei');
        self::assertSame(['Zander', 'Özil', 'bauer', 'Ahrens'], array_column($statistik->sortiert(1, false, true), 0));
        self::assertSame(['Ahrens', 'Özil', 'bauer'], array_column($statistik->sortiert(1, true, false), 0), 'ohne Nullwerte');
        self::assertSame(['Ahrens', 'bauer', 'Özil', 'Zander'], array_column($statistik->sortiert(0, false, false), 0), 'Text ohne Akzente und Grossschreibung; Nullwerte nur bei Zahlen');
        self::assertSame(['Zander', 'Özil', 'bauer', 'Ahrens'], array_column($statistik->sortiert(0, true, true), 0));
    }

    public function testEinstellungen(): void
    {
        $ohne = Einstellungen::lies($this->ordner . '/x.cfg', self::ADDON, self::TEXTE);
        self::assertSame([0, 1, false, 10, true, true, 'Spieler'], [$ohne->sortierung, $ohne->richtung, $ohne->vereinsweise, $ohne->proSeite, $ohne->nullwerte, $ohne->extraSortierspalte, $ohne->ligalink]);

        file_put_contents($this->ordner . '/x.cfg', "Vorsortierung User=2\nSortierung=0\nVereinsweise anzeigen=1\nAnzeige pro Seite=5\nNullwerte einblenden=0\nExtra Sortierspalte=0\nUnbekannt=7\nLinkbezeichnung=Torjäger");
        $liga = Einstellungen::lies(Einstellungen::datei($this->ordner, 'archiv/x.l98'), self::ADDON, self::TEXTE);
        self::assertSame([2, 0, true, 5, false, false, 'Torjäger'], [$liga->sortierung, $liga->richtung, $liga->vereinsweise, $liga->proSeite, $liga->nullwerte, $liga->extraSortierspalte, $liga->ligalink]);
    }

    /**
     * @param array<string, string> $parameter
     * @param list<string> $weitere weitere Zeilen der Ligadatei
     */
    private function kontext(string $action, bool $mitToren = true, array $parameter = [], array $weitere = []): Kontext
    {
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(array_merge(['[Options]', 'Teams=2', 'mittore=' . ($mitToren ? '1' : '0'), '[Teams]', '1=Eins', '2=Zwei'], $weitere)));
        $texte = self::TEXTE + [14 => 'Keine Daten', 15 => 'weiter', 16 => 'zurueck', 17 => 'Top', 34 => 'Zum Spieler', 36 => 'Nach', 37 => 'sortieren', 43 => '___',
            47 => 'auf', 48 => 'ab', 51 => 'Alle', 56 => 'Drucken', 57 => 'Alles drucken'];

        return new Kontext(
            new Anfrage($parameter),
            $action,
            new GeladeneLiga('x.l98', $liga, ''),
            new Konfiguration(['einspieler' => '1', 'spieler' => self::ADDON]),
            [46 => 'Homepage', 564 => '&#8599;'],
            '/lmo.php',
            new Pfade($this->ordner, '', 'IMG', '/lmo'),
            new Darstellung(static fn (string $datei, string $name, string $attribute): string => '', null, static fn (string $name, string $attribute): string => '[Bild ' . $name . ']'),
            weitereTexte: ['spieler' => $texte]
        );
    }

    public function testSeite(): void
    {
        $this->statistik("Name#Verein#Tore#Spielerlink\nMüller#Eins#5#https://example.org\nAhrens#Zwei#7#___\n");
        $controller = new StatistikController($this->ordner);

        self::assertTrue($controller->zustaendig($this->kontext('spieler')));
        self::assertFalse($controller->zustaendig($this->kontext('spieler', false)), 'Liga ohne Spielerstatistik');
        self::assertFalse($controller->zustaendig($this->kontext('table')));

        $html = $controller->zeige($this->kontext('spieler', true, ['sort' => '2']))->html;
        self::assertLessThan(strpos($html, 'Müller'), (int) strpos($html, 'Ahrens'), 'nach Toren absteigend');
        self::assertStringContainsString('[Bild Ahrens]', $html, 'Spielerbild');
        self::assertStringContainsString('&nbsp;Müller<a href="https://example.org" title="Zum Spieler">', $html, 'Spielerlink');
        self::assertStringNotContainsString('>Spielerlink<', $html, 'die Linkspalte hat keinen Kopf');
        self::assertStringContainsString('href="/lmo/addon/spieler/lmo-statprint.php?file=x.l98&amp;begin=0&amp;sort=2&amp;direction=1&amp;team="', $html);

        unlink($this->ordner . '/x.stat');
        self::assertStringContainsString('<img src="IMG/wrong.svg" border="0" width="20" alt=""> Keine Daten</p>', $controller->zeige($this->kontext('spieler'))->html);
    }

    public function testVereinsweise(): void
    {
        $this->statistik("Name#Verein#Tore\nMüller#Eins#5\nAhrens#Zwei#7\n");
        file_put_contents($this->ordner . '/x.cfg', 'Vereinsweise anzeigen=1');

        $html = (new StatistikController($this->ordner))->zeige($this->kontext('spieler', true, ['team' => 'Eins']))->html;

        self::assertStringContainsString('<a href="/lmo.php?file=x.l98&amp;action=spieler&amp;begin=0&amp;sort=0&amp;direction=1">Alle</a>', $html);
        self::assertStringNotContainsString('Ahrens', $html, 'nur die Spieler des Vereins');
        self::assertStringNotContainsString('333', $html, 'kein Rest vom Debuggen im Kopf der Vereinsspalte');
    }

    public function testHomepageDesVereins(): void
    {
        $this->statistik("Name#Verein#Tore\nMüller#Eins#5\nAhrens#Zwei#7\n");
        $controller = new StatistikController($this->ordner);
        $mitLinks = ['[Options]', 'urlT=1', '[Team1]', 'URL=https://eins.example/?a=1&b=2'];

        $html = $controller->zeige($this->kontext('spieler', true, [], $mitLinks))->html;
        self::assertStringContainsString('Eins&nbsp;<a href="https://eins.example/?a=1&amp;b=2" target="_blank" title="Homepage"><img border="0" width="11" src="IMG/url.png" alt="&#8599;" title="Homepage"></a></td>', $html, 'Link in der Zelle des Vereins');
        self::assertSame(1, substr_count($html, 'url.png'), 'Zwei hat keine Homepage');
        self::assertStringNotContainsString('url.png', $controller->zeige($this->kontext('spieler', true, [], ['[Options]', 'urlT=0', '[Team1]', 'URL=https://eins.example/']))->html, 'Liga ohne Links (urlT)');
    }

    public function testDruckansicht(): void
    {
        $this->statistik("Name#Verein#Tore#Spielerlink\nMüller#Eins#5#https://example.org\nAhrens#Zwei#7#___\nZander#Eins#1#___\n");
        $einstellungen = Einstellungen::lies($this->ordner . '/x.cfg', ['anzeige_pro_seite' => '1', 'vereinsweise_anzeigen' => '1', 'standard_richtung' => '1', 'nullwerte_anzeigen' => '1'], self::TEXTE);
        $darstellung = new Darstellung(static fn (string $datei, string $name, string $attribute): string => '', null, static fn (string $name, string $attribute): string => '[Bild ' . $name . ']');
        $texte = self::TEXTE + [14 => 'Keine Daten'];
        $druck = fn (array $parameter): string => Druckansicht::html($this->ordner, 'archiv/x.l98', new Anfrage($parameter), $einstellungen, [562 => 'zurueck', 798 => 'de'], $texte, 'IMG', $darstellung);

        $alle = $druck(['sort' => '2']);
        self::assertStringContainsString('<html lang="de">', $alle);
        self::assertSame(3, substr_count($alle, '[Bild '), 'ohne begin alle Spieler, nicht nur eine Seite');
        self::assertLessThan(strpos($alle, 'Müller'), (int) strpos($alle, 'Ahrens'), 'nach Toren absteigend');
        self::assertStringContainsString('<strong>7&nbsp;&nbsp;</strong>', $alle, 'Sortierspalte hervorgehoben');
        self::assertStringNotContainsString('example.org', $alle, 'ohne Spielerlinks');

        $seite = $druck(['sort' => '2', 'begin' => '1']);
        self::assertSame(1, substr_count($seite, '[Bild '), 'mit begin eine Seite');
        self::assertStringContainsString('<strong>2. </strong>', $seite);

        $verein = $druck(['team' => 'Eins & Co']);
        self::assertStringContainsString('Spieler - Eins &amp; Co', $verein, 'Verein in der Ueberschrift maskiert');
        self::assertStringNotContainsString('>Verein<', $verein, 'fuer einen Verein ohne Vereinsspalte');

        unlink($this->ordner . '/x.stat');
        self::assertStringContainsString('> Keine Daten</p>', $druck([]));
    }

    public function testMenuepunkt(): void
    {
        $addon = new SpielerAddon($this->ordner);

        self::assertSame('spieler', $addon->name());
        self::assertCount(1, $addon->controller());
        self::assertSame(['Spielerstatistik' => '<a href="/lmo.php?file=x.l98&amp;action=spieler" title="Statistiken">Spieler</a>&nbsp;&nbsp;'], $addon->rahmenteile($this->kontext('table')));
        self::assertSame(['Spielerstatistik' => 'Spieler&nbsp;&nbsp;'], $addon->rahmenteile($this->kontext('spieler')), 'die gezeigte Seite ohne Link');
        self::assertSame([], $addon->rahmenteile($this->kontext('table', false)));
    }
}
