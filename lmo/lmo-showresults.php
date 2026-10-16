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
 * Die Ergebnisse eines Spieltags (Lmo\Web\Seiten\ErgebnisListe mit der Vorlage vorlagen/ergebnisse.html.twig).
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $st gewaehlter Spieltag
 * @var string $addp Link zum Spielplan einer Mannschaft, es fehlt nur die Nummer
 * @var string $defdateformat Datumsformat in date()-Schreibweise (Konfiguration)
 * @var string $diroutput Ordner fuer erzeugte Dateien (Konfiguration)
 * @var int|string $einzutore Torstatistik unter den Ergebnissen (Konfiguration)
 * @var int|string $einspielfrei Einstellung einspielfrei (Konfiguration)
 * @var array<int|string, mixed> $zutore Tore je Spieltag (lmo-zustat.php)
 * @var array<int|string, mixed> $dstore Tore je Spiel je Spieltag (lmo-zustat.php)
 */

// Torstatistik des Spieltags unter den Ergebnissen, aus der Statistikdatei (lmo-zustat.php)
$lmo_torstatistik_spieltag = null;
if ($einzutore == 1) {
    $lmo_torstatistik_spieltag = '';
    $zustat_file = str_replace('.l98', '.l98.php',  basename($file));
    $zustat_dir = basename($diroutput);
    if (file_exists(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file)) {
        require(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file);
        $lmo_torfaktor = $lmo_liga->einstellungen()->torfaktor();
        $lmo_torstatistik_spieltag = $text[38] . ': ' . applyFactor($zutore[$st], $lmo_torfaktor) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $text[38] . $text[4001] . ': ' . applyFactor($dstore[$st], $lmo_torfaktor);
    }
}

// bisherige Ergebnisse je Spiel (AddOn Team Vergleich, nicht Teil des LMO); $i wie bisher: Partie ab 0
$lmo_addon_vergleich = null;
if (file_exists(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php')) {
    $lmo_addon_vergleich = static function (\Lmo\Liga\Spiel $lmo_spiel) use ($lmo_liga, $text, $st, $file): string {
        $i = $lmo_spiel->partie - 1;
        ob_start();
        include(PATH_TO_ADDONDIR . '/stats/lmo-showprogram.inc.php');

        return (string)ob_get_clean();
    };
}

$lmo_darstellung = new \Lmo\Web\Darstellung(static fn (string $datei, string $name, string $attribute): string => (string)HTML_smallTeamIcon($datei, $name, $attribute));
$lmo_ergebnisliste = new \Lmo\Web\Seiten\ErgebnisListe($lmo_liga, $text, $lmo_darstellung, PATH_TO_LMO . '/vorlagen', URL_TO_IMGDIR);
echo $lmo_ergebnisliste->zeige((int)$st, $file, $addp, $defdateformat, $lmo_torstatistik_spieltag, $einspielfrei == 1, $lmo_addon_vergleich);
