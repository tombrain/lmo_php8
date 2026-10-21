<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\Fixture;
use PHPUnit\Framework\TestCase;

/**
 * Ein Addon wird entfernt (sein Ordner geloescht), seine Einstellungen in config/<addon>/ bleiben:
 * Die Administration laeuft ohne Fehler weiter und bietet nichts mehr vom Addon an.
 */
final class AddonEntferntTest extends TestCase
{
    private const LIGA = '1l_2024-25.l98';

    public function testErgebnisseSpeichernOhneTippspiel(): void
    {
        $c = Fixture::loggedInClient();
        $pfad = $c->instance()->path();
        // die Liga wird getippt und die Auswertung laeuft beim Speichern (aktauswert)
        $cfg = $pfad . '/config/tipp/cfg.txt';
        file_put_contents($cfg, preg_replace('/^immeralle=.*$/m', 'immeralle=1', (string) file_get_contents($cfg)));
        $vorher = $c->get('lmoadmin.php', 'action=admin&todo=edit&file=' . self::LIGA . '&st=1');
        self::assertStringContainsString('name="xmtipp', $vorher, 'mit dem Addon: die Spalte zur Tippwertung');

        exec('rm -rf ' . escapeshellarg($pfad . '/addon/tipp'));
        $seite = $c->get('lmoadmin.php', 'action=admin&todo=edit&file=' . self::LIGA . '&st=1');
        $gespeichert = $c->submitForm('lmoadmin.php', 'action=admin&todo=edit&file=' . self::LIGA . '&st=1', 'xgoala0', ['xgoala0' => '2', 'xgoalb0' => '1']);

        self::assertStringNotContainsString('STDERR', $seite);
        self::assertStringNotContainsString('name="xmtipp', $seite, 'ohne das Addon keine Tippwertung');
        self::assertStringNotContainsString('STDERR', $gespeichert, 'Speichern ohne lmo-tippsavewert.php');
        self::assertStringContainsString('lmoMiddle', $gespeichert, 'die Seite kommt vollstaendig');
    }

    public function testGrundeinstellungenOhneSpielerstatistik(): void
    {
        $c = Fixture::loggedInClient();
        $pfad = $c->instance()->path();
        $grund = 'action=admin&todo=edit&file=golden/basic.l98&st=-1&show=2';  // Reiter Anzeige
        self::assertStringContainsString('name="xmittore"', $c->get('lmoadmin.php', $grund), 'mit dem Addon');

        exec('rm -rf ' . escapeshellarg($pfad . '/addon/spieler'));
        $seite = $c->get('lmoadmin.php', $grund);

        self::assertStringNotContainsString('STDERR', $seite);
        self::assertStringNotContainsString('name="xmittore"', $seite, 'ohne das Addon keine Spieler-Option');
        self::assertFileExists($pfad . '/config/spieler/cfg.txt', 'die Einstellungen bleiben');
    }

    public function testStartseiteSpielerstatistikOhneAddon(): void
    {
        $c = Fixture::loggedInClient();
        $pfad = $c->instance()->path();
        // Startseite der Liga: Spielerstatistik (OnRun=7)
        $liga = $c->instance()->ligenDir() . '/golden/basic.l98';
        $inhalt = str_replace("\r\n", "\n", (string) file_get_contents($liga));
        $inhalt = preg_match('/^OnRun=/m', $inhalt)
            ? preg_replace('/^OnRun=.*$/m', 'OnRun=7', $inhalt)
            : preg_replace('/^\[Options\]\n/m', "[Options]\nOnRun=7\n", $inhalt, 1);
        file_put_contents($liga, $inhalt);

        exec('rm -rf ' . escapeshellarg($pfad . '/addon/spieler'));
        $start = $c->get('lmo.php', 'file=golden/basic.l98');

        self::assertStringNotContainsString('STDERR', $start);
        // gleich der Tabelle; nur die Links der Sprachwahl tragen die Adresse des Aufrufs mit
        $tabelle = str_replace('&amp;action=table', '', $c->get('lmo.php', 'file=golden/basic.l98&action=table'));
        self::assertSame($tabelle, $start, 'statt einer leeren Seite die Tabelle');
    }
}
