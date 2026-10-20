<?php

declare(strict_types=1);

namespace Lmo\Web;

use Lmo\Addon\Addons;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;

/**
 * Der Einstieg fuer die Seiten des LMO: liest gewaehlte Seite (action) und Ligadatei aus der
 * Anfrage, laedt die Liga und laesst den zustaendigen Controller die Seite bauen: erst die des
 * Kerns, dann die der Addons (Lmo\Addon\MitSeiten).
 *
 * Ist kein Controller zustaendig (etwa eine unbekannte Seite), liefert bearbeite() null; den Rahmen
 * baut lmo-start.php darum (Lmo\Web\Rahmen).
 */
final class Anwendung
{
    /** Startseite einer Liga ohne gewaehlte Seite, nach ihrer Einstellung OnRun */
    private const STARTSEITEN = ['results', 'table', 'program', 'cross', 'graph', 'stats', 'cal', 'spieler'];

    private Konfiguration $konfiguration;
    private Sprachtexte $texte;
    private LigaLader $lader;
    private Pfade $pfade;
    private Darstellung $darstellung;
    /** @var list<Controller> alle Controller in der Reihenfolge, in der sie gefragt werden: erst die des Kerns, dann die der Addons */
    private array $controller;
    private Addons $addons;

    /**
     * @param Konfiguration $konfiguration Konfiguration der Installation (config/cfg.txt)
     * @param Sprachtexte $texte Sprachtexte; fuer die geladene Liga passt sie kontext() an
     * @param LigaLader $lader laedt die Ligadatei aus der Anfrage
     * @param Pfade $pfade Ordner und Adressen der Installation
     * @param Darstellung $darstellung Vereinswappen und Faktoren fuer die Seiten
     * @param list<Controller> $controller Controller des Kerns, in der Reihenfolge, in der sie gefragt werden
     * @param Addons $addons Addons der Installation; ihre Controller (Lmo\Addon\MitSeiten) werden nach denen des Kerns gefragt
     */
    public function __construct(Konfiguration $konfiguration, Sprachtexte $texte, LigaLader $lader, Pfade $pfade, Darstellung $darstellung, array $controller, Addons $addons = new Addons())
    {
        $this->konfiguration = $konfiguration;
        $this->texte = $texte;
        $this->lader = $lader;
        $this->pfade = $pfade;
        $this->darstellung = $darstellung;
        $this->controller = array_merge($controller, $addons->controller());
        $this->addons = $addons;
    }

    /**
     * Die Seite zur Anfrage; null, wenn kein Controller zustaendig ist.
     *
     * @param string $adresse Adresse des LMO fuer Links ($_SERVER['PHP_SELF'])
     * @param string|null $datei vorgegebene Ligadatei (eine einbindende Seite setzt $file); null: aus der Anfrage
     */
    public function bearbeite(Anfrage $anfrage, string $adresse, ?string $datei = null): ?Seite
    {
        return $this->zeige($this->kontext($anfrage, $adresse, $datei));
    }

    /** Die Seite zum Kontext; null, wenn kein Controller zustaendig ist. */
    public function zeige(Kontext $kontext): ?Seite
    {
        foreach ($this->controller as $controller) {
            if ($controller->zustaendig($kontext)) {
                return $controller->zeige($kontext);
            }
        }

        return null;
    }

    /** Der Kontext zur Anfrage: Liga geladen, Seite bestimmt, Sprachtexte fuer die Liga. */
    public function kontext(Anfrage $anfrage, string $adresse, ?string $datei = null): Kontext
    {
        $liga = $this->lader->lade($datei ?? $anfrage->text('file'));
        $texte = $liga !== null ? $this->texte->fuerLiga($liga->liga) : $this->texte;

        return new Kontext($anfrage, self::action($anfrage, $liga?->liga), $liga, $this->konfiguration, $texte->kern, $adresse, $this->pfade, $this->darstellung, $this->addons, $texte->weitere);
    }

    /**
     * Die gewaehlte Seite: der Parameter action ("admin" heisst hier keine); ohne ihn die Startseite
     * der Liga (Einstellung OnRun).
     */
    public static function action(Anfrage $anfrage, ?Liga $liga): string
    {
        $action = $anfrage->text('action');
        if ($action !== '') {

            return $action == 'admin' ? '' : $action;
        }
        if ($liga === null) {
            return '';
        }

        return self::STARTSEITEN[$liga->einstellungen()->laufendeTabelle()] ?? '';
    }
}
