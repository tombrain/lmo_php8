<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tippdatei;
use PHPUnit\Framework\TestCase;

/**
 * Die Tippdatei eines Tippers.
 */
class TippdateiTest extends TestCase
{
    private string $pfad;

    protected function setUp(): void
    {
        $this->pfad = sys_get_temp_dir() . '/lmo-tipps-' . bin2hex(random_bytes(4)) . '.tip';
    }

    protected function tearDown(): void
    {
        @unlink($this->pfad);
    }

    public function testLigaLesen(): void
    {
        file_put_contents($this->pfad, "\n[Round1]\n@2@\nGA1=2\nGB1=1\nGA2=-2\nGB2=-1\n\n[Round2]\nGA1=\nGB1=3\n; Kommentar=1\n");
        $datei = Tippdatei::lies($this->pfad);

        self::assertSame(['tipps' => [['2', '1'], ['0', '_'], ['_', '_']], 'joker' => '2'], $datei->runde(1, 3, null), '-2 heisst 0, -1 kein Tipp');
        self::assertSame(['tipps' => [['_', '3'], ['_', '_']], 'joker' => '2'], $datei->runde(2, 2, null), 'ohne @-Zeile gilt der Joker davor (wie bis 4.5)');
        self::assertSame(['tipps' => [['_', '_']], 'joker' => ''], $datei->runde(3, 1, null), 'Spieltag ohne Tipps');
        self::assertSame(['tipps' => [], 'joker' => ''], Tippdatei::lies($this->pfad . '.fehlt')->runde(1, 0, null));
    }

    public function testPokalLesen(): void
    {
        file_put_contents($this->pfad, "[Round1]\n@12@\nGA11=3\nGB11=1\nGA12=-2\nGB22=0\n");

        self::assertSame(
            ['tipps' => [[['3', '1'], ['-2', '_']], [['_', '_'], ['_', '0']]], 'joker' => '12'],
            Tippdatei::lies($this->pfad)->runde(1, 2, 2),
            'im Pokal bleibt -2 stehen'
        );
    }

    public function testSpeichern(): void
    {
        file_put_contents($this->pfad, "\n[Round1]\n@1@\nGA1=1\nGB1=0\n\n[Round2]\nGA1=0\nGB1=0\n");

        Tippdatei::lies($this->pfad)->mitRunde(1, [[2, 2], ['_', '_'], ['', '1']], '3', false)->speichere($this->pfad);

        self::assertSame("\n[Round2]\nGA1=0\nGB1=0\n\n[Round1]\n@3@\nGA1=2\nGB1=2\nGA2=-1\nGB2=-1\nGA3=-1\nGB3=1\n", file_get_contents($this->pfad), 'der getippte Spieltag ans Ende');
        Tippdatei::lies($this->pfad)->mitRunde(3, [[[1, 0], [0, 2]]], null, true)->speichere($this->pfad);
        self::assertStringEndsWith("GB3=1\n\n[Round3]\nGA11=1\nGB11=0\nGA12=0\nGB12=2\n", (string) file_get_contents($this->pfad), 'Pokal, ohne Jokertipp keine @-Zeile');
        Tippdatei::leer()->speichere($this->pfad);
        self::assertSame("\n", file_get_contents($this->pfad), 'eine neue Tippdatei');
    }
}
