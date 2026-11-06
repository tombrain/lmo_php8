<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Anfrage;
use PHPUnit\Framework\TestCase;

/**
 * Die Parameter eines Aufrufs.
 */
class AnfrageTest extends TestCase
{
    public function testEinzelneWerte(): void
    {
        $anfrage = new Anfrage(['st' => '5', 'leer' => '', 'null' => '0', 'liste' => ['a']]);

        self::assertSame(5, $anfrage->zahl('st'));
        self::assertSame(7, $anfrage->zahl('leer', 7), 'leer: die Vorgabe');
        self::assertSame('5', $anfrage->text('st'));
        self::assertSame('x', $anfrage->text('liste', 'x'), 'eine Liste ist kein Text');
        self::assertTrue($anfrage->gesetzt('leer'));
        self::assertFalse($anfrage->hat('null'), 'wie empty()');
        self::assertFalse($anfrage->gesetzt('fehlt'));
    }

    public function testMit(): void
    {
        $anfrage = new Anfrage(['todo' => 'open', 'file' => 'a.l98']);
        $neu = $anfrage->mit('todo', 'edit');

        self::assertSame(['edit', 'a.l98'], [$neu->text('todo'), $neu->text('file')]);
        self::assertSame('open', $anfrage->text('todo'), 'die alte bleibt');
    }

    public function testListe(): void
    {
        $anfrage = new Anfrage(['ligen' => ['bula', 2, ['tief'], null], 'text' => 'bula']);

        self::assertSame(['bula', '2'], $anfrage->werte('ligen'), 'nur Texte und Zahlen');
        self::assertSame([], $anfrage->werte('text'), 'ein Text ist keine Liste');
        self::assertSame([], $anfrage->werte('fehlt'));
    }

    public function testUnterordner(): void
    {
        self::assertSame('', Anfrage::unterordner(''));
        self::assertSame('', Anfrage::unterordner('/'));
        self::assertSame('', Anfrage::unterordner('./'), 'dirname() einer Datei ohne Ordner');
        self::assertSame('archiv/', Anfrage::unterordner('archiv'));
        self::assertSame('archiv/2024/', Anfrage::unterordner('archiv/2024/'));
        self::assertSame('archiv/2024/', Anfrage::unterordner('archiv\\2024'), 'Backslash wie /');
    }

    public function testUnterordnerOhneWegHeraus(): void
    {
        // str_replace(['../', './'], '', ...) machte daraus wieder "../"; "...." ist nur ein Ordnername
        self::assertSame('..../', Anfrage::unterordner('...././/'));
        self::assertSame('', Anfrage::unterordner('../../'));
        self::assertSame('etc/', Anfrage::unterordner('/../etc'));
        self::assertSame('archiv/', Anfrage::unterordner('archiv/../..'));
        self::assertSame('...x/', Anfrage::unterordner('...x'), 'nur "." und ".." fallen weg');
        self::assertSame('ab/', Anfrage::unterordner("a\0b"), 'ohne Nullbyte');
    }
}
