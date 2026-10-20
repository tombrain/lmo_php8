<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\FieberkurveController;
use Lmo\Addon\Tipp\Tipprahmen;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\GeladeneLiga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Fieberkurve des Tippspiels: Auswahl und Adresse des Bildes.
 */
class FieberkurveControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-fieber-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/tipps/auswert', 0777, true);
        file_put_contents($this->ordner . '/tipps/auswert/x.aus', "[zoe]\nSG1=2\nTP1=3\n\n[<anna>]\nSG1=2\nTP1=4\nSG2=1\nTP2=1\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->ordner . '/tipps/auswert/*') ?: []);
        rmdir($this->ordner . '/tipps/auswert');
        rmdir($this->ordner . '/tipps');
        rmdir($this->ordner);
    }

    /**
     * @param array<string, string> $parameter
     * @param array<string, string> $tipp
     */
    private function kontext(array $parameter = [], array $tipp = [], bool $mitLiga = true): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }
        $liga = Liga::ausEintraegen(LigaDatei::zerlege(['[Options]', 'Name=Testliga', 'Teams=2', 'Rounds=2', 'Matches=1', 'Actual=1', '[Teams]', '1=Alpha', '2=Beta']));

        return new Kontext(
            new Anfrage($parameter + ['action' => 'tipp', 'todo' => 'fieber', 'file' => 'x.l98']),
            'tipp',
            $mitLiga ? new GeladeneLiga('x.l98', $liga, '') : null,
            new Konfiguration(['eintippspiel' => '1', 'tipp' => $tipp + [
                'dirtipp' => 'tipps/', 'tippfieber' => '1', 'krit1' => '0', 'krit2' => '1', 'krit3' => '2', 'showzus' => '0', 'showstsiege' => '1', 'regeln' => '0',
            ]]),
            $texte,
            '/lmo.php',
            new Pfade($this->ordner, dirname(__DIR__, 3) . '/lmo/vorlagen', 'IMG', 'http://lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            weitereTexte: ['tipp' => $texte],
            sitzung: new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'zoe', 'lmotipperverein' => ''])
        );
    }

    private function seite(Kontext $kontext): string
    {
        $controller = new FieberkurveController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
        self::assertTrue($controller->zustaendig($kontext));

        return $controller->zeige($kontext)->html;
    }

    public function testZustaendig(): void
    {
        $controller = new FieberkurveController($this->ordner, new Tipprahmen(static fn (string $datei): string => ''));
        self::assertFalse($controller->zustaendig($this->kontext(['todo' => 'wert'])));
        self::assertFalse($controller->zustaendig($this->kontext(['viewermode' => '1'])));
        self::assertFalse($controller->zustaendig($this->kontext(mitLiga: false)));
    }

    public function testOhneAuswahl(): void
    {
        $html = $this->seite($this->kontext());

        self::assertLessThan(strpos($html, '>zoe<') ?: 0, strpos($html, '>&lt;anna&gt;<') ?: PHP_INT_MAX, 'nach Nick sortiert, maskiert');
        self::assertStringContainsString('<option value="-1" selected>___</option>', $html);
        self::assertStringContainsString('<h2>T284</h2>', $html);
    }

    public function testPunkteUndPlaetze(): void
    {
        $punkte = $this->seite($this->kontext(['save' => '1', 'xstat1' => '1', 'xstat2' => '0', 'xkurvenmodus' => '1']));
        self::assertStringContainsString('http://lmo/addon/tipp/lmo-tipppaintgraph.php?pganz=2&amp;pgteam1=&lt;anna&gt;&amp;pgteam2=zoe&amp;max=4&amp;pgst=2&amp;pgplatz1=4,1,0&amp;pgplatz2=3,,0&amp;kmodus=1&amp;pgtext1=T135&amp;pgtext2=T38', $punkte);

        $plaetze = $this->seite($this->kontext(['save' => '1', 'xstat1' => '-1', 'xstat2' => '0', 'xkurvenmodus' => '2']));
        self::assertStringContainsString('?pganz=1&amp;pgteam1=zoe&amp;max=2&amp;pgst=2&amp;pgplatz1=2,,0&amp;kmodus=2&amp;pgtext1=T135&amp;pgtext2=T136', $plaetze, 'nur der zweite gewaehlt');
        self::assertStringContainsString('<option value="2" selected>T232</option>', $plaetze);
    }

    public function testOhneAuswertungUndAbgeschaltet(): void
    {
        self::assertStringNotContainsString('xstat1', $this->seite($this->kontext(tipp: ['tippfieber' => '0'])));

        unlink($this->ordner . '/tipps/auswert/x.aus');
        $html = $this->seite($this->kontext());
        self::assertStringContainsString('T17', $html);
        self::assertStringNotContainsString('T284', $html);
    }
}
