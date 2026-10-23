<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\GrundeinstellungenController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Grundeinstellungen einer Liga: Reiter, Rechte und das Speichern der Werte eines Reiters.
 */
class GrundeinstellungenControllerTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga & Co\nType=0\nTeams=3\nRounds=4\nMatches=1\nfavTeam=2\nDatF=d.m.Y H:i\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n";

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /**
     * @param array<string, string> $anfrage
     * @param array<string, int> $sitzung
     */
    private function seite(array $anfrage, array $sitzung = ['lmouserok' => 2], string $liga = self::LIGA): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $texte[37] = 'Punkte';
        $texte[38] = 'Tore';
        foreach ([5001, 5002, 5030, 5031, 5032, 5033, 5034, 5035] as $nr) {
            $texte[$nr] = 'T' . $nr;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98', 'st' => '-1']),
            '',
            new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", $liga))), ''),
            new Konfiguration(['defdateformat' => 'd.m.Y H:i']),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['date' => [5 => 'Samstag', 12 => 'Sa']],
            sitzung: new Sitzung($sitzung)
        );
        $controller = new GrundeinstellungenController(function (Liga $liga, string $datei, string $st): array {
            $this->gespeichert[] = [$liga, $datei, $st];

            return ['GESPEICHERT ', $liga];
        }, 'Punkte', 'Tore', 1742040000);
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testAllgemein(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<h1>Liga & Co</h1>', $html);
        self::assertStringContainsString('name="xtitel" size="40" maxlength="60" value="Liga & Co"', $html, 'wie bisher unmaskiert');
        self::assertStringContainsString('name="xnamepkt" size="7" maxlength="60" value="Punkte"', $html, 'ohne eigene Bezeichnung der allgemeine Text');
        self::assertStringContainsString('<option value="2" selected>Beta</option>', $html, 'Lieblingsmannschaft');
        self::assertStringContainsString('<option value="0">___</option>', $html);
        self::assertStringContainsString('show=4&amp;file=a.l98&amp;st=-1">T16</a>', $html, 'Reiter Tabelle fuer eine Liga');
        self::assertSame([], $this->gespeichert);
    }

    public function testHilfsadmin(): void
    {
        $html = $this->seite([], ['lmouserok' => 1]);

        self::assertStringNotContainsString('xtitel', $html, 'den Namen aendern nur volle Rechte');
        self::assertStringNotContainsString('show=2', $html, 'nur der erste Reiter');
        self::assertStringContainsString('name="xfavteam"', $html);

        $this->seite(['save' => '1', 'show' => '0', 'xtitel' => 'Neu', 'xfavteam' => '3'], ['lmouserok' => 1]);
        $liga = $this->gespeichert[0][0];
        self::assertSame(['Liga & Co', '3'], [$liga->optionen['Name'], $liga->optionen['favTeam']], 'nur die Mannschaften');
    }

    public function testAnzeigeSpeichern(): void
    {
        $html = $this->seite(['save' => '1', 'show' => '2', 'xdatfselect' => '', 'xdatf2' => 'Y-m-d', 'xdats' => 'on', 'xergebnis' => 'on', 'xonrun' => '1']);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        $e = $this->gespeichert[0][0]->einstellungen();
        self::assertEquals(['Y-m-d', '1', '0', '1', '0'], [$e->wert('DatF'), $e->wert('DatS'), $e->wert('DatM'), $e->wert('OnRun'), $e->wert('Plan')]);
        self::assertSame('-1', $this->gespeichert[0][2]);
        self::assertStringContainsString('<input type="radio" name="xdatfselect" value="" checked>', $html, 'ein eigenes Format');
        self::assertStringContainsString('<option value="l, d.m. H:i">Samstag, 15.03. ' . date('H:i', 1742040000) . '</option>', $html);
    }

    public function testPunkteSpeichern(): void
    {
        $this->seite(['save' => '1', 'show' => '3', 'xminus' => 'on', 'xdirekt' => 'on', 'xdirektauswaerts' => 'on', 'xpns' => ' 2 ']);
        $liga = $this->gespeichert[0][0];

        self::assertSame(['2', '1', '1', '2'], [$liga->optionen['MinusPoints'], $liga->optionen['Direct'], $liga->optionen['DirectAway'], $liga->optionen['PointsForWin']]);
    }

    public function testEigeneBezeichnungWieDerAllgemeineTextEntfaellt(): void
    {
        $this->seite(['save' => '1', 'show' => '0', 'xnamepkt' => 'Punkte', 'xnametor' => 'Treffer']);
        $liga = $this->gespeichert[0][0];

        self::assertSame(['', 'Treffer'], [$liga->optionen['namePkt'] ?? '', $liga->optionen['nameTor']]);
    }
}
