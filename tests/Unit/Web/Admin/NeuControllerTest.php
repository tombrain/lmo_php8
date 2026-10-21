<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\NeuController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Eine neue Liga anlegen: die vier Schritte, Vorlagen fuer den Spielplan und die neue Liga.
 */
class NeuControllerTest extends TestCase
{
    private string $ordner;

    /** @var list<array{Liga, string}> was gespeichert wurde */
    private array $gespeichert = [];

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminneu-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/ligen', 0777, true);
        file_put_contents($this->ordner . '/ligen/vier.l98', "[Options]\nName=Vierer\nType=0\nTeams=4\nRounds=6\nMatches=2\n\n[Round1]\nTA1=2\nTB1=1\nTA2=4\nTB2=3\n");
        file_put_contents($this->ordner . '/ligen/b.l98', "[Options]\nType=0\nTeams=4\nRounds=6\nMatches=2\n");
        file_put_contents($this->ordner . '/ligen/gross.l98', "[Options]\nName=Gross\nType=0\nTeams=18\nRounds=34\nMatches=9\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/ligen/*') ?: []);
        rmdir($this->ordner . '/ligen');
        rmdir($this->ordner);
    }

    /** @param array<string, string> $anfrage */
    private function seite(array $anfrage, int $rolle = 2): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $kontext = new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'new'] + $anfrage),
            '',
            null,
            new Konfiguration(['dirliga' => 'ligen/', 'defdateformat' => 'd.m.Y H:i']),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new NeuController(
            function (Liga $liga, string $datei): string {
                $this->gespeichert[] = [$liga, $datei];

                return 'GESPEICHERT ';
            },
            static fn (int $von, int $bis): int => $bis
        );
        self::assertSame($rolle == 2, $controller->zustaendig($kontext), 'nur fuer den Hauptadmin');

        return $controller->zeige($kontext)->html;
    }

    public function testSchritt1(): void
    {
        $html = $this->seite([], 1);
        $html = $this->seite([]);

        self::assertStringContainsString('<input type="hidden" name="newpage" value="1">', $html);
        self::assertStringContainsString('ligen/<input class="lmo-formular-input" type="text" name="xfile" size="28" maxlength="28" value="noname"', $html);
        self::assertStringContainsString('value="No Name"', $html);
        self::assertStringContainsString('<option value="0" selected>T176</option>', $html);
        self::assertStringContainsString('T246 1 T259 4', $html);
    }

    public function testVorhandeneDatei(): void
    {
        $html = $this->seite(['newpage' => '1', 'xfile' => 'vier', 'xtitel' => 'X', 'xtype' => '0']);

        self::assertStringStartsWith(Meldung::html('IMG', 'T280', true), $html);
        self::assertStringContainsString('name="newpage" value="1"', $html, 'zurueck zu Schritt 1');
    }

    public function testSchritt2(): void
    {
        $liga = $this->seite(['newpage' => '1', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0']);
        self::assertStringContainsString('name="xteams" size="2" maxlength="2" value="18"', $liga);
        self::assertStringContainsString("lmoimg(\\'ta\\',img1)", $liga);
        self::assertStringContainsString('name="xanzst" size="3" maxlength="3" value="34"', $liga);

        $pokal = $this->seite(['newpage' => '1', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '1', 'xteams' => '']);
        self::assertStringContainsString('<option value="16" selected>16</option>', $pokal, 'leer gesendet: 16');
        self::assertStringContainsString('<option value="24">24</option>', $pokal);
    }

    public function testSchritt3Spielplan(): void
    {
        $html = $this->seite(['newpage' => '2', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2']);

        self::assertStringContainsString('name="xprogram" value="none" onChange', $html, 'wie bisher ohne Vorauswahl (0 ist nicht leer)');
        self::assertStringContainsString('name="xprogram" value="none" checked', $this->seite(['newpage' => '2', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2', 'xprogram' => '']), 'leer gesendet: kein Spielplan');
        self::assertStringContainsString("<input type='radio' name='xprogram' value=\"b.l98\" onChange=\"dolmoedit()\">T5071<br>", $html, 'ohne Namen');
        self::assertStringContainsString('value="vier.l98" onChange="dolmoedit()">Vierer<br>', $html);
        self::assertStringNotContainsString($this->ordner, $html, 'kein Pfad des Servers');
        self::assertStringNotContainsString('Gross', $html, 'nur gleich grosse Ligen');
        self::assertStringContainsString('value="random"', $html, 'jeder gegen jeden passt');

        $ohne = $this->seite(['newpage' => '2', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '5', 'xanzsp' => '2']);
        self::assertStringNotContainsString('value="random"', $ohne);
        self::assertStringContainsString('<strong>[T224]</strong>', $ohne, 'keine passende Liga');
    }

    public function testSchritt3Pokal(): void
    {
        $html = $this->seite(['newpage' => '2', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '1', 'xteams' => '16']);

        self::assertStringContainsString('name="xanzst" value="4"', $html, '16 Mannschaften: 4 Runden');
        self::assertStringContainsString('name="xmod4"', $html);
        self::assertStringNotContainsString('name="xmod5"', $html);
        self::assertStringContainsString('<option value="1" selected>T353</option>', $html);
    }

    public function testAnlegenMitAusgelostemSpielplan(): void
    {
        $html = $this->seite(['newpage' => '3', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2', 'xprogram' => 'random']);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        self::assertStringContainsString('todo=edit&amp;st=-2&amp;file=neu.l98', $html);
        self::assertCount(1, $this->gespeichert);
        [$liga, $datei] = $this->gespeichert[0];
        self::assertSame('neu.l98', $datei);
        self::assertSame(['Neu', '0', '4', '6', '2', 'd.m.Y H:i'], [$liga->optionen['Name'], $liga->optionen['Type'], $liga->optionen['Teams'], $liga->optionen['Rounds'], $liga->optionen['Matches'], $liga->optionen['DatF']]);
        self::assertSame('T281 3', $liga->mannschaft(3)->name);
        self::assertSame('T2823', $liga->mannschaft(3)->kurz);
        self::assertSame(['3', '2'], [$liga->spieltage[1]->partien[1]->heim, $liga->spieltage[1]->partien[1]->gast], 'der ausgeloste Spielplan');
    }

    public function testDateinameBleibtImLigenordner(): void
    {
        foreach (['../../config/boese', 'ordner\\liga.L98', '/tmp/x.l98'] as $eingabe) {
            $this->seite(['newpage' => '3', 'xfile' => $eingabe, 'xtitel' => 'X', 'xtype' => '1', 'xteams' => '2']);
        }
        self::assertSame(['boese.l98', 'liga.l98', 'x.l98'], array_column($this->gespeichert, 1));
        self::assertStringContainsString('value="liga"', $this->seite(['newpage' => '1', 'xfile' => 'a/liga.l98', 'xtitel' => 'X', 'xtype' => '0']), 'auch im Formular');
    }

    public function testAnlegenMitVorlage(): void
    {
        $this->seite(['newpage' => '3', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2', 'xprogram' => 'vier.l98']);
        // nur angebotene Vorlagen: kein beliebiger Pfad, keine Liga anderer Groesse
        $this->seite(['newpage' => '3', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2', 'xprogram' => $this->ordner . '/ligen/vier.l98']);
        $this->seite(['newpage' => '3', 'xfile' => 'neu', 'xtitel' => 'Neu', 'xtype' => '0', 'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2', 'xprogram' => 'gross.l98']);

        $liga = $this->gespeichert[0][0];
        foreach ([1, 2] as $abgelehnt) {
            $partie = $this->gespeichert[$abgelehnt][0]->spieltage[1]->partien[1];
            self::assertSame(['0', '0'], [$partie->heim, $partie->gast], 'ohne Spielplan');
        }
        self::assertSame(['2', '1'], [$liga->spieltage[1]->partien[1]->heim, $liga->spieltage[1]->partien[1]->gast]);
        self::assertSame(['0', '0'], [$liga->spieltage[2]->partien[1]->heim, $liga->spieltage[2]->partien[1]->gast], 'ohne Paarung in der Vorlage 0');
    }
}
