<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Anfrage;
use Lmo\Web\Controller\LigenuebersichtController;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use PHPUnit\Framework\TestCase;

/**
 * Die Ligenuebersicht: drei Ligen im Ligenordner ("Zebra", "Äpfel", eine ohne Namen, ein Pokal),
 * dazu die Unterordner "archiv" (mit Beschreibung) und "Altes".
 */
class LigenuebersichtTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-uebersicht-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen/archiv', 0777, true);
        mkdir($this->ordner . '/ligen/Altes');
        file_put_contents($this->ordner . '/ligen/archiv/dir-descr.txt', 'Saisons vor 2020 & früher');
        file_put_contents($this->ordner . '/ligen/b.l98', "[Options]\nName=Zebra\nActual=12\nTeams=18\n");
        file_put_contents($this->ordner . '/ligen/a.l98', "[Options]\nName=Äpfel\nActual=3\nTeams=10\n");
        file_put_contents($this->ordner . '/ligen/c.l98', "[Options]\nTeams=4\n");
        file_put_contents($this->ordner . '/ligen/p.l98', "[Options]\nName=Pokal\nType=1\nTeams=8\nActual=2\n");
        file_put_contents($this->ordner . '/ligen/archiv/alt.l98', "[Options]\nName=Alt\n");
        touch($this->ordner . '/ligen/a.l98', 1000);
        touch($this->ordner . '/ligen/b.l98', 3000);
        touch($this->ordner . '/ligen/c.l98', 2000);
        touch($this->ordner . '/ligen/p.l98', 4000);
    }

    protected function tearDown(): void
    {
        $loesche = static function (string $pfad) use (&$loesche): void {
            foreach (array_diff(scandir($pfad) ?: [], ['.', '..']) as $eintrag) {
                is_dir($pfad . '/' . $eintrag) ? $loesche($pfad . '/' . $eintrag) : unlink($pfad . '/' . $eintrag);
            }
            rmdir($pfad);
        };
        $loesche($this->ordner);
    }

    /** @param array<string, string> $parameter */
    private function seite(array $parameter = [], bool $angemeldet = false, ?\Closure $darf = null): string
    {
        $text = [];
        for ($i = 0; $i <= 600; $i++) {
            $text[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage($parameter),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'archivlink' => '1', 'liga_sort' => 'liga_name', 'liga_sort_direction' => 'asc', 'defdateformat' => 'd.m.Y']),
            $text,
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => '', static fn (string $datei, string $attribute): string => '[S:' . $datei . ']')
        );

        return (new LigenuebersichtController($angemeldet, $darf ?? static fn (string $datei): bool => true))->zeige($kontext)->html;
    }

    /** @return list<string> Namen der Ligen in der Reihenfolge der Liste */
    private static function namen(string $html): array
    {
        preg_match_all('#file=[^"]*">([^<]*)</a>#', $html, $treffer);

        return $treffer[1];
    }

    public function testZustaendigOhneLiga(): void
    {
        $kontext = static fn (string $action): Kontext => new Kontext(new Anfrage([]), $action, null, new Konfiguration([]), [], '', new Pfade('', '', ''), new Darstellung(static fn (string $d, string $n, string $a): string => ''));
        $c = new LigenuebersichtController(false, static fn (string $datei): bool => true);

        self::assertTrue($c->zustaendig($kontext('')));
        self::assertFalse($c->zustaendig($kontext('tipp')), 'das Tippspiel hat eine eigene Startseite');
    }

    public function testListeNachNameUndUnbenannte(): void
    {
        $html = $this->seite();

        // Akzente ersetzt die Sortierung wie bisher Byte fuer Byte; "Äpfel" landet damit vor "Zebra"
        self::assertSame(['Pokal', 'T507 1', 'Äpfel', 'Zebra'], self::namen($html));
        self::assertStringContainsString('<a href="/lmo.php?todo=&amp;file=b.l98">Zebra</a>', $html);
        self::assertStringContainsString('[S:b.l98]', $html, 'Symbol der Liga');
        self::assertStringContainsString('T2 12 &nbsp;', $html, 'Spieltag');
        self::assertStringContainsString('T373  &nbsp;', $html, 'Pokal mit 8 Mannschaften in Runde 2: Halbfinale ohne Nummer');
        self::assertStringNotContainsString('b.l98 &nbsp;', $html, 'ohne Anmeldung keine Dateinamen');
    }

    public function testSortierungAusDerAnfrage(): void
    {
        self::assertSame(['Äpfel', 'T507 1', 'Zebra', 'Pokal'], self::namen($this->seite(['liga_sort' => 'file_date'])), 'nach Datum');
        self::assertSame(['Äpfel', 'Zebra', 'T507 1', 'Pokal'], self::namen($this->seite(['liga_sort' => 'file_name', 'liga_sort_direction' => 'asc'])));
        self::assertStringContainsString('ligaTable.sort(3,1);</script>', $this->seite(['liga_sort' => 'file_date', 'liga_sort_direction' => 'desc']), 'Spalte im Browser');
    }

    public function testAngemeldetMitDateinamen(): void
    {
        $html = $this->seite([], true);

        self::assertStringContainsString('<td class="nobr" align="left">b.l98 &nbsp;</td>', $html);
        self::assertStringContainsString('"CaseInsensitiveString", "CaseInsensitiveString", "RoundSort"', $html, 'eine Spalte mehr');
    }

    public function testNurErlaubteLigen(): void
    {
        self::assertSame(['Zebra'], self::namen($this->seite([], true, static fn (string $datei): bool => $datei === 'b.l98')), 'Hilfsadmin');
    }

    public function testUnterordner(): void
    {
        $html = $this->seite();

        self::assertMatchesRegularExpression('#subdir=Altes/">Altes</a>.*subdir=archiv/">archiv</a>#s', $html, 'natuerlich sortiert');
        self::assertStringContainsString('<small>Saisons vor 2020 &amp; fr&uuml;her</small>', $html, 'Beschreibung mit htmlentities()');
        self::assertStringNotContainsString('T562', $html, 'oben kein Zurueck');

        $archiv = $this->seite(['subdir' => 'archiv/']);
        self::assertSame(['Alt'], self::namen($archiv));
        self::assertStringContainsString('href="/lmo.php?todo=&amp;file=archiv/alt.l98"', $archiv);
        self::assertStringContainsString('<a href="/lmo.php?subdir=./">T5 T562</a>', $archiv, 'zurueck wie bisher mit dirname()');
        self::assertSame(['Alt'], self::namen($this->seite(['subdir' => '../archiv/'])), '"../" wird entfernt: kein Weg nach oben');
    }

    public function testUnlesbarerOrdner(): void
    {
        $html = $this->seite(['subdir' => 'fehlt/']);

        self::assertStringContainsString('<p class="error"><img src="IMG/wrong.svg" border="0" width="20" alt=""> T224</p>', $html);
        self::assertStringContainsString('<td colspan="5">[T223]</td>', $html);
    }
}
