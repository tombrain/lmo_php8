<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\AssertsSnapshots;
use Lmo\Tests\GoldenMaster\Support\Fixture;
use Lmo\Tests\GoldenMaster\Support\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Schreibende Admin-Aktionen als angemeldeter Hauptadmin. Jedes Szenario laeuft auf einer
 * frischen Instanz. Das Protokoll enthaelt je Schritt die gesendeten Formularfelder, die
 * Antwort und die Dateiaenderungen (Diff der .l98-Datei bzw. der Konfiguration).
 *
 * Die Formulare werden wie im Browser ausgefuellt (FormSubmitter). Steht im Snapshot
 * "KEIN FORMULAR GEFUNDEN" oder "LEERES FORMULAR", stimmt eine Annahme dieses Tests nicht.
 */
final class AdminWriteTest extends TestCase
{
    use AssertsSnapshots;

    private const A = 'action=admin';
    private const BASIC = '&file=golden/basic.l98';

    public function testEnterResults(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $edit = self::A . '&todo=edit' . self::BASIC . '&st=';

        $t->add('Spieltag 1: Ergebnisse, Termine, Notiz, Bericht', $c->submitForm('lmoadmin.php', $edit . '1', 'xgoala0', [
            'xgoala0' => '3', 'xgoalb0' => '1',
            'xgoala1' => '', 'xgoalb1' => '',
            'xatdat0' => '15.03.2025', 'xattim0' => '18:30',
            'xdatum1' => '14.03.2025', 'xdatum2' => '16.03.2025',
            'xmnote0' => 'Notiz & Test', 'xmberi0' => 'http://example.org/bericht',
        ]), $c->changes());
        $t->add('Spieltag 1 erneut oeffnen', $c->get('lmoadmin.php', $edit . '1'), $c->changes());
        $t->add('Spieltag 2: Wertung und Spiel ohne Ergebnis', $c->submitForm('lmoadmin.php', $edit . '2', 'xgoala0', [
            'xgoala0' => '0', 'xgoalb0' => '2', 'xmsieg0' => '1',
            'xgoala1' => '1', 'xgoalb1' => '1', 'xmsieg1' => '3',
            'xgoala2' => '_', 'xgoalb2' => '_',
        ]), $c->changes());
        $t->add('oeffentliche Tabelle danach', $c->get('lmo.php', 'file=golden/basic.l98&action=table'), $c->changes());
        $t->add('oeffentliche Ergebnisse Spieltag 1', $c->get('lmo.php', 'file=golden/basic.l98&action=results&st=1'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'results', $t->text());
    }

    public function testBasicData(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $q = self::A . '&todo=edit' . self::BASIC . '&st=-1';

        $t->add('Grunddaten speichern', $c->submitForm('lmoadmin.php', $q, 'xtitel', [
            'xtitel' => 'Geaenderter Titel', 'xpns' => '2', 'xpnu' => '1', 'xpnn' => '0',
            'xminus' => '2', 'xdirekt' => '1', 'xkegel' => '1', 'xanzcl' => '1', 'xchamp' => '1',
            'xfavteam' => '2', 'xurlt' => '1',
        ]), $c->changes());
        $t->add('Grunddaten erneut oeffnen', $c->get('lmoadmin.php', $q), $c->changes());
        $t->add('oeffentliche Tabelle danach', $c->get('lmo.php', 'file=golden/basic.l98&action=table'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'basic_data', $t->text());
    }

    public function testTeams(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $q = self::A . '&todo=edit' . self::BASIC . '&st=-2';

        $t->add('Mannschaften speichern', $c->submitForm('lmoadmin.php', $q, 'xteams1', [
            'xteams1' => 'Neu Team Eins', 'xteamk1' => 'NE1', 'xteamu1' => 'http://example.org/eins', 'xteamn1' => 'Notiz Eins',
            'xstrafp2' => '3', 'xstrafm2' => '1', 'xstrafdat2' => '4',
            'xtorkorrektur12' => '2', 'xtorkorrektur22' => '1',
        ]), $c->changes());
        $t->add('Mannschaften erneut oeffnen', $c->get('lmoadmin.php', $q), $c->changes());
        $t->add('oeffentliche Tabelle danach', $c->get('lmo.php', 'file=golden/basic.l98&action=table'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'teams', $t->text());
    }

    public function testCounts(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $q = self::A . '&todo=edit' . self::BASIC . '&st=-3';

        $t->add('Anzahl Spieltage aendern', $c->submitForm('lmoadmin.php', $q, 'xanzst', ['xanzst' => '15']), $c->changes());
        $t->add('Anzahl erneut oeffnen', $c->get('lmoadmin.php', $q), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'counts', $t->text());
    }

    public function testTableCorrection(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $q = self::A . '&todo=tabs&file=golden/handicap.l98&st=3';

        $t->add('Tabellenkorrektur: Platz 1 und 2 tauschen', $c->submitForm('lmoadmin.php', $q, 'xplatz1', [
            'xplatz1' => '2', 'xplatz2' => '1',
        ]), $c->changes());
        $t->add('oeffentliche Tabelle danach', $c->get('lmo.php', 'file=golden/handicap.l98&action=table'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'table_correction', $t->text());
    }

    public function testOptions(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();

        $t->add('Optionen speichern', $c->submitForm('lmoadmin.php', self::A . '&todo=options', 'xadr', [
            'xadr' => 'admin@example.test', 'xdeftime' => '18:00', 'xtabpkt' => '0', 'xtabonres' => '2', 'xbacklink' => '0',
        ]), $c->changes());
        $t->add('Optionen erneut oeffnen', $c->get('lmoadmin.php', self::A . '&todo=options'), $c->changes());
        $t->add('oeffentliche Tabelle danach', $c->get('lmo.php', 'file=golden/basic.l98&action=table'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'options', $t->text());
    }

    public function testDesign(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();

        $t->add('Design speichern', $c->submitForm('lmoadmin.php', self::A . '&todo=design', 'xlmo_main_background1', [
            'xlmo_main_background1' => '#ffeedd', 'xlmo_tabelle_background1' => '#00ff00',
        ]), $c->changes());
        $t->add('Stylesheet danach', $c->get('lmo-style.php', ''), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'design', $t->text());
    }

    public function testUserManagement(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $u = self::A . '&todo=user';

        $t->add('Benutzerliste', $c->get('lmoadmin.php', $u), $c->changes());
        $t->add('Benutzer anlegen', $c->submitForm('lmoadmin.php', $u, 'xadmin_name', [
            'xadmin_name' => 'helfer1', 'xadmin_pass' => 'passwort1',
        ]), $c->changes());
        $t->add('Benutzer bearbeiten: Rang, Ligen, erweitert', $c->submitForm('lmoadmin.php', $u . '&show=1', 'xadmin_name1', [
            'xadmin_rang1' => '1', 'xhelfer_ligen1[]' => ['basic', 'special'], 'xadmin_erweitert1' => '1',
        ]), $c->changes());
        $t->add('Benutzer loeschen', $c->get('lmoadmin.php', $u . '&del=1'), $c->changes());
        $t->add('Benutzerliste danach', $c->get('lmoadmin.php', $u), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'users', $t->text());
    }

    public function testDeleteLeague(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();
        $d = self::A . '&todo=delete';

        $t->add('Loeschliste', $c->get('lmoadmin.php', $d), $c->changes());
        $t->add('Liga loeschen', $c->get('lmoadmin.php', $d . '&del=1&dfile=ligen/golden/kegel.l98'), $c->changes());
        $t->add('nicht vorhandene Datei loeschen', $c->get('lmoadmin.php', $d . '&del=1&dfile=ligen/golden/gibtsnicht.l98'), $c->changes());
        $t->add('oeffentliche Ligenuebersicht danach', $c->get('lmo.php', ''), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'delete_league', $t->text());
    }

    public function testCreateLeague(): void
    {
        $c = Fixture::loggedInClient();
        $t = new Transcript();

        $t->add('Schritt 1: Formular', $c->get('lmoadmin.php', self::A . '&todo=new'), $c->changes());
        $t->add('Schritt 1: Name, Titel, Typ', $c->submitFromLast('xfile', [
            'xfile' => 'testliga', 'xtitel' => 'Testliga', 'xtype' => '0',
        ]), $c->changes());
        $t->add('Schritt 2: Teams, Spieltage, Spiele', $c->submitFromLast('xteams', [
            'xteams' => '4', 'xanzst' => '6', 'xanzsp' => '2',
        ]), $c->changes());
        $t->add('Schritt 3: ohne Spielplan', $c->submitFromLast('xprogram', ['xprogram' => 'none']), $c->changes());
        $t->add('neue Liga bearbeiten', $c->get('lmoadmin.php', self::A . '&todo=edit&file=testliga.l98&st=1'), $c->changes());
        $t->add('oeffentliche Tabelle der neuen Liga', $c->get('lmo.php', 'file=testliga.l98&action=table'), $c->changes());

        $this->assertMatchesSnapshot('admin-write', 'create_league', $t->text());
    }
}
