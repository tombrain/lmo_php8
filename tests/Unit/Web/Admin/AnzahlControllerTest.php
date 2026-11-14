<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Partie;
use Lmo\Web\Admin\AnzahlController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Anzahl der Spieltage und Spiele: Rechte, Formular und Speichern mit dem Leeren der Spiele dahinter.
 */
class AnzahlControllerTest extends TestCase
{
    /** 6 Spieltage mit 2 Spielen; dahinter Reste (Spiel 3 am 1. Spieltag, 7. Spieltag) */
    private const LIGA = "[Options]\nName=Liga\nType=0\nTeams=4\nRounds=6\nMatches=2\nActual=5\n\n[Round1]\nTA1=1\nTB1=2\nTA2=3\nTB2=4\nTA3=2\nTB3=1\n\n[Round5]\nTA1=1\nTB1=3\n\n[Round7]\nTA1=4\nTB1=1\n";

    /** @var list<array{Liga, string, string}> was gespeichert wurde */
    private array $gespeichert = [];

    /**
     * @param array<string, string> $anfrage
     * @param array<string, int> $sitzung
     */
    private function kontext(array $anfrage, array $sitzung = ['lmouserok' => 2], bool $mitLiga = true): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $texte[5001] = 'T5001';
        $texte[5002] = 'T5002';

        return new Kontext(
            new Anfrage($anfrage + ['action' => 'admin', 'todo' => 'edit', 'file' => 'a.l98', 'st' => '-3']),
            '',
            $mitLiga ? new GeladeneLiga('a.l98', Liga::ausEintraegen(LigaDatei::zerlege(explode("\n", self::LIGA))), '') : null,
            new Konfiguration([]),
            $texte,
            '/lmoadmin.php',
            new Pfade('/lmo', dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung($sitzung)
        );
    }

    private function controller(): AnzahlController
    {
        return new AnzahlController(function (Liga $liga, string $datei, string $st): array {
            $this->gespeichert[] = [$liga, $datei, $st];

            return ['GESPEICHERT ', $liga];
        });
    }

    public function testRechte(): void
    {
        $controller = $this->controller();

        self::assertTrue($controller->zustaendig($this->kontext([])));
        self::assertTrue($controller->zustaendig($this->kontext([], ['lmouserok' => 1, 'lmouserokerweitert' => 1])), 'erweiterter Hilfsadmin');
        self::assertFalse($controller->zustaendig($this->kontext([], ['lmouserok' => 1])), 'einfacher Hilfsadmin');
        self::assertFalse($controller->zustaendig($this->kontext(['st' => '-2'])));
        self::assertSame('', $controller->zeige($this->kontext([], mitLiga: false))->html, 'ohne Liga nichts');
    }

    public function testFormular(): void
    {
        $html = $this->controller()->zeige($this->kontext([]))->html;

        self::assertStringContainsString('<td class="nobr" align="center" colspan="4">' . Meldung::html('IMG', 'T561', true) . '</td>', $html);
        self::assertStringContainsString('name="xanzst" size="3" maxlength="3" value="6"', $html);
        self::assertStringContainsString('name="xanzsp" size="2" maxlength="2" value="2"', $html);
        self::assertStringContainsString('<input type="hidden" name="st" value="-3">', $html);
        self::assertStringContainsString('<a href="/lmoadmin.php?action=admin&amp;todo=edit&amp;file=a.l98&amp;st=-1">T544</a>', $html);
        self::assertStringContainsString('class="lmoSubmenu"', $html, 'mit der Navigation der Liga');
        self::assertSame([], $this->gespeichert);
    }

    public function testSpeichern(): void
    {
        $html = $this->controller()->zeige($this->kontext(['save' => '1', 'xanzst' => ' 4 ', 'xanzsp' => '1']))->html;

        self::assertStringStartsWith('GESPEICHERT ', $html);
        self::assertStringContainsString('name="xanzst" size="3" maxlength="3" value="4"', $html, 'die neuen Werte');
        [$liga, $datei, $st] = $this->gespeichert[0];
        self::assertSame(['a.l98', '-3'], [$datei, $st]);
        self::assertSame(['4', '1', '4'], [$liga->optionen['Rounds'], $liga->optionen['Matches'], $liga->optionen['Actual']], 'der aktuelle Spieltag hoechstens der letzte');
        // geleert wird, was hinter der bisherigen Anzahl lag: eine vergroesserte Liga beginnt dort leer
        self::assertSame(['1', '1'], [$liga->spieltage[1]->partien[1]->heim, $liga->spieltage[5]->partien[1]->heim], 'innerhalb der bisherigen Anzahl bleibt alles');
        self::assertSame(Partie::leer()->heim, $liga->spieltage[1]->partien[3]->heim, 'hinter dem bisher letzten Spiel leer');
        self::assertSame(Partie::leer()->heim, $liga->spieltage[7]->partien[1]->heim, 'hinter dem bisher letzten Spieltag leer');
    }
}
