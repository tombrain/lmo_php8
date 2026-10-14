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
 * Die Tabelle der Liga (Lmo\Web\Seiten\TabellenSeite mit der Vorlage vorlagen/tabelle.html.twig).
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $diroutput Ordner fuer erzeugte Dateien (Konfiguration)
 * @var string $addt1 Link zur Tabelle, es fehlt nur die Tabellenart (lmo-showrestab.php)
 * @var int|string $tabtype Tabellenart: 0 gesamt, 1 heim, 2 auswaerts, 3 Rueckrunde, 4 Hinrunde
 * @var int|string $tabonres Einstellung tabonres (Konfiguration)
 * @var int|string $tabpkt Punktespalte: 0 in der Mitte, 1 am rechten Rand (Konfiguration)
 * @var string $tabdat Stand der Tabelle als Text, leer fuer den aktuellen Stand
 * @var int|string $endtab Spieltag, bis zu dem die Tabelle gerechnet wird
 * @var int|string $einzutoretab Torstatistik unter der Tabelle (Konfiguration)
 * @var int|float|string $gzutore Tore der ganzen Saison (Statistikdatei, lmo-zustat.php)
 * @var int|float|string $gdstore Tore je Spiel der ganzen Saison (Statistikdatei, lmo-zustat.php)
 */

// Torstatistik der Saison unter der Tabelle, aus der Statistikdatei (lmo-zustat.php)
$lmo_torstatistik = '';
if ($einzutoretab == 1) {
    $zustat_file = str_replace('.l98', '.l98.php',  basename($file));
    $zustat_dir = basename($diroutput);
    if (file_exists(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file)) {
        require(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file);
        $lmo_torfaktor = $lmo_liga->einstellungen()->torfaktor();
        $lmo_torstatistik = $text[4000] . $text[38] . ': ' . applyFactor($gzutore, $lmo_torfaktor) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $text[38] . '&nbsp;' . $text[4001] . ': ' . applyFactor($gdstore, $lmo_torfaktor);
    }
}

$lmo_darstellung = new \Lmo\Web\Darstellung(static fn (string $datei, string $name, string $attribute): string => (string)HTML_smallTeamIcon($datei, $name, $attribute));
$lmo_tabellenseite = new \Lmo\Web\Seiten\TabellenSeite($lmo_liga, $text, $lmo_darstellung, PATH_TO_LMO . '/vorlagen', URL_TO_IMGDIR);
echo $lmo_tabellenseite->zeige((int)$tabtype, (int)$endtab, (string)$tabdat, $addt1, $file, (int)$tabonres, (int)$tabpkt, $lmo_torstatistik);
