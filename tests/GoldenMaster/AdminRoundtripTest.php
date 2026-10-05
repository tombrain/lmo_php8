<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Jedes Admin-Formular wird abgerufen und unveraendert wieder abgeschickt.
 * Dokumentiert, was das Original beim "Speichern ohne Aenderung" schreibt
 * (Normalisierung, zusaetzliche Schluessel, Neuberechnung) und deckt Formulare ab,
 * fuer die es kein eigenes Szenario gibt (Addons, Viewer, Tippspiel, Spieltagsverwaltung).
 */
final class AdminRoundtripTest extends TestCase
{
    use AssertsSnapshots;

    /**
     * @dataProvider forms
     */
    public function testSaveWithoutChanges(string $query, ?string $field): void
    {
        $c = Fixture::loggedInClient();
        $response = $c->submitForm('lmoadmin.php', $query, $field);
        $changes = $c->changes();

        $this->assertMatchesSnapshot(
            'admin-roundtrip',
            $query,
            $response . "\n### DATEIAENDERUNGEN\n" . ($changes === '' ? "(keine)\n" : $changes)
        );
    }

    public static function forms(): array
    {
        $a = 'action=admin';
        $cases = [];
        $add = static function (string $query, ?string $field) use (&$cases): void {
            $cases[$query] = [$query, $field];
        };

        $add($a . '&todo=options', 'xadr');
        $add($a . '&todo=addons', null);
        $add($a . '&todo=design', 'xlmo_main_background1');
        $add($a . '&todo=user&show=0', 'xadmin_name0');
        $add($a . '&todo=vieweroptions', null);
        $add($a . '&todo=tippoptions', null);
        $add($a . '&todo=tippemail', null);
        $add($a . '&todo=tipp', null);

        foreach (['golden/basic.l98', 'golden/special.l98', 'golden/handicap.l98', 'golden/wertung.l98'] as $file) {
            $f = '&file=' . $file;
            $add($a . '&todo=edit' . $f . '&st=1', 'xgoala0');
            $add($a . '&todo=edit' . $f . '&st=-1', 'xtitel');
            $add($a . '&todo=edit' . $f . '&st=-2', 'xteams1');
            $add($a . '&todo=edit' . $f . '&st=-3', 'xanzst');
            $add($a . '&todo=edit' . $f . '&st=-10', null);
            $add($a . '&todo=tabs' . $f . '&st=3', 'xplatz1');
        }
        return $cases;
    }
}
