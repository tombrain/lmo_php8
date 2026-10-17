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
 * Legt die Anwendung an (Lmo\Web\Anwendung), die die Seiten mit eigenem Controller zeigt, mit den
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

$lmo_anwendung = new \Lmo\Web\Anwendung(
    $lmo_konfiguration,
    $text,
    // Hilfsadmins sehen nur ihre eigenen Ligen
    new \Lmo\Web\LigaLader(PATH_TO_LMO . '/' . $lmo_konfiguration->ligenordner(), $lmo_konfiguration->datumsformat(), static fn (string $datei): bool => check_hilfsadmin($datei)),
    new \Lmo\Web\Pfade(PATH_TO_LMO, PATH_TO_LMO . '/vorlagen', URL_TO_IMGDIR),
    new \Lmo\Web\Darstellung(static fn (string $datei, string $name, string $attribute): string => (string)HTML_smallTeamIcon($datei, $name, $attribute)),
    [
        new \Lmo\Web\Controller\ErgebnisseController(),
        new \Lmo\Web\Controller\KreuztabelleController(),
        new \Lmo\Web\Controller\SpielplanController(),
        new \Lmo\Web\Controller\KalenderController(),
        new \Lmo\Web\Controller\PokalErgebnisseController(),
        new \Lmo\Web\Controller\PokalSpielplanController(),
    ],
    $lmo_addons
);
