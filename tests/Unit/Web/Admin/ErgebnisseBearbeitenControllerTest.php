<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Addon\ErgebnisSpalte;
use Lmo\Addon\MitErgebnisSpalten;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Partie;
use Lmo\Liga\Spiel;
use Lmo\Web\Admin\ErgebnisseBearbeitenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Ergebnisse eines Spieltags: Anzeige, Rechte, Speichern, Rueckrunde und die Spalten der Addons.
 */
class ErgebnisseBearbeitenControllerTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga & Co\nType=0\nTeams=3\nRounds=2\nMatches=1\nActual=1\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n\n[Round1]\nTA1=1\nTB1=2\nGA1=2\nGB1=1\nNT1=Regen & Wind\n\n[Round2]\nTA1=2\nTB1=3\n";

    private const POKAL = "[Options]\nName=Pokal\nType=1\nTeams=2\nRounds=1\nMatches=1\n\n[Teams]\n1=Alpha\n2=Beta\n\n[Round1]\nTA1=1\nTB1=2\n";

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /** @var list<list<string>> welche Auswertungen eingebunden wurden */
    private array $ausgewertet = [];

    /**
     * @param array<string, string> $anfrage
     * @param array<string, int> $sitzung
     */
    private function seite(array $anfrage, array $sitzung = ['lmouserok' => 2], string $liga = self::LIGA, Addons $addons = new Addons()): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        foreach ([999, 3000, 3001, 3002, 5001, 5002, 5030, 5031, 5032, 5033, 5034, 5035] as $nr) {
            $texte[$nr] = 'T' . $nr;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98']),
            '',
            new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), ''),
            new Konfiguration(['deftime' => '15:30']),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            $addons,
            sitzung: new Sitzung($sitzung)
        );
        $controller = new ErgebnisseBearbeitenController(
            function (Liga $liga, string $datei, string $st): array {
                $this->gespeichert[] = [$liga, $datei, $st];

                return ['GESPEICHERT ', $liga];
            },
            function (Liga $liga, string $datei, string $st, array $dateien): array {
                $this->ausgewertet[] = $dateien;

                return ['AUSGEWERTET ', $liga];
            }
        );
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    private static function spalten(): Addons
    {
        return new Addons([new class () implements MitErgebnisSpalten {
            public function name(): string
            {
                return 'demo';
            }

            public function ergebnisSpalten(Kontext $kontext): array
            {
                return [new ErgebnisSpalte(
                    'xdemo',
                    'Demo',
                    ['0' => '_', '1' => 'ja'],
                    static fn (Spiel $spiel): string => $spiel->notiz != '' ? '1' : '0',
                    static fn (Partie $partie, string $wert, int $spiel) => $partie->setzeBericht('Demo ' . $wert, $spiel)
                )];
            }
        }]);
    }

    public function testZustaendig(): void
    {
        $kontext = static fn (array $anfrage, int $rolle): Kontext => new Kontext(
            new Anfrage($anfrage + ['todo' => 'edit']),
            '',
            null,
            new Konfiguration([]),
            [],
            '/lmoadmin.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new ErgebnisseBearbeitenController(static fn (Liga $l): array => ['', $l], static fn (Liga $l): array => ['', $l]);

        self::assertTrue($controller->zustaendig($kontext(['st' => '3'], 1)));
        self::assertTrue($controller->zustaendig($kontext([], 2)), 'ohne Spieltag der aktuelle');
        self::assertFalse($controller->zustaendig($kontext(['st' => '-10'], 2)), 'Spieltage zuordnen noch nach alter Art');
        self::assertFalse($controller->zustaendig($kontext(['st' => '1'], 0)));
        self::assertSame('', $controller->zeige($kontext(['st' => '1'], 2))->html, 'ohne Liga leer');
    }

    public function testAnzeige(): void
    {
        $html = $this->seite(['st' => '1']);

        self::assertStringContainsString('<h1>Liga & Co</h1>', $html);
        self::assertStringContainsString('name="lmoedit_second_half"', $html, 'Rueckrunde nur in der Liga');
        self::assertStringContainsString('name="xdatum1"', $html);
        self::assertStringContainsString('<option value="1" selected>Alpha</option>', $html);
        self::assertStringContainsString('name="xgoala0" tabindex="007" size="2" maxlength="4" value="2"', $html);
        self::assertStringContainsString('name="xgoalb0" tabindex="008" size="2" maxlength="4" value="1"', $html);
        self::assertStringContainsString('value="Regen &amp; Wind"', $html, 'Notiz mit htmlentities');
        self::assertStringContainsString("document.getElementById('gT0').style.display='none'", $html, 'ohne Wertung nur "+"');
        self::assertStringNotContainsString("document.getElementById('n0').style.display='none'", $html, 'mit Notiz sichtbar');
        self::assertStringContainsString("document.getElementById('s0').style.display='none'", $html, 'ohne Bericht nur "+"');
        self::assertStringContainsString('<option value="0" selected>T403</option>', $html, 'ohne Speichern "unveraendert" gewaehlt');
        self::assertStringNotContainsString('xmspez', $html, 'ohne Verlaengerung kein Zusatz');
        self::assertSame([], $this->gespeichert);
    }

    public function testUnbekannterSpieltagIstDerAktuelle(): void
    {
        $html = $this->seite(['st' => '-2']);

        self::assertStringContainsString('<input type="hidden" name="st" value="1">', $html);
    }

    public function testHilfsadminOhnePaarungen(): void
    {
        $html = $this->seite(['st' => '1'], ['lmouserok' => 1]);

        self::assertStringNotContainsString('name="xteama0"', $html);
        self::assertStringNotContainsString('name="xdatum1"', $html);
        self::assertStringContainsString('Alpha', $html);

        $this->seite(['st' => '1', 'save' => '1', 'xteama0' => '3', 'xgoala0' => '4', 'xgoalb0' => '0'], ['lmouserok' => 1]);
        $liga = $this->gespeichert[0][0];
        $spiel = Spiel::ausRohdaten($liga, 1, 1);
        self::assertSame([1, 4, 0], [$spiel->heim, $spiel->toreHeim, $spiel->toreGast], 'die Paarung bleibt');
    }

    public function testSpeichern(): void
    {
        $html = $this->seite([
            'st' => '1', 'save' => '1',
            'xteama0' => '3', 'xteamb0' => '1',
            'xgoala0' => '3', 'xgoalb0' => 'x',
            'xatdat0' => '01.02.2025', 'xattim0' => '',
            'xmsieg0' => '0', 'xmnote0' => ' Neu ', 'xmberi0' => '',
            'xstx' => '2', 'xnticker' => '1', 'xnlines' => "erste\r\n\r\nzweite\r\n",
        ]);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        [$liga, $datei, $st] = $this->gespeichert[0];
        self::assertSame(['a.l98', '2'], [$datei, $st], 'mit dem gewaehlten Spieltag als aktuellem');
        $spiel = Spiel::ausRohdaten($liga, 1, 1);
        self::assertSame([3, 1, 3, 0, 'Neu'], [$spiel->heim, $spiel->gast, $spiel->toreHeim, $spiel->toreGast, $spiel->notiz], '"X" heisst 0');
        self::assertSame(mktime(15, 30, 0, 2, 1, 2025), $spiel->anstoss, 'ohne Uhrzeit die Vorgabe');
        self::assertSame(['01.02.2025', '01.02.2025'], [$liga->spieltage[1]->werte['D1'] ?? '', $liga->spieltage[1]->werte['D2'] ?? ''], 'Datum aus den Spielen');
        self::assertSame(['erste', 'zweite'], array_values(array_filter($liga->news, static fn ($k): bool => $k !== 'NC', ARRAY_FILTER_USE_KEY)), 'getrennt an den Zeilenumbruechen, ohne leere');
        self::assertSame('1', $liga->optionen['ticker']);
        self::assertSame([], $this->ausgewertet, 'ohne Addons keine Auswertung');
        self::assertStringContainsString('<option value="2" selected>2. T2</option>', $html, 'nach dem Speichern der aktuelle Spieltag gewaehlt');
    }

    public function testRueckrunde(): void
    {
        $html = $this->seite(['st' => '2', 'save' => '990']);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        self::assertStringContainsString('T3002', $html);
        [$liga, $datei, $st] = $this->gespeichert[0];
        self::assertSame(['a.l98', '0'], [$datei, $st], 'mit dem bisherigen aktuellen Spieltag');
        $spiel = Spiel::ausRohdaten($liga, 2, 1);
        self::assertSame([2, 1, null, null], [$spiel->heim, $spiel->gast, $spiel->toreHeim, $spiel->toreGast], 'Hinspiel mit getauschtem Heimrecht, ohne Ergebnis');
        self::assertStringContainsString('name="xteama0" onChange="dolmoedit()" tabindex="005">', $html);
        self::assertMatchesRegularExpression('~name="xteama0".*?<option value="2" selected>Beta</option>~s', $html, 'angezeigt wird der neue Stand');

        $this->gespeichert = [];
        $html = $this->seite(['st' => '1', 'save' => '990'], liga: str_replace('Rounds=2', 'Rounds=3', self::LIGA));
        self::assertStringContainsString('T3001', $html, 'ungerade Anzahl der Spieltage');
        self::assertSame([], $this->gespeichert);
    }

    public function testRueckrundeMitAuswertungDerAddons(): void
    {
        $addons = new Addons([new class () implements \Lmo\Addon\MitErgebnisAuswertung {
            public function name(): string
            {
                return 'demo';
            }

            public function auswertungBeimSpeichern(Kontext $kontext): array
            {
                return ['/x/demo.php'];
            }
        }]);
        $html = $this->seite(['st' => '1', 'save' => '990'], addons: $addons);

        self::assertStringStartsWith('AUSGEWERTET GESPEICHERT ', $html);
        self::assertSame([['/x/demo.php']], $this->ausgewertet);
    }

    public function testSpaltenDerAddons(): void
    {
        $html = $this->seite(['st' => '1'], addons: self::spalten());

        self::assertStringContainsString('<acronym title="Demo">Demo</acronym>', $html);
        self::assertMatchesRegularExpression('~name="xdemo0" onChange="dolmoedit\(\)" tabindex="013">\s*<option value="0">_</option>\s*<option value="1" selected>ja</option>~', $html, 'in der Liga ohne Titel');

        $this->seite(['st' => '1', 'save' => '1', 'xdemo0' => '1'], addons: self::spalten());
        self::assertSame('Demo 1', Spiel::ausRohdaten($this->gespeichert[0][0], 1, 1)->bericht);
    }

    public function testPokal(): void
    {
        $html = $this->seite(['st' => '1'], liga: self::POKAL, addons: self::spalten());

        self::assertStringNotContainsString('lmoedit_second_half', $html, 'keine Rueckrunde im Pokal');
        self::assertStringContainsString('name="xteama0" onChange="dolmoedit()" title="T107" tabindex="0005"', $html);
        self::assertStringContainsString('name="xgoala00" tabindex="0007" size="4"', $html);
        self::assertStringContainsString('name="xmspez00" onChange="dolmoedit()" title="T111" tabindex="0009"', $html, 'Zusatz immer');
        self::assertStringContainsString('name="xdemo00" onChange="dolmoedit()" title="Demo" tabindex="0012"', $html, 'im Pokal mit Titel');

        $this->seite(['st' => '1', 'save' => '1', 'xteama0' => '1', 'xteamb0' => '2', 'xgoala00' => '1', 'xgoalb00' => '1', 'xmspez00' => 'T1'], liga: self::POKAL);
        $spiel = Spiel::ausRohdaten($this->gespeichert[0][0], 1, 1);
        self::assertSame([1, 1, 1], [$spiel->toreHeim, $spiel->toreGast, $spiel->zusatz], 'i.E.');
    }
}
