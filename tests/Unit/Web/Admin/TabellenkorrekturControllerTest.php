<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Addon\Addons;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Admin\TabellenkorrekturController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Tabellenkorrektur eines Spieltags: Tabelle, Notizen der Mannschaften und das Speichern der Plaetze.
 */
class TabellenkorrekturControllerTest extends TestCase
{
    private const LIGA = "[Options]\nName=Liga & Co\nType=0\nTeams=3\nRounds=2\nMatches=1\nActual=1\nfavTeam=3\n\n[Teams]\n1=Alpha\n2=Beta\n3=Gamma\n\n[Team2]\nSP=3\nTOR1=-2\nSTDA=2\nNOT=Lizenz\n\n[Round1]\nTA1=1\nTB1=3\nGA1=0\nGB1=2\nHS=030102\n";

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /**
     * @param array<string, string> $anfrage
     * @param array<string, mixed> $konfiguration
     */
    private function seite(array $anfrage, int $rolle = 2, array $konfiguration = ['tabpkt' => '0']): string
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        foreach ([5001, 5002, 5030, 5031, 5032, 5033, 5034, 5035] as $nr) {
            $texte[$nr] = 'T' . $nr;
        }
        $kontext = new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'tabs', 'file' => 'a.l98']),
            '',
            new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", self::LIGA))), ''),
            new Konfiguration($konfiguration),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => $n == 'Beta' ? '<img ' . $d . $a . '>' : ''),
            new Addons(),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new TabellenkorrekturController(
            function (Liga $liga, string $datei, string $st): array {
                $this->gespeichert[] = [$liga, $datei, $st];

                return ['GESPEICHERT ', $liga];
            }
        );
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        $kontext = static fn (string $todo, int $rolle): Kontext => new Kontext(
            new Anfrage(['todo' => $todo]),
            '',
            null,
            new Konfiguration([]),
            [],
            '/lmoadmin.php',
            new Pfade('/lmo', '', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
        $controller = new TabellenkorrekturController(static fn (Liga $l): array => ['', $l]);

        self::assertTrue($controller->zustaendig($kontext('tabs', 1)), 'auch Hilfsadmins');
        self::assertFalse($controller->zustaendig($kontext('tabs', 0)));
        self::assertFalse($controller->zustaendig($kontext('edit', 2)));
        self::assertSame('', $controller->zeige($kontext('tabs', 2))->html, 'ohne Liga leer');
    }

    public function testAnzeige(): void
    {
        $html = $this->seite([]);

        self::assertStringContainsString('<input type="hidden" name="st" value="1">', $html, 'ohne Spieltag der aktuelle');
        self::assertStringContainsString('<td class="nobr"><strong>Gamma</strong></td>', $html, 'Lieblingsmannschaft fett, vorn als Sieger');
        self::assertMatchesRegularExpression('~name="xplatz1".*?<option value="3"  selected>3</option>~s', $html, 'gespeicherte Korrektur: Platz 1 auf 3');
        self::assertStringContainsString("<span class='popup'><img a.l98 alt=\"\"> <strong>Beta</strong><br />\n<br />\n<strong>T128:</strong> -3 (T524 T145 2)<br />\n<strong>T522:</strong> +2:0 (T524 T145 2)<br />\n<br />\n<strong>T22:</strong><br />\nLizenz</span>", $html, 'Strafe negativ, Bonus mit +');
        self::assertSame(1, substr_count($html, "class='popup'"), 'nur mit Notiz, Strafe oder Bonus');
        self::assertStringContainsString('onclick="alert(&quot;Beta\\n\\nT128: -3 (T524 T145 2)\\nT522: +2:0 (T524 T145 2)\\n\\nT22:\\nLizenz&quot;);', $html, 'der Text auch im Alarm');
        self::assertMatchesRegularExpression('~T36</acronym></th>\s*<th>&nbsp;</th>\s*<th style="text-align:right">T37</th>~', $html, 'Punkte vor den Toren (tabpkt=0)');
    }

    public function testPunkteHinten(): void
    {
        $html = $this->seite([], 2, ['tabpkt' => '1']);

        self::assertMatchesRegularExpression('~T39</th>\s*<th>&nbsp;</th>\s*<th style="text-align:right">T37</th>~', $html);
    }

    public function testSpeichern(): void
    {
        $html = $this->seite(['st' => '1', 'save' => '1', 'xplatz1' => '2', 'xplatz2' => '1', 'xplatz3' => ' 3 '], 1);

        self::assertStringStartsWith('GESPEICHERT ', $html);
        [$liga, $datei, $st] = $this->gespeichert[0];
        self::assertSame(['a.l98', '1', '020103'], [$datei, $st, $liga->spieltage[1]->werte['HS'] ?? '']);
        self::assertMatchesRegularExpression('~name="xplatz1".*?<option value="2"  selected>2</option>~s', $html, 'gleich mit dem neuen Stand');

        $this->seite(['st' => '1', 'save' => '1', 'xplatz1' => '1', 'xplatz2' => '2', 'xplatz3' => '3']);
        self::assertSame('0', $this->gespeichert[1][0]->spieltage[1]->werte['HS'] ?? '', 'jede auf ihrem Platz: keine Korrektur');
    }
}
