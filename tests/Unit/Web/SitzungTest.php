<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Web;

use Lmo\Web\Sitzung;
use PHPUnit\Framework\TestCase;

/**
 * Die Werte der Sitzung.
 */
class SitzungTest extends TestCase
{
    public function testLesenUndSetzen(): void
    {
        $sitzung = new Sitzung(['lmotipperok' => '5', 'lmotippername' => 'tester', 'liste' => ['x']]);

        self::assertSame(5, $sitzung->zahl('lmotipperok'));
        self::assertSame('tester', $sitzung->text('lmotippername'));
        self::assertSame(0, $sitzung->zahl('fehlt'));
        self::assertSame('vorgabe', $sitzung->text('liste', 'vorgabe'), 'kein Text');

        $sitzung->setze('lmotipperok', 0);
        self::assertSame(0, $sitzung->zahl('lmotipperok'));
        self::assertTrue($sitzung->gesetzt('lmotipperok'));
        $sitzung->entferne('lmotipperok');
        self::assertFalse($sitzung->gesetzt('lmotipperok'));
    }

    public function testDieSitzungDesAufrufs(): void
    {
        $vorher = $_SESSION ?? null;
        $_SESSION = ['lmotippername' => 'tester'];
        try {
            $sitzung = Sitzung::ausGlobalen();
            self::assertSame('tester', $sitzung->text('lmotippername'));
            $sitzung->setze('lmotipperverein', 'Eins');
            self::assertSame('Eins', $_SESSION['lmotipperverein'], 'Aenderungen gehen in $_SESSION');
            $_SESSION['lmotipperok'] = 5;
            self::assertSame(5, $sitzung->zahl('lmotipperok'), 'und umgekehrt');
        } finally {
            if ($vorher === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $vorher;
            }
        }
    }
}
