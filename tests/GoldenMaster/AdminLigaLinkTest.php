<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Kurze Links in die Administration: lmoadmin.php?file=<liga> und ?todo=open&file=<liga> oeffnen
 * die Liga zum Bearbeiten (lmoadmin.php macht daraus todo=edit), wie
 * ?action=admin&todo=edit&file=<liga>.
 */
final class AdminLigaLinkTest extends TestCase
{
    public function testKurzeLinksOeffnenDieLiga(): void
    {
        $c = Fixture::loggedInClient();
        $bearbeiten = $c->get('lmoadmin.php', 'action=admin&todo=edit&file=golden/basic.l98');
        self::assertStringContainsString('name="xgoala0"', $bearbeiten, 'die Ergebnisse der Liga');

        foreach (['todo=open&file=golden/basic.l98', 'file=golden/basic.l98', 'action=admin&todo=open&file=golden/basic.l98'] as $parameter) {
            self::assertStringContainsString('name="xgoala0"', $c->get('lmoadmin.php', $parameter), $parameter);
        }
        self::assertStringNotContainsString('name="xgoala0"', $c->get('lmoadmin.php', 'todo=open'), 'ohne Liga die Liste zum Oeffnen');
    }
}
