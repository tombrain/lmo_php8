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
  *
  */
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

$template->setVariable('Spieltageminus', $multi_cfgarray['anzahl_spieltage_zurueck']);
$template->setVariable('Spieltageplus', $multi_cfgarray['anzahl_spieltage_vor']);
// Durchlaufe so oft Ligen vorhanden sind
for ($i = 1; $i <= $anzahl_ligen; $i++) {
    // die Liga mit ihren Spielen (Lmo\Liga\Begegnungen; bis 4.5 die Classlib)
    $lmo_viewer_datei = PATH_TO_LMO . '/' . $dirliga . $fav_liga[$i];
    $lmo_viewer_rohdaten = is_file($lmo_viewer_datei) ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_viewer_datei) : null;
    // Ligenfile vorhanden?
    if ($lmo_viewer_rohdaten !== null) {
        $liga = \Lmo\Liga\Begegnungen::aus($lmo_viewer_rohdaten);
        $lmo_viewer_einstellungen = $lmo_viewer_rohdaten->einstellungen();
        $lmo_viewer_liganame = (string)($lmo_viewer_rohdaten->optionen['Name'] ?? '');
        $lmo_viewer_ligadatum = date((string)$lmo_viewer_einstellungen->wert('DatF'), (int)filemtime($lmo_viewer_datei));
        $template->setCurrentBlock('Liga');  // Äusserer Block für die Liga
        $rounds = $lmo_viewer_einstellungen->wert('Rounds');
        $aktueller_spieltag = $lmo_viewer_einstellungen->wert('Actual');
        $star = $aktueller_spieltag - ($multi_cfgarray['anzahl_spieltage_zurueck']);
        $end = $aktueller_spieltag + $multi_cfgarray['anzahl_spieltage_vor'];
        $ende = '';
        if ($end > $rounds) {
            $ende = $rounds;
        }
        else {
            $ende = $end;
        }
        $start = '';
        if ($star < 1) {
            $start = 1;
        }
        else {
            $start = $star;
        }

        $template->setVariable('Anfangsspieltag', $start);  //Ausgabe Spieltag
        $template->setVariable('Endespieltag', $ende);  //Ausgabe Spieltag

        //all teams
        if ($all_teams == false) {
        }
        else {
            $team_count = $liga->anzahl();
            for ($x = 0; $x < $team_count; $x++) {
                $fav_team[$i][] = $x;
            }
        }
        //Anfang Ergebnisse verlinken
        $template->setVariable('ErgebnisLink', URL_TO_LMO . '/lmo.php?file=' . $fav_liga[$i] . '&amp;action=results');
        $template->setVariable('Liganame', $lmo_viewer_liganame);
        $template->setVariable('Ligadatum', $lmo_viewer_ligadatum);
        for ($spieltag = $start; $spieltag <= $ende; $spieltag++) {
            $template->setCurrentBlock('Spieltag');
            //Anfang Spieltag verlinken
            $spieltag_link = URL_TO_LMO . '/lmo.php?file=' . $fav_liga[$i] . '&amp;action=results&amp;st=' . $spieltag;
            $template->setVariable('AktSpieltag', $spieltag);
            $template->setVariable('AktSpieltagLink', $spieltag_link);
            // mit Spieltagssortierung nach dem Anstoss
            $partien = $liga->spieltag((int)$spieltag, $lmo_viewer_einstellungen->wert('enableGameSort') == '1');

            $template->setCurrentBlock('Inhalt');  //Ausgabe Inhalt Beginn
            foreach ($partien as $partie) {
                require(PATH_TO_ADDONDIR . '/viewer/viewer_spiel.inc.php');
            }
            $template->parse('Spieltag');
        }
        $template->setVariable('VERSION', VIEWER_VERSION);
        $template->parse('Liga');
    }
    else  {
        echo getMessage($text['viewer'][49] . ' . $fav_liga[$i] . ' . $text['viewer'][50], true); // Ligenfile vorhanden? Frage beantwortet
    }
}
?>
