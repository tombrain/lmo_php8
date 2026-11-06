<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Die Seiten der Ersteinrichtung (lmoadmin.php ohne Admin-Konto): das Formular, abgewiesene
 * Eingaben und die Administration nach dem Anlegen. Die Einzelheiten pruefen FirstRunTest.
 */
final class ErsteinrichtungSeitenTest extends TestCase
{
    use AssertsSnapshots;

    public function testSeiten(): void
    {
        [$app, $runner] = Fixture::uninstalledInstance();
        $c = new AdminClient($app, $runner);
        $einrichten = static fn (string $name, string $kennwort, string $wiederholt): string => $c->post('lmoadmin.php', '', [
            'setup_user' => $name, 'setup_pass' => $kennwort, 'setup_pass2' => $wiederholt, 'setup_submit' => '1',
        ]);
        // die Zeit im Log der abgewiesenen Eingaben laeuft (faketime) und steht nicht fest
        $zeit = static fn (string $aenderungen): string => (string) preg_replace('/^([-+]?)\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\]/m', '$1[ZEIT]', $aenderungen);
        $t = new Transcript();
        $t->add('Formular', $c->get('lmoadmin.php', 'action=admin'), $c->changes());
        $t->add('Kennwort zu kurz, Name mit Sonderzeichen', $einrichten('"<b>x</b>', 'kurz', 'kurz'), $zeit($c->changes()));
        $t->add('Kennwoerter verschieden', $einrichten('admin', 'geheim123', 'anderes123'), $c->changes());
        $t->add('angelegt', $einrichten('admin', 'geheim123', 'geheim123'), (string) preg_replace('/\$argon2id\$\S+|\$2y\$\S+/', '{HASH}', $c->changes()));
        $t->add('danach', $c->get('lmoadmin.php', 'action=admin'), $c->changes());

        // was von der Testinstanz abhaengt: der Stempel der Standardkonfiguration und die Zeiten der Ligadateien
        $text = (string) preg_replace(['/^[0-9a-f]{32}$/m', '/<!--\d+-->\d\d\.\d\d\.\d{4} \d\d:\d\d/'], ['{STEMPEL}', '{DATEIZEIT}'], $t->text());
        $this->assertMatchesSnapshot('ersteinrichtung', 'seiten', $text);
    }
}
