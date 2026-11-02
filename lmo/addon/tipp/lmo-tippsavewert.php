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
 * Die Auswertung der Tipps einer Liga (Lmo\Addon\Tipp\Ligaauswertung) fuer den alten Code, der sie
 * noch einbindet: beim Speichern der Ergebnisse (todo=edit, mit dem gerade eingegebenen Stand in
 * $lmo_liga) und in classlib/update_addons.php.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga|null $lmo_liga Rohdaten der geladenen Liga, beim Speichern (todo=edit)
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $file Ligadatei relativ zum Ligenordner (todo=edit)
 * @var mixed $liga Name der Liga (Dateiname ohne .l98)
 * @var int|string $st der Spieltag, 0 fuer alle
 * @var string $dirliga Ligenordner relativ zum LMO (Konfiguration)
 * @var array<string, mixed> $cfgarray die Konfiguration (lmo-cfgload.php), die des Tippspiels unter 'tipp'
 *
 * Einstellungen:
 * @var int|string $tipp_dirtipp Einstellung aus tipp/cfg.txt
 * @var int|string $tipp_tippauthtxt Einstellung aus tipp/cfg.txt
 */

require_once(PATH_TO_LMO."/lmo-admintest.php");
if (isset($todo) && $todo != "edit") {
  $file = $liga.".l98";
}
// die Einstellungen, wie sie gerade gelten
$lmo_wert_cfg = array();
foreach (array_keys($cfgarray['tipp'] ?? array()) as $lmo_wert_name) {
  $lmo_wert_cfg[$lmo_wert_name] = (string)(${'tipp_' . $lmo_wert_name} ?? '');
}
$lmo_wert_liga = null;
if (file_exists(PATH_TO_LMO.'/'.$dirliga.$file)) {
  echo getMessage($text['tipp'][0].': '.$text['tipp'][29]." <var>".$liga."</var> ".$text['tipp'][65]);
  // beim Speichern im Adminbereich (todo=edit) steht der gerade eingegebene Stand schon in $lmo_liga
  $lmo_wert_liga = isset($todo) && $todo == "edit" ? ($lmo_liga ?? new \Lmo\Liga\Rohdaten\Liga()) : \Lmo\Liga\Rohdaten\Liga::lies(PATH_TO_LMO.'/'.$dirliga.$file);
}
$lmo_wert_daten = \Lmo\Addon\Tipp\TippAddon::datenordner(PATH_TO_DATADIR, PATH_TO_ADDONDIR, PATH_TO_CONFIGDIR);
$lmo_wert_auswertung = new \Lmo\Addon\Tipp\Ligaauswertung($lmo_wert_daten . '/' . $tipp_dirtipp, $lmo_wert_daten . '/' . $tipp_tippauthtxt, $lmo_wert_cfg, (string)$text[0], (string)$text[1]);
if (!$lmo_wert_auswertung->werte((string)$liga, $lmo_wert_liga, (int)$st, isset($start) ? (int)$start : null, isset($ende) ? (int)$ende : null)) {
  echo getMessage($text['tipp'][29]." ".$text[283], true);
}

clearstatcache();
if (isset($todo) && $todo != "edit") {
  $file = "";
}
