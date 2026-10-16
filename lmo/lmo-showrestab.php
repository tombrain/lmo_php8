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
 * Die Ergebnis- und die Tabellenseite einer Liga (action=results und action=table): verbindet
 * den Controller Lmo\Web\Controller\ErgebnisseController mit der Konfiguration und dem alten Code.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $action gewaehlte Seite (Aufrufparameter)
 * @var array<string, mixed> $cfgarray Konfiguration der Installation (lmo-cfgload.php)
 */

if (isset($file) && $file != "") {
  // bisherige Ergebnisse je Spiel (AddOn Team Vergleich, nicht Teil des LMO); $i wie bisher: Partie ab 0
  $lmo_addon_vergleich = null;
  if (file_exists(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php')) {
    $lmo_addon_vergleich = static function (\Lmo\Liga\Spiel $lmo_spiel) use ($lmo_liga, $text, $file): string {
      $i = $lmo_spiel->partie - 1;
      $st = $lmo_spiel->spieltag;
      ob_start();
      include(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php');

      return (string)ob_get_clean();
    };
  }

  // Konfiguration der Installation (lmo-cfgload.php)
  $lmo_konfiguration = new \Lmo\Web\Konfiguration($cfgarray);
  $lmo_controller = new \Lmo\Web\Controller\ErgebnisseController(
    $lmo_liga,
    $text,
    new \Lmo\Web\Darstellung(static fn (string $datei, string $name, string $attribute): string => (string)HTML_smallTeamIcon($datei, $name, $attribute)),
    PATH_TO_LMO . '/vorlagen',
    URL_TO_IMGDIR,
    $lmo_konfiguration,
    // Statistikdatei der Liga (lmo-zustat.php)
    \Lmo\Web\Statistikdatei::lies(PATH_TO_LMO . '/' . basename($lmo_konfiguration->ausgabeordner()) . '/' . str_replace('.l98', '.l98.php', basename($file))),
    $lmo_addon_vergleich
  );
  echo $lmo_controller->zeige(\Lmo\Web\Anfrage::ausGlobalen(), $action, $_SERVER['PHP_SELF'], $file);
}
