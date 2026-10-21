<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Addon\AlteAdminSeite;
use Lmo\Addon\Datenordner;
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitAdminMenue;
use Lmo\Addon\MitAdminSeiten;
use Lmo\Addon\MitErgebnisAuswertung;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSchaltern;
use Lmo\Addon\MitSeiten;
use Lmo\Addon\Schalter;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;

/**
 * Addon "tipp": das Tippspiel (action=tipp) mit Menuepunkt, wenn es eingeschaltet ist (eintippspiel).
 *
 * Alle Seiten zeigen die Controller des Addons; zuletzt steht der AnmeldungController, der auch
 * alle uebrigen zeigt (bis 4.5 lmo-tippstart.php und was es einband). Was noch aus dem alten Code
 * kommt (Tippregeln, Mails), gibt addon.php als Closure mit.
 */
final class TippAddon implements MitSeiten, MitRahmenteilen, MitAdminMenue, MitAdminSeiten, MitSchaltern, MitErgebnisAuswertung
{
    /** Der Schalter in den Optionen: Tippspiel an (config/tipp/cfg.txt, bis 4.5 config/cfg.txt) */
    public const SCHALTER = 'eintippspiel';

    /**
     * @param string $ordner Ordner des Addons (addon/tipp)
     * @param Closure(string): string $regeln gibt die Tippregeln aus (Datei relativ zum Ordner, tipp/cfg.txt regelnlink)
     * @param Closure(string, string, string): string $freischaltmail schickt E-Mail-Adresse, Nick und Kennwort an den Tipper, gibt die Meldung zurueck
     * @param Closure(string, string, string): ?string $kennwortmail schickt E-Mail-Adresse, Nick und neues Kennwort ("Kennwort vergessen"); null oder der Fehler
     * @param Closure(string): string $adminmail meldet dem Admin einen neuen Tipper (Nick), gibt einen Fehler zurueck ('' ohne)
     * @param string $version installierte Version des LMO (LMO_VERSION_NR) fuer die Info-Seite
     * @param string $daten Datenordner des Tippspiels (daten/tipp, datenordner()) mit Tipperdatei und Tipps; '' wie $ordner
     */
    public function __construct(private string $ordner = '', private ?Closure $regeln = null, private ?Closure $freischaltmail = null, private ?Closure $kennwortmail = null, private ?Closure $adminmail = null, private string $version = '', private string $daten = '')
    {
    }

    /** @var array<string, string> bereits ermittelte Datenordner (der alte Code fragt oft) */
    private static array $datenordner = [];

    /**
     * Der Datenordner des Tippspiels: daten/tipp mit der Tipperdatei (tipp/cfg.txt tippauthtxt) und
     * den Tipps (dirtipp, mit auswert/, auswert/vereine/ und einsicht/); fehlt etwas davon, wird es
     * angelegt. Bis 4.5 lag beides in addon/tipp; von dort zieht es beim ersten Aufruf um.
     *
     * @param string $daten Datenordner des LMO (PATH_TO_DATADIR)
     * @param string $addons Ordner der Addons (PATH_TO_ADDONDIR)
     * @param string $konfiguration Ordner der Konfiguration (PATH_TO_CONFIGDIR) mit tipp/cfg.txt
     */
    public static function datenordner(string $daten, string $addons, string $konfiguration): string
    {
        $schluessel = $daten . '|' . $addons . '|' . $konfiguration;
        if (isset(self::$datenordner[$schluessel])) {
            return self::$datenordner[$schluessel];
        }
        $cfg = is_file($konfiguration . '/tipp/cfg.txt') ? parse_ini_file($konfiguration . '/tipp/cfg.txt', false, INI_SCANNER_RAW) ?: [] : [];
        $tipps = trim(is_string($cfg['dirtipp'] ?? null) ? $cfg['dirtipp'] : 'tipps/', '/');
        $tipper = is_string($cfg['tippauthtxt'] ?? null) ? $cfg['tippauthtxt'] : 'lmo-tippauth.txt';
        $ordner = Datenordner::umziehen($daten . '/tipp', $addons . '/tipp', [$tipps, $tipper]);
        foreach (['', '/auswert', '/auswert/vereine', '/einsicht'] as $unterordner) {
            if (!is_dir($ordner . '/' . $tipps . $unterordner)) {
                @mkdir($ordner . '/' . $tipps . $unterordner, 0777, true);
            }
        }
        if (!is_file($ordner . '/' . $tipper)) {
            @file_put_contents($ordner . '/' . $tipper, '');
        }

        return self::$datenordner[$schluessel] = $ordner;
    }

    public function name(): string
    {
        return 'tipp';
    }

    /** Ist das Tippspiel eingeschaltet (SCHALTER, ohne Konfiguration an)? */
    public static function an(Konfiguration $konfiguration): bool
    {
        return $konfiguration->addonSchalter('tipp', self::SCHALTER, true);
    }

    /** Der Schalter "Tippspiel" in den Optionen */
    public function schalter(Kontext $kontext): array
    {
        $t = $kontext->texteVon('tipp');

        return [new Schalter(self::SCHALTER, $t[0] ?? '', $t[306] ?? '', true)];
    }

