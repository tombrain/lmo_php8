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


// wieviel Punkte gibts für den Tipp (Lmo\Addon\Tipp\Punkte); die Regeln kommen aus der Konfiguration
// des Tippspiels. Mit Zusatzauswertung (showzus) zaehlt die Funktion in $punkte1 bis $punkte6 mit,
// wofuer es Punkte gab; diese Zaehler setzt die Auswertung (lmo-tippsavewert.php) je Tipper zurueck.
function tipppunkte($gta0, $gtb0, $ga0, $gb0, $msieg, $msp, $text0, $text1, $jkspfaktor0, $mtipp) {
  global $tipp_tippmodus, $tipp_entscheidungnv, $tipp_entscheidungie, $tipp_rergebnis, $tipp_rtendenzdiff, $tipp_rtendenz;
  global $tipp_rtor, $tipp_rtendenztor, $tipp_rtendenzremis, $tipp_rremis, $tipp_gtpunkte, $tipp_showzus;
  global $punkte1, $punkte2, $punkte3, $punkte4, $punkte5, $punkte6;

  $regeln = \Lmo\Addon\Tipp\Punkte::ausKonfiguration(array(
    'tippmodus' => $tipp_tippmodus, 'entscheidungnv' => $tipp_entscheidungnv, 'entscheidungie' => $tipp_entscheidungie,
    'rergebnis' => $tipp_rergebnis, 'rtendenzdiff' => $tipp_rtendenzdiff, 'rtendenz' => $tipp_rtendenz, 'rtor' => $tipp_rtor,
    'rtendenztor' => $tipp_rtendenztor, 'rtendenzremis' => $tipp_rtendenzremis, 'rremis' => $tipp_rremis, 'gtpunkte' => $tipp_gtpunkte,
  ));
  list($punktespiel, $zaehler) = $regeln->fuer((int)$gta0, (int)$gtb0, (int)$ga0, (int)$gb0, (int)$msieg, (string)$msp, (string)$text0, (string)$text1, is_numeric($jkspfaktor0) ? +$jkspfaktor0 : 1, $mtipp == 1);
  if ($tipp_showzus == 1) {
    foreach ($zaehler as $nr => $wert) {
      ${'punkte' . $nr} += $wert;
    }
  }
  return $punktespiel;
}

?>
