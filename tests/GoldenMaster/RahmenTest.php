<?php

declare(strict_types=1);

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Instance;
use Lmo\Tests\GoldenMaster\Support\Normalizer;
use Lmo\Tests\GoldenMaster\Support\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Der Rahmen der oeffentlichen Seiten (Titel, Menue, Fusszeile) in den Faellen, die die
 * Seitentests nicht abdecken: der LMO in eine andere Seite eingebunden (lmo-standard.tpl.php
 * statt lmo-standard-komplett.tpl.php), eine eigene Vorlage ueber LMO_TEMPLATE (mit und ohne
 * Platzhalter fuer den Copyright-Hinweis) und eine ligaeigene Vorlage [liga].tpl.php.
 */
final class RahmenTest extends TestCase
{
    use AssertsSnapshots;

    /** @var array{0:Instance,1:Runner}|null */
    private static ?array $instanz = null;

    /**
     * @dataProvider faelle
     */
    public function testRahmenIstUnveraendert(string $skript, string $query): void
    {
        [$app, $runner] = self::instanz();
        $html = $runner->http(['script' => $skript, 'query' => $query])['body'];

        $this->assertMatchesSnapshot('rahmen', $skript . '?' . $query, Normalizer::html($html, $app->path(), $query));
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function faelle(): array
    {
        $faelle = [];
        foreach ([
            ['einbinden.php', ''],
            ['einbinden.php', 'file=golden/basic.l98&action=table'],
            ['einbinden.php', 'file=golden/basic.l98&action=cross'],
            ['eigene-vorlage.php', 'file=golden/basic.l98&action=table'],
            ['ohne-infolink.php', 'file=golden/basic.l98&action=table'],
            ['lmo.php', 'file=golden/kegel.l98&action=table'],
        ] as [$skript, $query]) {
            $faelle[$skript . '?' . $query] = [$skript, $query];
        }

        return $faelle;
    }

    /** @return array{0:Instance,1:Runner} */
    private static function instanz(): array
    {
        if (self::$instanz === null) {
            [$app, $runner] = Fixture::freshInstance();
            $lmo = $app->path();
            $standard = (string) file_get_contents($lmo . '/template/lmo-standard.tpl.php');
            // eingebunden: lmo.php wird von einer anderen Seite geladen
            file_put_contents($lmo . '/einbinden.php', "<?php\nrequire __DIR__ . '/lmo.php';\n");
            // eigene Vorlage mit Copyright-Platzhalter
            file_put_contents($lmo . '/template/eigen.tpl.php', "<div class=\"eigen\"><h2><!--Titel--></h2>\n" . $standard . "\n</div>");
            file_put_contents($lmo . '/eigene-vorlage.php', "<?php\ndefine('LMO_TEMPLATE', 'eigen.tpl.php');\nrequire __DIR__ . '/lmo.php';\n");
            // eigene Vorlage ohne Copyright-Platzhalter: den Hinweis gibt der LMO dann selbst aus
            file_put_contents($lmo . '/template/ohne-infolink.tpl.php', str_replace('<!--Infolink-->', '', $standard));
            file_put_contents($lmo . '/ohne-infolink.php', "<?php\ndefine('LMO_TEMPLATE', 'ohne-infolink.tpl.php');\nrequire __DIR__ . '/lmo.php';\n");
            // ligaeigene Vorlage: gilt nur fuer golden/kegel.l98
            file_put_contents($lmo . '/template/kegel.l98.tpl.php', "<p>Kegelvorlage</p>\n<!--Titel-->|<!--Tabelle-->|<!--Hauptteil-->|<!--Infolink-->");
            self::$instanz = [$app, $runner];
        }

        return self::$instanz;
    }
}