    public function controller(): array
    {
        $daten = $this->daten !== '' ? $this->daten : $this->ordner;
        $rahmen = new Tipprahmen($this->regeln ?? static fn (string $datei): string => '');

        $freischaltmail = $this->freischaltmail ?? static fn (string $email, string $nick, string $kennwort): string => '';

        $kennwortmail = $this->kennwortmail ?? static fn (string $email, string $nick, string $kennwort): ?string => null;
        $startseite = new StartseiteController($daten, $rahmen);

        return [
            new RegistrierungController($daten, $freischaltmail, $this->adminmail ?? static fn (string $nick): string => ''),
            new KontoController($daten, $rahmen, $freischaltmail),
            new UebersichtController($daten, $rahmen),
            new TippabgabeController($daten, $rahmen),
            new EinsichtController($daten, $rahmen),
            new TabelleController($daten, $rahmen),
            new RanglisteController($daten, $rahmen),
            new FieberkurveController($daten, $rahmen),
            new InfoController($rahmen, $this->version),
            $startseite,
            new AnmeldungController($daten, $rahmen, $startseite, $kennwortmail),
        ];
    }

    /**
     * Beim Speichern der Ergebnisse einer getippten Liga die Auswertung der Tipps (tipp/cfg.txt
     * aktauswert: die Liga, aktauswertges: alle Ligen zusammen), noch nach alter Art. Bis 4.5 band
     * lmo-adminedit.php die Dateien selbst ein.
     */
    public function auswertungBeimSpeichern(Kontext $kontext): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        if ($kontext->liga === null || !self::wirdGetippt($cfg, $kontext->liga->datei)) {
            return [];
        }
        $dateien = [];
        if (($cfg['aktauswert'] ?? '') == 1) {
            $dateien[] = $this->ordner . '/lmo-tippsavewert.php';
        }
        if (($cfg['aktauswertges'] ?? '') == 1) {
            $dateien[] = $this->ordner . '/lmo-tippsavewertgesamt.php';
        }

        return $dateien;
    }

    /**
     * Wird die Liga getippt? Alle (immeralle) oder die in ligenzutippen (Dateinamen ohne .l98, mit
     * Komma getrennt).
     *
     * @param array<string, string> $cfg Konfiguration des Tippspiels (tipp/cfg.txt)
     */
    public static function wirdGetippt(array $cfg, string $datei): bool
    {
        // lose wie bisher ($tipp_immeralle == 0): fehlt der Wert, zaehlt die Liste
        $alle = $cfg['immeralle'] ?? null;

        return !($alle == 0) || in_array(basename(substr($datei, 0, -4)), explode(',', $cfg['ligenzutippen'] ?? ''));
    }

    /** Die Seiten der Administration des Tippspiels (noch nach alter Art), nur fuer den Hauptadmin. */
    public function adminController(): array
    {
        $seiten = [];
        foreach (['tipp' => 'lmo-admintipp.php', 'tippemail' => 'lmo-admintippemail.php', 'tippuser' => 'lmo-admintippuser.php', 'tippuseredit' => 'lmo-admintippuseredit.php', 'tippoptions' => 'lmo-admintippoptions.php'] as $todo => $datei) {
            $seiten[] = new AlteAdminSeite(
                static fn (Kontext $kontext): bool => $kontext->anfrage->text('todo') == $todo && $kontext->sitzung->zahl('lmouserok') == 2,
                $this->ordner . '/' . $datei
            );
        }

        return $seiten;
    }

    /** Der Punkt "Tippspiel" im Hauptmenue der Administration, wenn das Tippspiel eingeschaltet ist. */
    public function adminMenue(Kontext $kontext): array
    {
        if (!self::an($kontext->konfiguration)) {
            return [];
        }
        $t = $kontext->texteVon('tipp');

        return [new Menuepunkt('tipp', $t[0] ?? '', $t[57] ?? '', in_array($kontext->anfrage->text('todo'), ['tipp', 'tippemail', 'tippuser', 'tippuseredit', 'tippoptions'], true))];
    }

    public function ligaMenue(Kontext $kontext): array
    {
        return [];
    }

    /** Der Link zur Auswertung des Tippspiels auf der Startseite der Administration, wenn es eingeschaltet ist. */
    public function startseite(Kontext $kontext): array
    {
        if (!self::an($kontext->konfiguration)) {
            return [];
        }

        return [new Menuepunkt('tipp', $kontext->texteVon('tipp')[115] ?? '', '', false)];
    }

    /**
     * Der Menuepunkt "Tippspiel", wenn die Liga getippt werden kann (alle Ligen: immeralle, sonst
     * die in ligenzutippen) oder keine Liga geladen ist.
     */
    public function rahmenteile(Kontext $kontext): array
    {
        if (!self::an($kontext->konfiguration)) {
            return [];
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $datei = $kontext->liga !== null ? $kontext->liga->datei : '';
        if (($cfg['immeralle'] ?? '') != 1 && !str_contains($cfg['ligenzutippen'] ?? '', substr(basename($datei), 0, -4))) {
            return [];
        }
        $titel = $kontext->texteVon('tipp')[0] ?? '';

        return ['Tippspiel' => $kontext->action != 'tipp'
            ? '<a href="' . $kontext->adresse . '?file=' . $datei . '&amp;action=tipp" title="' . $titel . '">' . $titel . '</a>'
            : $titel];
    }
}
