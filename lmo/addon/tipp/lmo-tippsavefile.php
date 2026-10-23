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
 * @var \Lmo\Addon\Tipp\Tipprunde $lmo_tipprunde Spieltag der Tippabgabe (lmo-tippedit.php); nur bei $st > 0
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $st gewaehlter Spieltag
 * @var string $todo gewaehlte Aktion (Aufrufparameter)
 * @var string $tippfile Tippdatei des Tippspiels
 *
 * Einstellungen:
 * @var int|string $tipp_jokertipp Einstellung aus tipp/cfg.txt
 *
 * Weitere Variablen des einbindenden Codes:
 * @var array<int|string, mixed> $goaltippa
 * @var array<int|string, mixed> $goaltippb
 * @var mixed $jksp
 */
  
  
if ($todo == "tippuseredit") {
  require_once(PATH_TO_LMO."/lmo-admintest.php");
} else {
  require_once(PATH_TO_ADDONDIR."/tipp/lmo-tipptest.php");
}
 
if ($tippfile != "") {
  if (substr($tippfile, -4) == ".tip") {
    // die Tippdatei (Lmo\Addon\Tipp\Tippdatei): die uebrigen Spieltage bleiben, der getippte kommt ans Ende
    $lmo_tippdatei = $st > 0 ? \Lmo\Addon\Tipp\Tippdatei::lies($tippfile) : \Lmo\Addon\Tipp\Tippdatei::leer();
    if ($st > 0) {
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
      $lmo_tippdatei = $lmo_tippdatei->mitRunde((int)$st, $lmo_tipps, $tipp_jokertipp == 1 ? (string)$jksp : null, $lmo_tipprunde->pokal);
    }
    if (!$lmo_tippdatei->speichere($tippfile)) {
      echo getMessage($text[283],true);
      exit;
    } elseif ($todo != "newtipper" && $todo != "newligen" && $todo != "tippuseredit") {
      if (!isset($jkspanticheat)) {
        $jkspanticheat = false;
      }
      if ($jkspanticheat == false) {
        echo getMessage($text['tipp'][41]);
      } else {
        echo getMessage($text['tipp'][41]." ".$text['tipp'][292]);
      }
    }
  }
  clearstatcache();
}
?>