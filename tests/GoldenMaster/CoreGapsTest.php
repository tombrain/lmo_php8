<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Schliesst gezielt Luecken, die die Coverage-Messung im Kern (oeffentlich + Admin) aufgedeckt hat.
 *
 * - index.php: reiner Redirect auf lmo.php mit der Original-Querystring.
 * - lmo-adminopenprogram.php: Spielplan "aus Datei uebernehmen". $xprogram wird dafuer OHNE
 *   Pruefung direkt an fopen() uebergeben (nur der Suffix ".l98" wird verlangt) - das ist ein
 *   eigener, vom extract()-Fund unabhaengiger Befund (Pfad-Handling ohne Whitelist), hier als
 *   Nebenprodukt der Abdeckung dokumentiert statt separat ausgewertet.
 *
 * Bewusst NICHT geschlossen (siehe TESTING.md): lmo-admindir.php ist
 * toter Code (keine einzige Referenz irgendwo im Repository); lmo-adminuserpass.php und
 * lmo-openfiledat.php werden ausschliesslich vom Tippspiel-Addon genutzt, das als eigener,
 * groesserer Block aussteht; lmo-adminrndprogram.php wird separat als Rauchtest gefuehrt
 * (siehe RandomScheduleSmokeTest), weil sein Ergebnis auf echtem Zufall beruht.
 */
final class CoreGapsTest extends TestCase
{
    use AssertsSnapshots;

    public function testIndexRedirectsToLmoPhp(): void
    {
        $fixture = Fixture::get();
        $result = $fixture->runner()->http(['script' => 'index.php', 'query' => 'file=golden/basic.l98&action=table']);
        $body = str_replace($fixture->app()->path(), '{LMO_PATH}', $result['body']);

        $this->assertMatchesSnapshot('core-gaps', 'index_redirect', $body . "\n");
    }

    public function testCreateLeagueFromTemplate(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();

        $t->add('Schritt 1: Formular', $c->get('lmoadmin.php', 'action=admin&todo=new'), $c->changes());
        $t->add('Schritt 1: Name, Titel, Typ', $c->submitFromLast('xfile', [
            'xfile' => 'ausvorlage', 'xtitel' => 'Aus Vorlage', 'xtype' => '0',
        ]), $c->changes());
        $t->add('Schritt 2: Teams, Spieltage, Spiele (wie golden/basic)', $c->submitFromLast('xteams', [
            'xteams' => '6', 'xanzst' => '10', 'xanzsp' => '3',
        ]), $c->changes());
        // $xprogram wird roh an fopen() uebergeben (siehe Klassenkommentar); der Pfad ist relativ
        // zu PATH_TO_LMO, da dort hin gechdir() wurde.
        $t->add('Schritt 3: Spielplan aus golden/basic.l98 uebernehmen', $c->submitFromLast('xprogram', [
            'xprogram' => 'ligen/golden/basic.l98',
        ]), $c->changes());
        $t->add('neue Liga bearbeiten (Spielplan sollte aus der Vorlage stammen)', $c->get('lmoadmin.php', 'action=admin&todo=edit&file=ausvorlage.l98&st=1'), $c->changes());
        $t->add('oeffentlicher Spielplan der neuen Liga', $c->get('lmo.php', 'file=ausvorlage.l98&action=program'), $c->changes());

        $this->assertMatchesSnapshot('core-gaps', 'create_league_from_template', $t->text());
    }
}
