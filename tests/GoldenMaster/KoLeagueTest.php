<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * KO-Ligen (Type=1: Pokal/Turnierbaum statt Punktrunde). Eigenes Datenformat (Ergebnisfelder
 * sind "match+durchgang"-kodiert, z. B. "xgoala00" = Spiel 1, 1. Durchgang) und eigener
 * Admin-Assistent (xtype=1 -> Team-AUSWAHL statt Freitext, Spielmodus je Runde statt
 * Spielplan-Optionen). Die Liga wird bewusst ueber den echten Admin-Assistenten erzeugt statt
 * als Fixture-Datei nachgebaut - das Dateiformat ist komplex genug, dass der "richtige" Weg
 * ueber die Anwendung selbst zuverlaessiger ist als eine handgebaute Datei.
 *
 * Abgedeckt: ein 4er-Turnierbaum (Halbfinale + Finale), Einzelspiel je Runde (Standard-Modus).
 * NICHT abgedeckt (eigener, groesserer Block, siehe TESTING.md): Hin-/Rueckspiel und
 * Best-of-3/5/7 (Modus 2/3/5/7), Playoffmode-Varianten (2-2-1 usw.), Spielerstatistik-Addon
 * fuer KO, Tippspiel-Addon fuer KO (lmo-tippeditko.php und Verwandte).
 */
final class KoLeagueTest extends TestCase
{
    use AssertsSnapshots;

    private const A = 'action=admin';

    public function testCreateAndPlayOutBracket(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();

        $t->add('Schritt 1: Name, Titel, Typ=Pokal', $c->submitForm('lmoadmin.php', self::A . '&todo=new', 'xfile', [
            'xfile' => 'pokal', 'xtitel' => 'Pokal', 'xtype' => '1',
        ]), $c->changes());
        $t->add('Schritt 2: 4 Teams (Turnierbaum-Groesse)', $c->submitFromLast('xteams', ['xteams' => '4']), $c->changes());
        $t->add('Schritt 3: Spielmodus je Runde (Standard: Einzelspiel)', $c->submitFromLast('xmod1'), $c->changes());

        $t->add('Mannschaftsnamen eintragen', $c->submitForm('lmoadmin.php', self::A . '&todo=edit&file=pokal.l98&st=-2', 'xteams1', [
            'xteams1' => 'Rot', 'xteams2' => 'Blau', 'xteams3' => 'Gruen', 'xteams4' => 'Gelb',
        ]), $c->changes());

        // Halbfinale: Spiel 0 = Rot-Blau, Spiel 1 = Gruen-Gelb (Feldnamen "x...{Spiel}{Durchgang}",
        // bei Einzelspiel ist der Durchgang immer 0).
        $t->add('Halbfinale eintragen (Runde 1)', $c->submitForm('lmoadmin.php', self::A . '&todo=edit&file=pokal.l98&st=1', 'xgoala00', [
            'xteama0' => '1', 'xteamb0' => '2', 'xgoala00' => '2', 'xgoalb00' => '1',
            'xteama1' => '3', 'xteamb1' => '4', 'xgoala10' => '0', 'xgoalb10' => '3',
        ]), $c->changes());
        $t->add('Halbfinale erneut oeffnen (Sieger-/Verlierer-Markierung pruefen)', $c->get('lmoadmin.php', self::A . '&todo=edit&file=pokal.l98&st=1'), $c->changes());

        // Finale: Sieger der beiden Halbfinals treten an (Rot aus Spiel 0, Gelb aus Spiel 1).
        $t->add('Finale eintragen (Runde 2)', $c->submitForm('lmoadmin.php', self::A . '&todo=edit&file=pokal.l98&st=2', 'xgoala00', [
            'xteama0' => '1', 'xteamb0' => '4', 'xgoala00' => '3', 'xgoalb00' => '2',
        ]), $c->changes());

        $t->add('oeffentlicher Spielplan: Rot (Finalsieger)', $c->get('lmo.php', 'file=pokal.l98&action=program&selteam=1'), $c->changes());
        $t->add('oeffentlicher Spielplan: Blau (im Halbfinale ausgeschieden)', $c->get('lmo.php', 'file=pokal.l98&action=program&selteam=2'), $c->changes());
        $t->add('oeffentliche Ergebnisse Runde 1 (Halbfinale)', $c->get('lmo.php', 'file=pokal.l98&action=results&st=1'), $c->changes());
        $t->add('oeffentliche Ergebnisse Runde 2 (Finale)', $c->get('lmo.php', 'file=pokal.l98&action=results&st=2'), $c->changes());
        $t->add('oeffentlicher Kalender', $c->get('lmo.php', 'file=pokal.l98&action=cal'), $c->changes());

        $this->assertMatchesSnapshot('ko-league', 'create_and_play_out_bracket', $t->text());
    }

    public function testEditRoundWithoutChanges(): void
    {
        $c = Fixture::loggedInClient();
        $c->submitForm('lmoadmin.php', self::A . '&todo=new', 'xfile', ['xfile' => 'pokal2', 'xtitel' => 'Pokal 2', 'xtype' => '1']);
        $c->submitFromLast('xteams', ['xteams' => '4']);
        $c->submitFromLast('xmod1');
        $c->changes(); // Anlegen nicht Teil des Roundtrip-Protokolls

        $t = new Transcript();
        $t->add('Runde 1 unveraendert speichern', $c->submitForm('lmoadmin.php', self::A . '&todo=edit&file=pokal2.l98&st=1', 'xgoala00'), $c->changes());
        $t->add('Mannschaften unveraendert speichern', $c->submitForm('lmoadmin.php', self::A . '&todo=edit&file=pokal2.l98&st=-2', 'xteams1'), $c->changes());

        $this->assertMatchesSnapshot('ko-league', 'edit_round_without_changes', $t->text());
    }
}
