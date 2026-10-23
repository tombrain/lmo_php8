<?php

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\AnstehendesSpiel;
use PHPUnit\Framework\TestCase;

/**
 * Das Tippspiel sammelt fuer die Erinnerungsmail die anstehenden Spiele mehrerer Ligen
 * (lmo-tippemailviewer.php; die Tippuebersicht prueft TippuebersichtTest). Die Ligadateien liest es
 * komplett als Lmo\Liga\Rohdaten\Liga und greift je Spiel auf dessen eigene Werte zu. Heraus kommt
 * je Spiel ein Lmo\Addon\Tipp\AnstehendesSpiel.
 */
class TippLigaLaderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErinnerungsmailSammeltSpiele(): void
    {
        $testLader = 'lmo-tippemailviewer.php';
        $werte = require __DIR__ . '/fixtures/lade.php';
        /** @var list<AnstehendesSpiel> $spiele */
        $spiele = $werte['lmo_spiele'];

        self::assertSame(2, $werte['anzspiele']);
        self::assertCount(2, $spiele);
        self::assertSame(['tippliga', 'tippliga'], $werte['liga']);
        self::assertSame(['1', '1'], $werte['spieltag']);
        self::assertSame(['1', '2'], $werte['spiel']);
        self::assertSame(['Tippliga', '0', 2, 1], [$spiele[0]->titel, $spiele[0]->typ, $spiele[0]->runden, $spiele[0]->spieleJePaarung]);
        self::assertSame(['Alpha', 'B | C'], [$spiele[0]->heim, $spiele[0]->gast]);
        self::assertSame(['Gamma', 'Alpha'], [$spiele[1]->heim, $spiele[1]->gast]);
        self::assertSame([date('d.m.Y H:i', 1740826800), date('d.m.Y H:i', 1740834000)], $werte['zeit']);
    }
}
