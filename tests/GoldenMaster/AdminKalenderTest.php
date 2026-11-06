<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Der Kalender zur Datumswahl in der Administration (lmo-admincal.php, Pop-up aus den Feldern
 * fuer das Datum): Monate, Blaettern, der heutige Tag.
 */
final class AdminKalenderTest extends TestCase
{
    use AssertsSnapshots;

    public function testMonate(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        foreach ([
            'dieser Monat' => 'abs=lmoedit&feld=xdatum1',
            'Maerz 2025 (Beginn Samstag)' => 'abs=lmoedit&feld=xdatum1&calshow=March 2025',
            'Februar 2024 (Schaltjahr)' => 'abs=lmoedit&feld=xdatum1&calshow=February 2024',
            'Juni 2025 (endet Montag)' => 'abs=lmoedit&feld=xdatum1&calshow=June 2025',
            'ohne Formular' => '',
        ] as $fall => $parameter) {
            $t->add($fall, $c->get('lmo-admincal.php', $parameter), '');
        }

        $this->assertMatchesSnapshot('admin-kalender', 'monate', $t->text());
    }
}
