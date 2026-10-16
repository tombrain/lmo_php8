<?php

namespace Lmo\Tests\Unit\Web;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Konfiguration;
use Lmo\Web\LigaLader;
use Lmo\Web\Sprachtexte;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Was der LMO vor jeder Seite laedt: Konfiguration, Sprachtexte und die Liga, jeweils aus einem
 * eigenen Ordner im Temp-Verzeichnis.
 */
class LadenTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-laden-' . bin2hex(random_bytes(4));
        mkdir($this->ordner);
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

    private function datei(string $name, string $inhalt): void
    {
        $pfad = $this->ordner . '/' . $name;
        if (!is_dir(dirname($pfad))) {
            mkdir(dirname($pfad), 0777, true);
        }
        file_put_contents($pfad, $inhalt);
    }

    public function testKonfigurationMitAddons(): void
    {
        $this->datei('cfg.txt', "dirliga=ligen/\ntabonres=2\neinzutore=1\neinspielfrei=0\ndefdateformat=d.m.Y\n");
        $this->datei('tipp/cfg.txt', "tipp_tippmodus=1\n");

        $k = Konfiguration::lies($this->ordner);

        self::assertSame('ligen/', $k->ligenordner());
        self::assertSame(2, $k->tabonres());
        self::assertTrue($k->torstatistikErgebnisse());
        self::assertFalse($k->spielfreiNennen());
        self::assertFalse($k->torstatistikTabelle(), 'fehlt in der Datei');
        self::assertSame('d.m.Y', $k->datumsformat());
        self::assertSame('Deutsch', $k->sprache(), 'Vorgabe');
        self::assertSame(['tipp_tippmodus' => '1'], $k->addon('tipp'));
        self::assertSame([], $k->addon('fehlt'));
    }

    public function testNurAnzeigeoptionenPerAnfrage(): void
    {
        $k = new Konfiguration(['tabonres' => '1', 'tabpkt' => '1', 'dirliga' => 'ligen/', 'einzutore' => '1']);

        $mit = $k->mitAnzeigeoptionen(new Anfrage(['tabonres' => '2', 'tabpkt' => '', 'dirliga' => '../', 'einzutore' => '0']));

        self::assertSame(2, $mit->tabonres());
        self::assertSame(0, $mit->tabpkt(), 'auch leer gesetzt zaehlt');
        self::assertSame('ligen/', $mit->ligenordner(), 'kein Anzeigewert');
        self::assertTrue($mit->torstatistikErgebnisse(), 'kein Anzeigewert');
        self::assertSame(1, $k->tabonres(), 'das Original bleibt');
        self::assertSame(1, $k->mitAnzeigeoptionen(new Anfrage([]))->tabpkt());
    }

    public function testKonfigurationFehlt(): void
    {
        $this->expectException(RuntimeException::class);
        Konfiguration::lies($this->ordner);
    }

    public function testSprachtexteUebereinander(): void
    {
        $this->datei('lang-Deutsch.txt', "1=Eins\n2=Zwei\n3=Drei\nkein Text\n");
        $this->datei('lang-English.txt', "2=Two\n");
        $this->datei('lang-Francais.txt', "3=Trois\n4=\n");
        $this->datei('tipp/lang-English.txt', "1=Tip\n");
        $this->datei('tipp/lang-Francais.txt', "2=Pari\n");

        $text = Sprachtexte::lies($this->ordner, 'English', 'Francais');

        self::assertSame('Eins', $text[1], 'nur deutsch vorhanden');
        self::assertSame('Two', $text[2], 'Standardsprache ueber Deutsch');
        self::assertSame('Trois', $text[3], 'Sprache des Besuchers ueber allem');
        self::assertSame('', $text[4]);
        self::assertSame([1 => 'Tip', 2 => 'Pari'], $text['tipp']);

        self::assertSame('Two', Sprachtexte::lies($this->ordner, 'English')[2], 'ohne Wahl des Besuchers');
    }

    public function testEigeneBezeichnungenDerLiga(): void
    {
        $text = [37 => 'Punkte', 38 => 'Tore'];
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'namePkt=Zaehler', 'nameTor=Kegel']));
        $pokal = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Type=1', 'namePkt=Zaehler']));

        self::assertSame([37 => 'Zaehler', 38 => 'Kegel'], Sprachtexte::fuerLiga($text, $liga));
        self::assertSame($text, Sprachtexte::fuerLiga($text, $pokal), 'im Pokal nicht');
        self::assertSame($text, Sprachtexte::fuerLiga($text, new Liga()));
    }

    public function testLigaLaden(): void
    {
        $this->datei('ligen/a.l98', "[Options]\nName=Liga A\nTeams=2\n");
        $this->datei('ligen/archiv/b.l98', "[Options]\nName=Liga B\n");
        $this->datei('ligen/c.txt', "[Options]\n");
        $this->datei('ligen/leer.l98', '');
        $this->datei('geheim.l98', "[Options]\n");
        touch($this->ordner . '/ligen/a.l98', mktime(12, 30, 0, 5, 17, 2025));

        $lader = new LigaLader($this->ordner . '/ligen/', 'd.m.Y H:i');
        $a = $lader->lade('a.l98');

        self::assertNotNull($a);
        self::assertSame('a.l98', $a->datei);
        self::assertSame('Liga A', $a->liga->optionen['Name']);
        self::assertSame('17.05.2025 12:30', $a->stand);
        self::assertNotNull($lader->lade('archiv/b.l98'), 'Unterordner');
        self::assertNull($lader->lade(''));
        self::assertNull($lader->lade('fehlt.l98'));
        self::assertNull($lader->lade('c.txt'), 'keine .l98-Datei');
        self::assertNull($lader->lade('leer.l98'));
        self::assertNull($lader->lade('../geheim.l98'), 'ausserhalb des Ligenordners');
    }

    public function testLigaNurMitBerechtigung(): void
    {
        $this->datei('ligen/a.l98', "[Options]\n");
        $this->datei('ligen/b.l98', "[Options]\n");
        $lader = new LigaLader($this->ordner . '/ligen/', 'd.m.Y', static fn (string $datei): bool => $datei === 'a.l98');

        self::assertNotNull($lader->lade('a.l98'));
        self::assertNull($lader->lade('b.l98'));
    }
}
