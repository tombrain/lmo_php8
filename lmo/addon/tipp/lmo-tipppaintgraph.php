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
  
  

require_once(__DIR__.'/../../init.php');

// das Bild der Tipp-Fieberkurve (Lmo\Addon\Tipp\Fieberbild) aus den Parametern der Adresse und den Farben des Designs
$lmo_bild_werte = array();
foreach (array('pganz', 'pgteam1', 'pgteam2', 'max', 'pgst', 'pgplatz1', 'pgplatz2', 'pgplatz1a', 'pgplatz2a', 'kmodus', 'pgtext1', 'pgtext2') as $lmo_bild_name) {
  if (isset($_GET[$lmo_bild_name]) && is_string($_GET[$lmo_bild_name])) {
    $lmo_bild_werte[$lmo_bild_name] = $_GET[$lmo_bild_name];
  }
}
$lmo_bild_farben = array();
foreach (array('lmo_inner_background1', 'lmo_inner_color1', 'lmo_tabelle_background1', 'lmo_tabelle_background2', 'lmo_tabelle_background3') as $lmo_bild_name) {
  $lmo_bild_farben[$lmo_bild_name] = isset($$lmo_bild_name) ? (string)$$lmo_bild_name : null;
}
header("Content-Type: image/png");
echo \Lmo\Addon\Tipp\Fieberbild::png($lmo_bild_werte, $lmo_bild_farben);
