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
 * Die Gesamtwertung des Tippspiels (Lmo\Addon\Tipp\Gesamtwertung) fuer den alten Code, der sie
 * noch einbindet: beim Speichern der Ergebnisse (todo=edit) und in classlib/update_addons.php.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var array<int|string, mixed> $text Sprachtexte
 * @var array<string, mixed> $cfgarray die Konfiguration (lmo-cfgload.php), die des Tippspiels unter 'tipp'
 *
 * Einstellungen:
 * @var int|string $tipp_dirtipp Einstellung aus tipp/cfg.txt
 * @var int|string $tipp_tippauthtxt Einstellung aus tipp/cfg.txt
 */

require_once(PATH_TO_LMO . "/lmo-admintest.php");
// die Einstellungen, wie sie gerade gelten
$lmo_gesamt_cfg = array();
foreach (array_keys($cfgarray['tipp'] ?? array()) as $lmo_gesamt_name) {
  $lmo_gesamt_cfg[$lmo_gesamt_name] = (string)(${'tipp_' . $lmo_gesamt_name} ?? '');
}
$lmo_gesamt_daten = \Lmo\Addon\Tipp\TippAddon::datenordner(PATH_TO_DATADIR, PATH_TO_ADDONDIR, PATH_TO_CONFIGDIR);
if ((new \Lmo\Addon\Tipp\Gesamtwertung($lmo_gesamt_daten . '/' . $tipp_dirtipp, $lmo_gesamt_daten . '/' . $tipp_tippauthtxt, $lmo_gesamt_cfg))->werte()) {
  echo getMessage($text['tipp'][0].': '.$text['tipp'][56]." ".$text['tipp'][65]);
} else {
  echo getMessage($text['tipp'][0].': '.$text['tipp'][29]." ".$text[283], true);
}

clearstatcache();
if (isset($todo) && $todo != "edit") {
  $file = "";
}
