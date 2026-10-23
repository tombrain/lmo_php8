<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of
  * the License, or (at your option) any later version.
  *
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
  * General Public License for more details.
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  *
  */
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

/**
 * Legt die Anwendung an und gibt sie zurueck (Lmo\Web\Anwendung), die die Seiten mit eigenem Controller zeigt, mit den
 * Addons der Installation (Lmo\Addon\Addons), und
 * verbindet sie mit dem alten Code: Konfiguration, Sprachtexte, Vereinswappen und Rechte kommen
 * vorerst noch aus init.php und lmo-functions.php.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var array<string, mixed> $cfgarray Konfiguration der Installation (lmo-cfgload.php)
 * @var array<int|string, mixed> $text Sprachtexte (lmo-langload.php)
 */

$lmo_konfiguration = new \Lmo\Web\Konfiguration($cfgarray);

// Addons mit addon.php (Lmo\Addon\Addon); dazu die Bruecken zu Addons nach alter Art (nicht Teil
// des LMO): bisherige Ergebnisse am Ende jeder Ergebniszeile in Liga und Pokal (AddOn Team
// Vergleich) und der Teamplan unter dem Spielplan
$lmo_addons = \Lmo\Addon\Addons::ausOrdner(PATH_TO_ADDONDIR);
if (file_exists(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php')) {
    $lmo_addons = $lmo_addons->mit(new \Lmo\Addon\AltesSpielInclude('stats', PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php', false));
}
if (file_exists(PATH_TO_ADDONDIR . '/stats/lmo-showkoresults.inc.php')) {
    $lmo_addons = $lmo_addons->mit(new \Lmo\Addon\AltesSpielInclude('stats', PATH_TO_ADDONDIR . '/stats/lmo-showkoresults.inc.php', true));
}
if (file_exists(PATH_TO_ADDONDIR . '/pdf/lmo-showprogram.inc.php')) {
    $lmo_addons = $lmo_addons->mit(new \Lmo\Addon\AltesSeitenInclude('pdf', 'program', PATH_TO_ADDONDIR . '/pdf/lmo-showprogram.inc.php'));
}

// schreibt die Statistikdatei einer Liga neu (lmo-zustat.php), fuer die Statistikseite
$lmo_statistik_erzeugen = static function (\Lmo\Liga\Rohdaten\Liga $lmo_liga, string $file, array $text) use ($lmo_konfiguration): void {
    $diroutput = $lmo_konfiguration->ausgabeordner();
    $generate_zustat = true;
    include(PATH_TO_LMO . '/lmo-zustat.php');
};

// Wappen, Liga- und Spielerbilder (bis 4.5 ueber die Classlib)
$lmo_bilder = new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR);

return new \Lmo\Web\Anwendung(
    $lmo_konfiguration,
    // Sprachtexte des alten Codes, getrennt in Kerntexte und die der Unterordner (date, Addons)
    \Lmo\Web\Sprachtexte::ausAltemText($text),
    // Hilfsadmins sehen nur ihre eigenen Ligen
    new \Lmo\Web\LigaLader(PATH_TO_LMO . '/' . $lmo_konfiguration->ligenordner(), $lmo_konfiguration->datumsformat(), static fn (string $datei): bool => check_hilfsadmin($datei)),
    new \Lmo\Web\Pfade(PATH_TO_LMO, PATH_TO_LMO . '/vorlagen', URL_TO_IMGDIR, URL_TO_LMO),
    new \Lmo\Web\Darstellung(
        static fn (string $datei, string $name, string $attribute): string => $lmo_bilder->bild($name, 'teams', 'small', $attribute),
        static fn (string $datei, string $attribute): string => $lmo_bilder->ligaSymbol($datei, $attribute),
        static fn (string $name, string $attribute): string => $lmo_bilder->bild($name, 'spieler', 'small', $attribute),
        static fn (string $datei, string $name, string $attribute): string => $lmo_bilder->bild($name, 'teams', 'big', $attribute)
    ),
    [
        new \Lmo\Web\Controller\ErgebnisseController(),
        new \Lmo\Web\Controller\KreuztabelleController(),
        new \Lmo\Web\Controller\SpielplanController(),
        new \Lmo\Web\Controller\KalenderController(),
        new \Lmo\Web\Controller\PokalErgebnisseController(),
        new \Lmo\Web\Controller\PokalSpielplanController(),
        new \Lmo\Web\Controller\FieberkurveController(),
        new \Lmo\Web\Controller\StatistikController($lmo_statistik_erzeugen),
        new \Lmo\Web\Controller\InfoController((string)constant('LMO_VERSION_NR')),
    ],
    $lmo_addons,
    // nach den Addons: die Ligenuebersicht ohne gewaehlte Liga (backlink), damit Seiten der Addons
    // ohne Liga vorgehen; angemeldet (Admin oder Hilfsadmin) mit den Dateinamen
    $lmo_konfiguration->schalter('backlink') ? [
        new \Lmo\Web\Controller\LigenuebersichtController(isset($_SESSION['lmouserok']) && $_SESSION['lmouserok'] > 0, static fn (string $datei): bool => check_hilfsadmin($datei)),
    ] : []
);
