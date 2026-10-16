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
 * Legt die Anwendung an (Lmo\Web\Anwendung), die die Seiten mit eigenem Controller zeigt, und
 * verbindet sie mit dem alten Code: Konfiguration, Sprachtexte, Vereinswappen und Rechte kommen
 * vorerst noch aus init.php und lmo-functions.php.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var array<string, mixed> $cfgarray Konfiguration der Installation (lmo-cfgload.php)
 * @var array<int|string, mixed> $text Sprachtexte (lmo-langload.php)
 */

$lmo_konfiguration = new \Lmo\Web\Konfiguration($cfgarray);

// bisherige Ergebnisse je Spiel (AddOn Team Vergleich, nicht Teil des LMO); $i wie bisher: Partie ab 0
$lmo_addon_vergleich = null;
if (file_exists(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php')) {
    $lmo_addon_vergleich = static function (\Lmo\Liga\Spiel $lmo_spiel) use ($text): string {
        $i = $lmo_spiel->partie - 1;
        $st = $lmo_spiel->spieltag;
        ob_start();
        include(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php');

        return (string)ob_get_clean();
    };
}

$lmo_anwendung = new \Lmo\Web\Anwendung(
    $lmo_konfiguration,
    $text,
    // Hilfsadmins sehen nur ihre eigenen Ligen
    new \Lmo\Web\LigaLader(PATH_TO_LMO . '/' . $lmo_konfiguration->ligenordner(), $lmo_konfiguration->datumsformat(), static fn (string $datei): bool => check_hilfsadmin($datei)),
    new \Lmo\Web\Pfade(PATH_TO_LMO, PATH_TO_LMO . '/vorlagen', URL_TO_IMGDIR),
    new \Lmo\Web\Darstellung(static fn (string $datei, string $name, string $attribute): string => (string)HTML_smallTeamIcon($datei, $name, $attribute)),
    [
        new \Lmo\Web\Controller\ErgebnisseController($lmo_addon_vergleich),
    ]
);
