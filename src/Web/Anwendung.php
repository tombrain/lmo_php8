<?php

namespace Lmo\Web;

use Lmo\Addon\Addons;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;

/**
 * Der Einstieg fuer die Seiten des LMO: liest gewaehlte Seite (action) und Ligadatei aus der
 * Anfrage, laedt die Liga und laesst den zustaendigen Controller die Seite bauen: erst die des
 * Kerns, dann die der Addons (Lmo\Addon\MitSeiten).
 *
 * Seiten ohne eigenen Controller zeigt vorerst noch der alte Code (lmo-showmain2.php); fuer sie
 * liefert bearbeite() null.
 */
final class Anwendung
{
    /** Startseite einer Liga ohne gewaehlte Seite, nach ihrer Einstellung OnRun */
    private const STARTSEITEN = ['results', 'table', 'program', 'cross', 'graph', 'stats', 'cal', 'spieler'];

    private Konfiguration $konfiguration;
    /** @var array<int|string, mixed> */
    private array $text;
    private LigaLader $lader;
    private Pfade $pfade;
    private Darstellung $darstellung;
    /** @var list<Controller> alle Controller in der Reihenfolge, in der sie gefragt werden: erst die des Kerns, dann die der Addons */
    private array $controller;
    private Addons $addons;

    /**
     * @param Konfiguration $konfiguration Konfiguration der Installation (config/cfg.txt)
     * @param array<int|string, mixed> $text Sprachtexte; fuer die geladene Liga passt sie kontext() an
     * @param LigaLader $lader laedt die Ligadatei aus der Anfrage
     * @param Pfade $pfade Ordner und Adressen der Installation
     * @param Darstellung $darstellung Vereinswappen und Faktoren fuer die Seiten
     * @param list<Controller> $controller Controller des Kerns, in der Reihenfolge, in der sie gefragt werden
     * @param Addons $addons Addons der Installation; ihre Controller (Lmo\Addon\MitSeiten) werden nach denen des Kerns gefragt
     */
    public function __construct(Konfiguration $konfiguration, array $text, LigaLader $lader, Pfade $pfade, Darstellung $darstellung, array $controller, Addons $addons = new Addons())
    {
        $this->konfiguration = $konfiguration;
        $this->text = $text;
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
        $kontext = $this->kontext($anfrage, $adresse, $datei);
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
        $text = $liga !== null ? Sprachtexte::fuerLiga($this->text, $liga->liga) : $this->text;

        return new Kontext($anfrage, self::action($anfrage, $liga?->liga), $liga, $this->konfiguration, $text, $adresse, $this->pfade, $this->darstellung, $this->addons);
    }

    /**
     * Die gewaehlte Seite: der Parameter action ("admin" heisst hier keine); ohne ihn die Startseite
     * der Liga (wie lmo-start.php und lmo-showmain2.php).
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
