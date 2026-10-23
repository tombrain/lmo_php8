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
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var int|string $st gewaehlter Spieltag
 * @var array<int|string, mixed> $array leeres Array als Vorlage fuer array_pad()
 * @var string $tippfile Tippdatei des Tippspiels
 */

  
  
if ($tippfile == "") {
  exit;
}
if (substr($tippfile, -4) != ".tip") {
  exit;
}
$jksp = "";
if (!is_readable($tippfile)) {
  exit;
}
// der Spieltag aus Sicht des Tippspiels: so viele Paarungen und Spiele je Paarung fassen die Tipps
$lmo_tipp_lader_runde = \Lmo\Addon\Tipp\Tipprunde::fuer($lmo_liga, (int)$st);
// nur der benoetigte Spieltag wird eingelesen (Lmo\Addon\Tipp\Tippdatei)
$lmo_tipp_gelesen = \Lmo\Addon\Tipp\Tippdatei::lies($tippfile)->runde((int)$st, $lmo_tipp_lader_runde->plaetze, $lmo_tipp_lader_runde->pokal ? $lmo_tipp_lader_runde->spieleJePaarung : null);
$jksp = $lmo_tipp_gelesen['joker'];
if (!$lmo_tipp_lader_runde->pokal) {
  $goaltippa = array_pad(array("_"), $lmo_tipp_lader_runde->plaetze+1, "_");
  $goaltippb = array_pad(array("_"), $lmo_tipp_lader_runde->plaetze+1, "_");
  foreach ($lmo_tipp_gelesen['tipps'] as $i => $lmo_tipp) {
    $goaltippa[$i] = $lmo_tipp[0];
    $goaltippb[$i] = $lmo_tipp[1];
  }
} else {
  $goaltippa = array_pad($array, $lmo_tipp_lader_runde->plaetze+1, "");
  $goaltippb = array_pad($array, $lmo_tipp_lader_runde->plaetze+1, "");
  foreach ($lmo_tipp_gelesen['tipps'] as $i => $lmo_tipp) {
    $goaltippa[$i] = array_pad(array("_"), $lmo_tipp_lader_runde->spieleJePaarung+1, "_");
    $goaltippb[$i] = array_pad(array("_"), $lmo_tipp_lader_runde->spieleJePaarung+1, "_");
    foreach ($lmo_tipp as $n => $lmo_spiel) {
      $goaltippa[$i][$n] = $lmo_spiel[0];
      $goaltippb[$i][$n] = $lmo_spiel[1];
    }
  }
}
clearstatcache();

?>