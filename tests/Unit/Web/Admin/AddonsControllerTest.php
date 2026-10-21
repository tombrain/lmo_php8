<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web\Admin;

use Lmo\Web\Admin\AddonsController;
use Lmo\Web\Anfrage;
use Lmo\Web\Darstellung;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Pfade;
use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Einstellungen der Addons: Liste, Felder des gewaehlten Addons und Speichern.
 */
class AddonsControllerTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-adminaddons-' . bin2hex(random_bytes(4));
        mkdir($this->ordner . '/spieler', 0777, true);
        mkdir($this->ordner . '/tipp', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter([$this->ordner . '/cfg.txt', $this->ordner . '/spieler/cfg.txt', $this->ordner . '/tipp/cfg.txt'], 'is_file'));
        rmdir($this->ordner . '/spieler');
        rmdir($this->ordner . '/tipp');
        rmdir($this->ordner);
    }

    /** @param array<string, string> $anfrage */
    private function kontext(array $anfrage, int $rolle = 2): Kontext
    {
        $texte = [];
        for ($i = 0; $i <= 600; $i++) {
            $texte[$i] = 'T' . $i;
        }

        return new Kontext(
            new Anfrage(['action' => 'admin', 'todo' => 'addons'] + $anfrage),
            '',
            null,
            new Konfiguration(['deflang' => 'Deutsch', 'spieler' => ['farbe' => 'rot', 'zeilen' => '10'], 'tipp' => ['zeilen' => '20', 'mail' => '']]),
            $texte,
            '/lmoadmin.php',
            new Pfade($this->ordner, dirname(__DIR__, 4) . '/lmo/vorlagen', 'IMG', '/lmo'),
            new Darstellung(static fn (string $d, string $n, string $a): string => ''),
            sitzung: new Sitzung(['lmouserok' => $rolle])
        );
    }

    public function testAnzeige(): void
    {
        $controller = new AddonsController($this->ordner);
        self::assertFalse($controller->zustaendig($this->kontext([], 1)), 'nur fuer den Hauptadmin');
        self::assertTrue($controller->zustaendig($this->kontext([])));

        $html = $controller->zeige($this->kontext(['show' => '1']))->html;

        self::assertStringContainsString(Meldung::html('IMG', 'T571', true), $html);
        self::assertStringContainsString('class="lmost4"><a href="/lmoadmin.php?action=admin&amp;todo=addons&amp;show=0">spieler</a>', $html);
        self::assertStringContainsString('class="active">tipp</td>', $html);
        self::assertStringContainsString('name="xmail" size="30" value=""', $html);
        self::assertStringNotContainsString('xfarbe', $html, 'nur die Felder des gewaehlten Addons');
        self::assertStringContainsString('<input type="hidden" name="show" value="1">', $html);
        self::assertFileDoesNotExist($this->ordner . '/cfg.txt', 'ohne save nichts geschrieben');
    }

    public function testSpeichern(): void
    {
        $html = (new AddonsController($this->ordner))->zeige($this->kontext(['save' => '1', 'show' => '1', 'xzeilen' => ' 30 ', 'xmail' => 'a@b.de']))->html;

        self::assertStringStartsWith(Meldung::html('IMG', 'T138'), $html);
        self::assertStringContainsString('name="xzeilen" size="30" value="30"', $html, 'mit den neuen Werten');
        self::assertStringEqualsFile($this->ordner . '/cfg.txt', "deflang=Deutsch\n");
        self::assertStringEqualsFile($this->ordner . '/tipp/cfg.txt', "zeilen=30\nmail=a@b.de\n");
        // wie bisher gilt ein Feld fuer jedes Addon mit diesem Schluessel
        self::assertStringEqualsFile($this->ordner . '/spieler/cfg.txt', "farbe=rot\nzeilen=30\n");
    }

    public function testSpeichernFehlgeschlagen(): void
    {
        $html = (new AddonsController($this->ordner . '/gibtsnicht'))->zeige($this->kontext(['save' => '1']))->html;

        self::assertStringStartsWith(Meldung::html('IMG', 'T283', true), $html);
    }
}
