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
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitAdminMenue;
use Lmo\Addon\MitAdminSeiten;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Web\Kontext;

/**
 * Addon "tipp": das Tippspiel (action=tipp) mit Menuepunkt, wenn es eingeschaltet ist (eintippspiel).
 *
 * Alle Seiten zeigen die Controller des Addons; zuletzt steht der AnmeldungController, der auch
 * alle uebrigen zeigt (bis 4.5 lmo-tippstart.php und was es einband). Was noch aus dem alten Code
 * kommt (Tippregeln, Mails), gibt addon.php als Closure mit.
 */
final class TippAddon implements MitSeiten, MitRahmenteilen, MitAdminMenue, MitAdminSeiten
{
    /**
     * @param string $ordner Ordner des Addons (addon/tipp)
     * @param Closure(string): string $regeln gibt die Tippregeln aus (Datei relativ zum Ordner, tipp/cfg.txt regelnlink)
     * @param Closure(string, string, string): string $freischaltmail schickt E-Mail-Adresse, Nick und Kennwort an den Tipper, gibt die Meldung zurueck
     * @param Closure(string, string, string): ?string $kennwortmail schickt E-Mail-Adresse, Nick und neues Kennwort ("Kennwort vergessen"); null oder der Fehler
     * @param Closure(string): string $adminmail meldet dem Admin einen neuen Tipper (Nick), gibt einen Fehler zurueck ('' ohne)
     * @param string $version installierte Version des LMO (LMO_VERSION_NR) fuer die Info-Seite
     */
    public function __construct(private string $ordner = '', private ?Closure $regeln = null, private ?Closure $freischaltmail = null, private ?Closure $kennwortmail = null, private ?Closure $adminmail = null, private string $version = '')
    {
    }

    public function name(): string
    {
        return 'tipp';
    }

    public function controller(): array
    {
        $rahmen = new Tipprahmen($this->regeln ?? static fn (string $datei): string => '');

        $freischaltmail = $this->freischaltmail ?? static fn (string $email, string $nick, string $kennwort): string => '';

        $kennwortmail = $this->kennwortmail ?? static fn (string $email, string $nick, string $kennwort): ?string => null;
        $startseite = new StartseiteController($this->ordner, $rahmen);

        return [
            new RegistrierungController($this->ordner, $freischaltmail, $this->adminmail ?? static fn (string $nick): string => ''),
            new KontoController($this->ordner, $rahmen, $freischaltmail),
            new UebersichtController($this->ordner, $rahmen),
            new TippabgabeController($this->ordner, $rahmen),
            new EinsichtController($this->ordner, $rahmen),
            new TabelleController($this->ordner, $rahmen),
            new RanglisteController($this->ordner, $rahmen),
            new FieberkurveController($this->ordner, $rahmen),
            new InfoController($rahmen, $this->version),
            $startseite,
            new AnmeldungController($this->ordner, $rahmen, $startseite, $kennwortmail),
        ];
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
        if (!$kontext->konfiguration->schalter('eintippspiel')) {
            return [];
        }
        $t = $kontext->texteVon('tipp');

        return [new Menuepunkt('tipp', $t[0] ?? '', $t[57] ?? '', in_array($kontext->anfrage->text('todo'), ['tipp', 'tippemail', 'tippuser', 'tippuseredit', 'tippoptions'], true))];
    }

    public function ligaMenue(Kontext $kontext): array
    {
        return [];
    }

    /**
     * Der Menuepunkt "Tippspiel", wenn die Liga getippt werden kann (alle Ligen: immeralle, sonst
     * die in ligenzutippen) oder keine Liga geladen ist.
     */
    public function rahmenteile(Kontext $kontext): array
    {
        if (!$kontext->konfiguration->schalter('eintippspiel')) {
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
