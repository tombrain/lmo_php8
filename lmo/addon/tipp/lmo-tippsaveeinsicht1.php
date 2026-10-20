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
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Addon\Tipp\Tipprunde $lmo_tipprunde Spieltag der Tippabgabe (lmo-tippedit.php)
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var int|string $st gewaehlter Spieltag
 *
 * Einstellungen:
 * @var int|string $tipp_dirtipp Einstellung aus tipp/cfg.txt
 * @var int|string $tipp_jokertipp Einstellung aus tipp/cfg.txt
 *
 * Weitere Variablen des einbindenden Codes:
 * @var array<int|string, mixed> $goaltippa
 * @var array<int|string, mixed> $goaltippb
 * @var mixed $jksp
 */
  
  
require_once(PATH_TO_ADDONDIR."/tipp/lmo-tipptest.php");
if ($file != "" && $st > 0 && $_SESSION['lmotippername'] != "") {
  $einsichtfile = PATH_TO_ADDONDIR."/tipp/".$tipp_dirtipp."einsicht/".substr($file, 0, -4)."_".$st.".ein";
  //if (decoct(fileperms($einsichtfile))!=100777){chmod ($einsichtfile, 0777);}
  if (substr($einsichtfile, -4) == ".ein") {
    // die Einsicht hat das Format der Tippdatei, mit einem Abschnitt je Tipper (Lmo\Addon\Tipp\Tippdatei);
    // nur die unveraenderten Tipps werden zurueckgeschrieben, die getippten am Ende dazu
    $lmo_tipps = array();
    for ($j = 0; $j < $lmo_tipprunde->plaetze; $j++) {
      if (!$lmo_tipprunde->pokal) {
        $lmo_tipps[$j] = array($goaltippa[$j], $goaltippb[$j]);
      } else {
        for ($n = 0; $n < $lmo_tipprunde->spieleJePaarung; $n++) {
          $lmo_tipps[$j][$n] = array($goaltippa[$j][$n], $goaltippb[$j][$n]);
        }
      }
    }
    $lmo_einsicht = \Lmo\Addon\Tipp\Tippdatei::lies($einsichtfile)->mitAbschnitt($_SESSION['lmotippername'], $lmo_tipps, $tipp_jokertipp == 1 ? (string)$jksp : null, $lmo_tipprunde->pokal);
    if (!$lmo_einsicht->speichere($einsichtfile)) {
      echo getMessage($text[283],true);
      exit;
    }
  }
   
  clearstatcache();
}

?>