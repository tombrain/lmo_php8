<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;

/**
 * Addon "tipp": das Tippspiel (action=tipp) mit Menuepunkt, wenn es eingeschaltet ist (eintippspiel).
 *
 * Die Seiten, die schon umgebaut sind, zeigen die Controller des Addons (KontoController); die
 * uebrigen noch der alte Code (lmo-tippstart.php und was er einbindet). addon.php gibt ihn als
 * Closure mit, samt dem Laden der Liga, das bis 4.5 lmo-showmain2.php erledigte.
 */
final class TippAddon implements MitSeiten, MitRahmenteilen
{
    /**
     * @param Closure(): void $alterCode gibt die Seite des Tippspiels aus (lmo-tippstart.php)
     * @param string $ordner Ordner des Addons (addon/tipp)
     * @param Closure(string): string $regeln gibt die Tippregeln aus (Datei relativ zum Ordner, tipp/cfg.txt regelnlink)
     * @param Closure(string, string, string): string $freischaltmail schickt E-Mail-Adresse, Nick und Kennwort an den Tipper, gibt die Meldung zurueck
     * @param Closure(string, string, string): ?string $kennwortmail schickt E-Mail-Adresse, Nick und neues Kennwort ("Kennwort vergessen"); null oder der Fehler
     * @param Closure(string): string $adminmail meldet dem Admin einen neuen Tipper (Nick), gibt einen Fehler zurueck ('' ohne)
     * @param string $version installierte Version des LMO (LMO_VERSION_NR) fuer die Info-Seite
     */
    public function __construct(private Closure $alterCode, private string $ordner = '', private ?Closure $regeln = null, private ?Closure $freischaltmail = null, private ?Closure $kennwortmail = null, private ?Closure $adminmail = null, private string $version = '')
    {
    }

    public function name(): string
    {
        return 'tipp';
    }

    public function controller(): array
    {
        $alterCode = $this->alterCode;
        $rahmen = new Tipprahmen($this->regeln ?? static fn (string $datei): string => '');

        $freischaltmail = $this->freischaltmail ?? static fn (string $email, string $nick, string $kennwort): string => '';

        $kennwortmail = $this->kennwortmail ?? static fn (string $email, string $nick, string $kennwort): ?string => null;
        $startseite = new StartseiteController($this->ordner, $rahmen);

        return [
            new RegistrierungController($this->ordner, $freischaltmail, $this->adminmail ?? static fn (string $nick): string => ''),
            new KontoController($this->ordner, $rahmen, $freischaltmail),
            new TippabgabeController($this->ordner, $rahmen),
            new EinsichtController($this->ordner, $rahmen),
            new TabelleController($this->ordner, $rahmen),
            new RanglisteController($this->ordner, $rahmen),
            new FieberkurveController($this->ordner, $rahmen),
            new InfoController($rahmen, $this->version),
            $startseite,
            new AnmeldungController($this->ordner, $rahmen, $startseite, $kennwortmail),
            new class ($alterCode) implements Controller {
            /** @param Closure(): void $alterCode */
            public function __construct(private Closure $alterCode)
            {
            }

            public function zustaendig(Kontext $kontext): bool
            {
                return $kontext->action == 'tipp' && $kontext->konfiguration->schalter('eintippspiel');
            }

            public function zeige(Kontext $kontext): Seite
            {
                ob_start();
                ($this->alterCode)();

                return new Seite((string) ob_get_clean());
            }
        },
        ];
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
