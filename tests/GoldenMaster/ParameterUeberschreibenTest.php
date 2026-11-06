<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Parameter der Adresse gegen Variablen, die init.php schon gesetzt hat (Konfiguration,
 * Sprachtexte): init.php uebernimmt die Parameter per extract() fuer den alten Code, nur noch
 * als neue Variablen (bis 4.5 ersetzten sie auch Konfiguration und Sprachtexte).
 */
final class ParameterUeberschreibenTest extends TestCase
{
    use AssertsSnapshots;

    public function testSeiten(): void
    {
        $c = Fixture::anonymousClient();
        $t = new Transcript();
        foreach ([
            'Sprachtext aus der Adresse' => 'action=table&file=golden/basic.l98&text[16]=<b>EINGESCHLEUST</b>',
            'Ligenordner aus der Adresse' => 'dirliga=../config/',
            'Sprachwahl' => 'action=table&file=golden/basic.l98&lmouserlang=English',
            'unbekannte Sprache' => 'action=table&file=golden/basic.l98&lmouserlang=../../x',
        ] as $fall => $parameter) {
            $seite = $c->get('lmo.php', $parameter);
            // die Zeit im Log der abgewiesenen Eingaben laeuft (faketime) und steht nicht fest
            $t->add($fall, $seite, (string) preg_replace('/^([-+]?)\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\]/m', '$1[ZEIT]', $c->changes()));
            // bis 4.5 ersetzte ?text[16]=... alle Sprachtexte: die Seite zerfiel mit Warnungen
            self::assertStringNotContainsString('STDERR', $seite, $fall);
        }

        $this->assertMatchesSnapshot('parameter-ueberschreiben', 'seiten', $t->text());
    }
}
