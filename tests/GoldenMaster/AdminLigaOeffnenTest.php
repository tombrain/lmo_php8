<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AdminClient;
use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Eine Liga in der Administration laden (Lmo\Web\LigaOeffnen, bis 4.5 lmo-openfile.php):
 * unbekannte und fremde Dateien, eine Datei ohne .l98 und eigene Bezeichnungen der Liga fuer Punkte
 * und Tore.
 */
final class AdminLigaOeffnenTest extends TestCase
{
    use AssertsSnapshots;

    public function testLaden(): void
    {
        [$app, $runner] = Fixture::freshInstance();
        file_put_contents($app->path() . '/config/lmo-auth.php', "<?php exit(); ?>\nadmin|lmo|2|||\nhilfe|geheim|1|basic|0\n");
        $ligen = $app->ligenDir();
        file_put_contents($ligen . '/notiz.txt', "[Options]\nName=Notiz\n");
        $eigene = (string) file_get_contents($ligen . '/golden/basic.l98');
        file_put_contents($ligen . '/eigene.l98', (string) preg_replace(['/^namePkt=.*$/m', '/^nameTor=.*$/m'], ['namePkt=Zaehler', 'nameTor=Treffer'], str_replace("\r\n", "\n", $eigene)));

        $admin = new AdminClient($app, $runner);
        $admin->login('admin', 'lmo');
        $admin->changes();
        $hilfe = new AdminClient($app, $runner);
        $hilfe->login('hilfe', 'geheim');
        $hilfe->changes();

        $t = new Transcript();
        foreach ([
            'unbekannte Liga' => [$admin, 'action=admin&todo=edit&file=fehlt.l98'],
            'Datei ohne .l98' => [$admin, 'action=admin&todo=edit&file=notiz.txt'],
            'eigene Bezeichnungen (Tabelle)' => [$admin, 'action=admin&todo=edit&file=eigene.l98&st=1'],
            'eigene Bezeichnungen (Grunddaten)' => [$admin, 'action=admin&todo=edit&file=eigene.l98&st=-1'],
            'Hilfsadmin, eigene Liga' => [$hilfe, 'action=admin&todo=edit&file=golden/basic.l98&st=1'],
            'Hilfsadmin, fremde Liga' => [$hilfe, 'action=admin&todo=edit&file=golden/wertung.l98&st=1'],
            'ohne Spieltag: der aktuelle' => [$admin, 'action=admin&todo=edit&file=golden/basic.l98'],
        ] as $fall => [$client, $parameter]) {
            $t->add($fall, $client->get('lmoadmin.php', $parameter), $client->changes());
        }

        $this->assertMatchesSnapshot('admin-liga-oeffnen', 'laden', $t->text());
    }
}
