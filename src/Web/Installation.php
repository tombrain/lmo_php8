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

namespace Lmo\Web;

use Closure;
use Lmo\Addon\Addons;
use Lmo\Addon\AltesSeitenInclude;
use Lmo\Addon\AltesSpielInclude;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Saisonstatistik;
use Lmo\Web\Controller\ErgebnisseController;
use Lmo\Web\Controller\FieberkurveController;
use Lmo\Web\Controller\InfoController;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Controller\KreuztabelleController;
use Lmo\Web\Controller\LigenuebersichtController;
use Lmo\Web\Controller\PokalErgebnisseController;
use Lmo\Web\Controller\PokalSpielplanController;
use Lmo\Web\Controller\SpielplanController;
use Lmo\Web\Controller\StatistikController;

/**
 * Die Anwendung dieser Installation (bis 4.5 lmo-anwendung.php): die Seiten des Kerns, die Addons
 * aus addon/ und die Bruecken zu Addons nach alter Art (nicht Teil des LMO), mit den Pfaden und
 * Adressen der Installation. Konfiguration und Sprachtexte kommen aus init.php.
 */
final class Installation
{
    /**
     * @param string $lmo Ordner des LMO (PATH_TO_LMO)
     * @param string $addons Ordner der Addons (PATH_TO_ADDONDIR)
     * @param string $bilder Ordner der Bilder (PATH_TO_IMGDIR)
     * @param string $bilderAdresse Adresse der Bilder (URL_TO_IMGDIR)
     * @param string $adresse Adresse des LMO (URL_TO_LMO)
     * @param string|null $vorlagen Ordner der Vorlagen; ohne lmo/vorlagen
     */
    public function __construct(
        private string $lmo,
        private string $addons,
        private string $bilder,
        private string $bilderAdresse,
        private string $adresse,
        private ?string $vorlagen = null
    ) {
    }

    /** Die Installation mit den Pfaden, die init.php festlegt */
    public static function ausKonstanten(): self
    {
        $konstante = static fn (string $name): string => defined($name) && is_string(constant($name)) ? (string) constant($name) : '';

        return new self($konstante('PATH_TO_LMO'), $konstante('PATH_TO_ADDONDIR'), $konstante('PATH_TO_IMGDIR'), $konstante('URL_TO_IMGDIR'), $konstante('URL_TO_LMO'));
    }

    /**
     * Die Anwendung.
     *
     * @param array<string, mixed> $cfgarray die Konfiguration (init.php)
     * @param array<int|string, mixed> $text die Sprachtexte (init.php)
     * @param Closure(string): bool $darf darf die Liga gezeigt werden (Hilfsadmins nur ihre, check_hilfsadmin())
     * @param bool $angemeldet ein Admin oder Hilfsadmin ist angemeldet (die Ligenuebersicht nennt die Dateien)
     * @param string $version die installierte Version (LMO_VERSION_NR)
     */
    public function anwendung(array $cfgarray, array $text, Closure $darf, bool $angemeldet, string $version): Anwendung
    {
        $konfiguration = new Konfiguration($cfgarray);
        $bilder = new Bilder($this->bilder, $this->bilderAdresse);

        return new Anwendung(
            $konfiguration,
            // Sprachtexte des alten Codes, getrennt in Kerntexte und die der Unterordner (date, Addons)
            Sprachtexte::ausAltemText($text),
            new LigaLader($this->lmo . '/' . $konfiguration->ligenordner(), $konfiguration->datumsformat(), $darf),
            new Pfade($this->lmo, $this->vorlagen ?? $this->lmo . '/vorlagen', $this->bilderAdresse, $this->adresse),
            new Darstellung(
                static fn (string $datei, string $name, string $attribute): string => $bilder->bild($name, 'teams', 'small', $attribute),
                static fn (string $datei, string $attribute): string => $bilder->ligaSymbol($datei, $attribute),
                static fn (string $name, string $attribute): string => $bilder->bild($name, 'spieler', 'small', $attribute),
                static fn (string $datei, string $name, string $attribute): string => $bilder->bild($name, 'teams', 'big', $attribute)
            ),
            [
                new ErgebnisseController(),
                new KreuztabelleController(),
                new SpielplanController(),
                new KalenderController(),
                new PokalErgebnisseController(),
                new PokalSpielplanController(),
                new FieberkurveController(),
                new StatistikController($this->statistikErzeugen($konfiguration)),
                new InfoController($version),
            ],
            $this->addons(),
            // nach den Addons: die Ligenuebersicht ohne gewaehlte Liga (backlink), damit Seiten der
            // Addons ohne Liga vorgehen
            $konfiguration->schalter('backlink') ? [new LigenuebersichtController($angemeldet, $darf)] : []
        );
    }

    /**
     * Schreibt die Statistikdatei einer Liga neu (Lmo\Liga\Saisonstatistik), fuer die Statistikseite
     * und das Speichern in der Administration.
     *
     * @return Closure(Liga, string, array<int|string, mixed>): void Liga, Ligadatei, Sprachtexte
     */
    public function statistikErzeugen(Konfiguration $konfiguration): Closure
    {
        $ordner = $this->lmo . '/' . $konfiguration->ausgabeordner();

        return static function (Liga $liga, string $datei, array $text) use ($ordner): void {
            $spieltag = $text[145] ?? '';
            Saisonstatistik::schreibe($liga, $ordner . basename($datei) . '.php', is_scalar($spieltag) ? (string) $spieltag : '');
        };
    }

    /**
     * Die Addons mit addon.php (Lmo\Addon\Addon); dazu die Bruecken zu Addons nach alter Art:
     * bisherige Ergebnisse am Ende jeder Ergebniszeile in Liga und Pokal (AddOn Team Vergleich) und
     * der Teamplan unter dem Spielplan (PDF-Addon).
     */
    private function addons(): Addons
    {
        $addons = Addons::ausOrdner($this->addons);
        if (file_exists($this->addons . '/stats/lmo-showprogram.inc.php')) {
            $addons = $addons->mit(new AltesSpielInclude('stats', $this->addons . '/stats/lmo-showprogram.inc.php', false));
        }
        if (file_exists($this->addons . '/stats/lmo-showkoresults.inc.php')) {
            $addons = $addons->mit(new AltesSpielInclude('stats', $this->addons . '/stats/lmo-showkoresults.inc.php', true));
        }
        if (file_exists($this->addons . '/pdf/lmo-showprogram.inc.php')) {
            $addons = $addons->mit(new AltesSeitenInclude('pdf', 'program', $this->addons . '/pdf/lmo-showprogram.inc.php'));
        }

        return $addons;
    }
}
