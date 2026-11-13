<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Addon\Mini;

use Lmo\Addon\Mini\Minitabelle;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Bilder;
use PHPUnit\Framework\TestCase;

/**
 * Die Werte der Mini-Tabelle: Ausschnitt, Tendenz, Stile der Platzgruppen, Lieblingsmannschaft.
 */
class MinitabelleTest extends TestCase
{
    // 4 Mannschaften, 2 Spieltage; nach dem 2. Spieltag: A 6, C 3, D 3, B 0 (C vor D nach Toren)
    private const LIGA = "[Options]\nName=L\nType=0\nTeams=4\nRounds=2\nMatches=2\nActual=2\nChamp=1\nCL=0\nCK=0\nUC=0\nAR=0\nAB=1\nfavTeam=3\n\n"
        . "[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n4=Delta\n\n[Teamk]\n1=A\n2=B\n3=C\n4=D\n\n"
        . "[Round1]\nTA1=1\nTB1=2\nGA1=1\nGB1=0\nTA2=4\nTB2=3\nGA2=1\nGB2=0\n\n[Round2]\nTA1=1\nTB1=4\nGA1=2\nGB1=0\nTA2=3\nTB2=2\nGA2=3\nGB2=0\n";

    /**
     * @param array<string, mixed> $mini
     * @return array{werte: array<string, string>, bloecke: array<string, list<array<string, string>>>}
     */
    private static function werte(array $mini = [], int $ueber = 1, int $unter = 1, ?int $platz = null): array
    {
        $cfg = ['mini' => $mini + ['TendenzPfeilUpImg' => 'hoch.svg', 'TendenzPfeilDownImg' => 'runter.svg', 'TendenzPunktImg' => 'punkt.svg'],
            'lmo_tabelle_background1' => '#ff0', 'lmo_tabelle_color1' => '#000', 'lmo_tabelle_background6' => '#f00', 'lmo_tabelle_color6' => '#fff'];
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", self::LIGA)));

        return (new Minitabelle($cfg, [16 => 'Tabelle', 'mini' => [14 => 'Stand']], new Bilder(sys_get_temp_dir() . '/lmo-keine-bilder', 'IMG'), 'IMG', 'LMO', 'TPL'))
            ->werte($liga, 'unter/l.l98', (int) mktime(12, 0, 0, 3, 1, 2025), $ueber, $unter, $platz);
    }

    /** @param list<array<string, string>> $zeilen */
    private static function spalte(array $zeilen, string $name): string
    {
        return implode(',', array_column($zeilen, $name));
    }

    public function testAusschnittUmDieLieblingsmannschaft(): void
    {
        $w = self::werte();
        self::assertSame('A,C,D', self::spalte($w['bloecke']['Inhalt'], 'Team'), 'Gamma (Platz 2) mit einem Platz davor und danach');
        self::assertSame('C,D,B', self::spalte(self::werte([], 1, 1, 3)['bloecke']['Inhalt'], 'Team'), 'um Platz 3: die Plaetze 2 bis 4');
        self::assertSame('A,C', self::spalte(self::werte([], 1, 0, 1)['bloecke']['Inhalt'], 'Team'), 'am Anfang: ab Platz 1');
    }

    public function testZeile(): void
    {
        $gamma = self::werte()['bloecke']['Inhalt'][1];
        self::assertSame(['<strong>2</strong>', 'Gamma', 'C', '3:1', '+2', '3', '+1'], [$gamma['Platz'], $gamma['TeamLang'], $gamma['Team'], $gamma['PlusTore'] . ':' . $gamma['MinusTore'], $gamma['Tordifferenz'], $gamma['Punkte'], $gamma['Tendenz']]);
        self::assertSame('<img src="IMG/hoch.svg" border="0" title="1">', $gamma['TendenzIMG'], 'vom 3. auf den 2. Platz');
        self::assertStringContainsString('font-weight:bolder;', $gamma['Style'], 'die Lieblingsmannschaft');
    }

    public function testStileDerPlatzgruppen(): void
    {
        $zeilen = self::werte(['stripedTable' => '#eee;#ddd', 'tabelle_classMeister' => 'meister', 'tabelle_classAbsteiger' => 'ab'], 2, 2)['bloecke']['Inhalt'];

        self::assertSame(['background: #ff0 repeat;color: #000;', 'meister'], [$zeilen[0]['Style'], $zeilen[0]['Class']], 'die Farbe des Meisters ersetzt den Streifen');
        self::assertSame('background-color: #ddd;font-weight:bolder;', $zeilen[1]['Style'], 'ohne Platzgruppe der Streifen');
        self::assertSame('background-color: #eee;', $zeilen[2]['Style']);
        self::assertSame(['background: #f00 repeat;color: #fff;', 'ab'], [$zeilen[3]['Style'], $zeilen[3]['Class']], 'Absteiger');
    }

    public function testStreifenVorPlatzfarben(): void
    {
        $zeilen = self::werte(['stripedTable' => '#eee;#ddd', 'stripedVorPlatz' => '1', 'tabelle_classMeister' => 'meister', 'tabelle_classAbsteiger' => 'ab'], 2, 2)['bloecke']['Inhalt'];

        self::assertSame(['background-color: #eee;color: #000;', 'meister'], [$zeilen[0]['Style'], $zeilen[0]['Class']], 'der Streifen bleibt, vom Meister nur Textfarbe und Klasse');
        self::assertSame(['background-color: #ddd;color: #fff;', 'ab'], [$zeilen[3]['Style'], $zeilen[3]['Class']]);
        self::assertSame('background: #ff0 repeat;color: #000;', self::werte(['stripedVorPlatz' => '1'], 2, 2)['bloecke']['Inhalt'][0]['Style'], 'ohne Streifen wie bisher');
    }

    public function testWerteDerVorlage(): void
    {
        $w = self::werte()['werte'];
        self::assertSame(['LMO/?action=table&amp;file=unter/l.l98', 'Tabelle', 'Stand: 01.03.2025', 'TPL/mini/'], [$w['Link'], $w['Tabelle'], $w['ligaDatum'], $w['URL_TO_TEMPLATEDIR']]);
    }
}
