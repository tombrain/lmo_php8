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

/**
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var array<int|string, mixed> $text Sprachtexte
 *
 * Weitere Variablen des einbindenden Codes:
 * @var array<int|string, mixed> $fav_liga
 * @var array<int|string, mixed> $fav_team
 * @var mixed $i
 * @var \Lmo\Liga\Begegnungen $liga
 * @var \Lmo\Liga\Einstellungen $lmo_viewer_einstellungen
 * @var string $lmo_viewer_liganame
 * @var string $lmo_viewer_ligadatum
 * @var array<int|string, mixed>|null $multi_cfgarray
 * @var \Lmo\Liga\Begegnung $partie
 * @var mixed $template
 *
 * Werden auch in dieser Datei gesetzt, aber nicht auf jedem Weg (koennen von aussen kommen):
 * @var mixed $spieltag
 */

foreach ($fav_team[$i] as $akt_team) {
    if (isset($multi_cfgarray['modus']) && $multi_cfgarray['modus'] == 1) {
        $spieltag = $partie->spieltag;
    }
    $mhp_link_s = $mgp_link_s = $mhp_link_e = $mgp_link_e = '';
    if (($akt_team == $partie->heim) || ($akt_team == $partie->gast)) {
        // die beiden Mannschaften, wie sie in der Datei stehen
        $lmo_viewer_heim = $liga->daten($partie->heim);
        $lmo_viewer_gast = $liga->daten($partie->gast);
        $lmo_viewer_bilder = new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR);

        //Anfang Relevante Daten
        $lmo_viewer_torfaktor = (string)$lmo_viewer_einstellungen->wert('goalfaktor');
        $template->setVariable('Liganame', $lmo_viewer_liganame);
        $template->setVariable('Ligadatum', $lmo_viewer_ligadatum);
        $template->setVariable('Spieltag', $multi_cfgarray['spieltagtext'] . ' ' . $spieltag);
        $template->setVariable('Datum', $partie->datum('-', $multi_cfgarray['datumsformat']));
        $template->setVariable('Uhrzeit', $partie->uhrzeit('-', $multi_cfgarray['uhrzeitformat']));
        $template->setVariable('Tore', $partie->toreHeimText($multi_cfgarray['tordummy'], $lmo_viewer_torfaktor) . ' : ' . $partie->toreGastText($multi_cfgarray['tordummy'], $lmo_viewer_torfaktor) . ' ' . $partie->spielendeText($text));
        //Heim & Gasttore einzeln
        $template->setVariable('ToreHeim', $partie->toreHeimText($multi_cfgarray['tordummy']));
        $template->setVariable('ToreGast', $partie->toreGastText($multi_cfgarray['tordummy']));
        //Ende Relevante Daten

        //Neu TeamIcons Heim fuer Bild alt /Anpassung Apache2
        $Heim = (string)$lmo_viewer_heim->name;
        $Gast = (string)$lmo_viewer_gast->name;
        $template->setVariable('Iconheim', $lmo_viewer_bilder->bild($Heim, 'teams', 'small'));
        $template->setVariable('Icongast', $lmo_viewer_bilder->bild($Gast, 'teams', 'small'));
        $template->setVariable('IconMiddleheim', $lmo_viewer_bilder->bild($Heim, 'teams', 'middle'));
        $template->setVariable('IconMiddlegast', $lmo_viewer_bilder->bild($Gast, 'teams', 'middle'));
        $template->setVariable('IconBigheim', $lmo_viewer_bilder->bild($Heim, 'teams', 'big'));
        $template->setVariable('IconBiggast', $lmo_viewer_bilder->bild($Gast, 'teams', 'big'));
        //Ende TeamIcons

        $mhp_link_s = '';
        $mhp_link_e = '';
        $mgp_link_s = '';
        $mgp_link_e = '';
        //HP verlinken
        if (isset($multi_cfgarray['mannschaftshomepages_verlinken']) && $multi_cfgarray['mannschaftshomepages_verlinken']==1) {
            if (($lmo_viewer_heim->werte['URL'] ?? '') != '') {
            $mhp_link_s = '<a href="' . $lmo_viewer_heim->werte['URL'] . '" target="_blank">';
            $mhp_link_e = '</a>';
            }
            if (($lmo_viewer_gast->werte['URL'] ?? '') != '') {
            $mgp_link_s = '<a href="' . $lmo_viewer_gast->werte['URL'] . '" target="_blank">';
            $mgp_link_e = '</a>';
            }
        }//Ende HP verlinken

        //Favteam hervorheben
        if (isset($multi_cfgarray['favteam_highlight']) && $multi_cfgarray['favteam_highlight'] == 1) {
            if ($partie->heim == $lmo_viewer_einstellungen->wert('favTeam')) {
                $mhp_link_s = '<strong>' . $mhp_link_s;
                $mhp_link_e .= '</strong>';
            }
            if ($partie->gast == $lmo_viewer_einstellungen->wert('favTeam')) {
                $mgp_link_s = '<strong>' . $mgp_link_s;
                $mgp_link_e .= '</strong>';
            }
        }  //Ende Favteam hervorheben

        //Trotz Konfigwert auch andere Variablen zur Verfügung stellen
        $template->setVariable('HeimKurz', (string)$lmo_viewer_heim->kurz);
        $template->setVariable('GastKurz', (string)$lmo_viewer_gast->kurz);
        $template->setVariable('HeimMittel', (string)$lmo_viewer_heim->mittel);
        $template->setVariable('GastMittel', (string)$lmo_viewer_gast->mittel);
        $template->setVariable('HeimLang', $Heim);
        $template->setVariable('GastLang', $Gast);
        //Neu wegen Auswahl Mittellanger Teamnamen
        if ($multi_cfgarray['mannschaftsnamen'] == 2) {
            $template->setVariable('Heim', $mhp_link_s . $lmo_viewer_heim->mittel . $mhp_link_e);
            $template->setVariable('Gast', $mgp_link_s . $lmo_viewer_gast->mittel . $mgp_link_e);
        }
        elseif ($multi_cfgarray['mannschaftsnamen'] == 1) {
            $template->setVariable('Heim', $mhp_link_s . $lmo_viewer_heim->kurz . $mhp_link_e);
            $template->setVariable('Gast', $mgp_link_s . $lmo_viewer_gast->kurz . $mgp_link_e);
        }
        else {
            $template->setVariable('Heim', $mhp_link_s . $Heim . $mhp_link_e);
            $template->setVariable('Gast', $mgp_link_s . $Gast . $mgp_link_e);
        }  //Ende Mannschaftsnamen

        //Anfang Notitz
        if (trim($partie->notiz) != '') {
            $icon = URL_TO_IMGDIR . '/viewer/' . $multi_cfgarray['notizsymbol'];
            $ntext = '<a href="#" title="' . $partie->notiz . '" onclick="alert(\'' . $text[22] . ': ' . $partie->notiz . '\');window.focus();return false;"><img src="' . $icon . '" border="0" alt=""></a>';
            $template->setVariable('Notiz', $ntext);
        }//Ende Notiz

        //Anfang Tabelle verlinken
        $table_link = URL_TO_LMO . '/lmo.php?file=' . $fav_liga[$i] . '&amp;action=table&amp;st=' . $spieltag;
        if ($multi_cfgarray['tabelle_verlinken'] == '1') {
            $tlink = '<a href="' . $table_link . '" target="_blank" title="' . $text['viewer'][35] . ' (' . $text['viewer'][33] . ')"><img src="' . URL_TO_IMGDIR . '/viewer/' . $multi_cfgarray['tabellensymbol'] . '" border="0" alt="#"></a>';
        }
        else {
            $tlink = '<a href="' . $table_link . '" title="' . $text['viewer'][35] . ' (' . $text['viewer'][34] . ')"><img src="' . URL_TO_IMGDIR . '/viewer/' . $multi_cfgarray['tabellensymbol'] . '" border="0" alt="#"></a>';
        }
        $template->setVariable('Tabellenlink', $tlink);
        //Ende Tabelle

        //Anfang Spieltag verlinken
        //Im Unterschied zu den anderen Links wird nur die URL gesetzt, nicht der komplette Link
        //Rest wird über das Template gesteuert
        $spieltag_link = URL_TO_LMO . '/lmo.php?file=' . $fav_liga[$i] . '&amp;action=results&amp;st=' . $spieltag;
        $template->setVariable('SpieltagLink', $spieltag_link);
        //Ende Spieltag

        //Anfang Spielbericht
        $SpBer_link = $partie->bericht;
        if ($SpBer_link != '') {
            if ($multi_cfgarray['spielberichte_neues_fenster'] == '1' ) {
                $tlink = '<a href="' . $SpBer_link . '" target="_blank" title="' . $text['viewer'][38] . ' (' . $text['viewer'][33] . ')"><img src="' . URL_TO_IMGDIR . '/viewer/' . $multi_cfgarray['spielberichtesymbol'] . '" border="0" alt="#"></a>';
            }
            else {
                $tlink = '<a href="' . $SpBer_link . '" title="' . $text['viewer'][38] . ' (' . $text['viewer'][34] . ')"><img src="' . URL_TO_IMGDIR . '/viewer/' . $multi_cfgarray['spielberichtesymbol'] . '" border="0" alt="#"></a>';
            }
            $template->setVariable('Spielbericht', $tlink);
        }//Ende Spielbericht

        //Anfang Spiele Heute hervorheben
        if ($multi_cfgarray['heute_highlight'] == 1) {
            if ($partie->zeit > zeitberechnung('2', -1) && $partie->zeit < zeitberechnung('2', 0)) {
                $template->setvariable('Zeilenklasse', 'vRowHighlight');
            }
            else {
                $template->setvariable('Zeilenklasse', 'vRow');
            }
        }  //Ende Spiele Heute hervorheben
    }
}
$template->parseCurrentBlock();
?>
