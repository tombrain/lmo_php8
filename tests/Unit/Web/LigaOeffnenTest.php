<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\LigaOeffnen;
use PHPUnit\Framework\TestCase;

/**
 * Eine Liga zum Bearbeiten laden: Spieltag, Bezeichnungen, Rechte, fehlende Dateien.
 */
class LigaOeffnenTest extends TestCase
{
    private string $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir() . '/lmo-ligaoeffnen-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->ordner);
        file_put_contents($this->ordner . 'a.l98', "[Options]\nName=A\nType=0\nActual=3\nstats=1\nnamePkt=Zaehler\nnameTor=Treffer\n");
        file_put_contents($this->ordner . 'p.l98', "[Options]\nName=P\nType=1\nnamePkt=Zaehler\n");
        file_put_contents($this->ordner . 'leer.l98', '');
        file_put_contents($this->ordner . 'notiz.txt', 'x');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->ordner));
    }

    private function oeffne(string $datei, mixed $st = null, bool $darf = true): LigaOeffnen
    {
        return LigaOeffnen::oeffne($this->ordner, $datei, $st, static fn (string $d): bool => $darf, 'Y', 'IMG', [37 => 'Pkt.', 38 => 'Tore', 224 => 'FEHLT']);
    }

    public function testLiga(): void
    {
        $g = $this->oeffne('a.l98');

        self::assertSame(['', 'a.l98', 'A', '3', '1', date('Y')], [$g->meldung, $g->datei, $g->liga?->optionen['Name'] ?? null, $g->st, $g->stats, $g->stand]);
        self::assertSame(['Zaehler', 'Treffer'], [$g->text[37], $g->text[38]], 'die Bezeichnungen der Liga');
        self::assertSame('-1', $this->oeffne('a.l98', '-1')->st, 'der gewaehlte Spieltag');
    }

    public function testPokal(): void
    {
        $g = $this->oeffne('p.l98');
        self::assertSame(['Pkt.', 1, 0], [$g->text[37], $g->st, $g->stats], 'im Pokal die Sprachtexte; ohne Actual Spieltag 1');
    }

    public function testOhneLiga(): void
    {
        $fehler = '<p class="error"><img src="IMG/wrong.svg" border="0" width="20" alt=""> FEHLT</p>';
        self::assertSame([$fehler, ''], [$this->oeffne('fehlt.l98')->meldung, $this->oeffne('fehlt.l98')->datei]);
        self::assertSame([$fehler, ''], [$this->oeffne('a.l98', null, false)->meldung, $this->oeffne('a.l98', null, false)->datei], 'fremde Liga');
        self::assertSame(['', ''], [$this->oeffne('')->meldung, $this->oeffne('')->datei]);
        self::assertSame(['', 'notiz.txt', null], [$this->oeffne('notiz.txt')->meldung, $this->oeffne('notiz.txt')->datei, $this->oeffne('notiz.txt')->liga], 'keine Ligadatei: bleibt gewaehlt, ohne Liga');
        self::assertSame([$fehler, 'leer.l98', null], [$this->oeffne('leer.l98')->meldung, $this->oeffne('leer.l98')->datei, $this->oeffne('leer.l98')->liga], 'leere Datei');
    }
}
