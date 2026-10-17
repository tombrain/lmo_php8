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
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var int|string $st gewaehlter Spieltag
 * @var string $action gewaehlte Seite (Aufrufparameter)
 *
 * Einstellungen:
 * @var int|string $backlink Einstellung aus cfg.txt
 * @var int|string $calctime Einstellung aus cfg.txt
 * @var int|string $einspieler Einstellung aus cfg.txt
 * @var int|string $einsprachwahl Einstellung aus cfg.txt
 * @var int|string $eintippspiel Einstellung aus cfg.txt
 * @var int|string $tabonres Einstellung aus cfg.txt
 * @var int|string $tipp_immeralle Einstellung aus tipp/cfg.txt
 * @var int|string $tipp_ligenzutippen Einstellung aus tipp/cfg.txt
 *
 * Weitere Variablen des einbindenden Codes:
 * @var mixed $spieler_ligalink
 * @var mixed $startzeit
 *
 * Werden auch in dieser Datei gesetzt, aber nicht auf jedem Weg (koennen von aussen kommen):
 * @var mixed $ste
 */



$addm = $_SERVER['PHP_SELF'] . '?file=' . $file . '&amp;action=';
// Einstellungen der geladenen Liga mit Vorgabewerten (Lmo\Liga\Einstellungen). Ohne geladene Liga die einer
// leeren Liga; eingebundene Seiten und das Tippspiel fuehren ihre eigenen, deshalb ein eigener Name.
$lmo_main_e = (new \Lmo\Liga\Rohdaten\Liga())->einstellungen();
if ($file != '') {
    require(PATH_TO_LMO . '/lmo-openfile.php');
    if ($file != '' && isset($lmo_liga)) {
        $lmo_main_e = $lmo_liga->einstellungen();
    }
    if ($file != '') {
        if (empty($_GET['endtab'])) {
            $endtab = $lmo_main_e->spieltage();
            $ste = $st;
            $tabdat = '';
        } else {
            $endtab = $_GET['endtab'];
            $tabdat = $endtab . '. ' . $text[2];
            $ste = $endtab;
        }
        if (!empty($_GET['st'])) {
            $tabdat = $st . '. ' . $text[2];
        }
        if (empty($_REQUEST['action']) && $action == '') {
            switch ($lmo_main_e->laufendeTabelle()) {
                case '0': $action = 'results';break;
                case '1': $action = 'table';break;
                case '2': $action = 'program';break;
                case '3': $action = 'cross';break;
                case '4': $action = 'graph';break;
                case '5': $action = 'stats';break;
                case '6': $action = 'cal';break;
                case '7': $action=  'spieler';break;
            }
        }
    }
}

// die Anwendung (Lmo\Web\Anwendung) und der Kontext dieser Anfrage: fuer die Seiten mit eigenem
// Controller und das Menue
$lmo_anwendung = require(PATH_TO_LMO . '/lmo-anwendung.php');
$lmo_kontext = $lmo_anwendung->kontext(\Lmo\Web\Anfrage::ausGlobalen(), $_SERVER['PHP_SELF'], $file);

// Alle Teile für die Startansicht
$output_titel = '';
$output_html_lang = '';
$output_sprachauswahl = '';
$output_kalender = '';
$output_ergebnisse = '';
$output_tabelle = '';
$output_spielplan = '';
$output_kreuztabelle = '';
$output_fieberkurve = '';
$output_ligastatistik = '';
$output_spielerstatistik = '';
$output_info = '';
$output_hauptteil = '';
$output_ligenuebersicht = '';
$output_savehtml = '';
$output_letzteauswertung = '';
$output_berechnungszeit = '';
$output_stylesheet = '';
$output_javascript = '';
$p1 = '';

if (!defined('LMO_TEMPLATE')) define('LMO_TEMPLATE', 'lmo-standard.tpl.php');

// Wenn ein Template der Form [liganame].tpl.php existiert, wird dieses benutzt. Das ermöglicht
// die Nutzung verschiedener Templates für unterschiedliche Ligen. Die mitgelieferten Vorlagen
// ersetzt der Rahmen mit Twig (Lmo\Web\Rahmen); eigene nach alter Art gelten weiter (veraltet).
$lmo_alte_vorlage = \Lmo\Web\Rahmen::alteVorlage(PATH_TO_TEMPLATEDIR, $file, LMO_TEMPLATE);

// Titel
if ($file != '') {
    $output_titel .= $lmo_main_e->name();
} else {
    $output_titel .= $action == 'tipp' ? $text['tipp'][0] : $text[53];
}

// Output <html lang=""> with ISO language codes in Template
$output_html_lang .= $text[798];

// Stylesheet
$output_stylesheet .= '  <link type="text/css" rel="stylesheet" href="' . URL_TO_LMO . '/lmo-style-nc.php">
  <style type="text/css">@import url("' . URL_TO_LMO . '/lmo-style.php");</style>';

// Javascript
$output_javascript .= '<!-- Dieser Container dient als das schwebende Fenster -->
<div id="globalLMOTooltip" class="lmo-smart-tooltip"></div>

<script>
// Das Tooltip-Element referenzieren
const sportTooltip = document.getElementById("globalLMOTooltip");

function showLMOTooltip(element) {
  // Text aus dem data-info Attribut auslesen
  const infoText = element.getAttribute("data-info");
  if (!infoText) return;

  // Inhalt in das Fenster schreiben und sichtbar machen
  sportTooltip.innerHTML = infoText;
  sportTooltip.style.visibility = "visible";
  sportTooltip.style.opacity = "1";

  // Position des SVG-Icons auf dem Bildschirm ermitteln
  const rect = element.getBoundingClientRect();
  const winW = window.innerWidth;
  const winH = window.innerHeight;

  // Standard-Position: Direkt unter dem Icon (8px Abstand)
  let top = rect.bottom + window.scrollY + 8;
  let left = rect.left + window.scrollX;

  // Fixed Koordinaten
  sportTooltip.style.position = "fixed";
  top = rect.bottom + 8;
  left = rect.left;

  // AUTO-KORREKTUR: Unten
  if (top + sportTooltip.offsetHeight > winH) {
    // Über dem Icon anzeigen
    top = rect.top - sportTooltip.offsetHeight - 8;
  }

  // AUTO-KORREKTUR: Rechts
  if (left + sportTooltip.offsetWidth > winW) {
    // Nach links verschieben, mit 15px Sicherheitsabstand zum Rand
    left = winW - sportTooltip.offsetWidth - 15;
  }

  // Falls das Icon ganz links am Rand ist, Mindestabstand einhalten
  if (left < 10) left = 10;

  // Positionswerte zuweisen
  sportTooltip.style.top = top + "px";
  sportTooltip.style.left = left + "px";
}

function hideLMOTooltip() {
  // Fenster wieder ausblenden
  sportTooltip.style.visibility = "hidden";
  sportTooltip.style.opacity = "0";
}
</script>';

// Sprachauswahl
if ($einsprachwahl == 1) {
    $output_sprachauswahl = getLangSelector();
}

ob_start();
if ($file != '') {
    // das Menue der Liga (Lmo\Web\Seiten\Hauptmenue)
    $lmo_menue = \Lmo\Web\Seiten\Hauptmenue::teile($lmo_kontext);
    $output_kalender .= $lmo_menue['Kalender'];
    $output_ergebnisse .= $lmo_menue['Ergebnisse'];
    $output_tabelle .= $lmo_menue['Tabelle'];
    $output_spielplan .= $lmo_menue['Spielplan'];
    $output_kreuztabelle .= $lmo_menue['Kreuztabelle'];
    $output_fieberkurve .= $lmo_menue['Fieberkurve'];
    $output_ligastatistik .= $lmo_menue['Ligastatistik'];
    $output_info .= $lmo_menue['Info'];

    $druck = 0;
    $p0 = 'p1';
    $$p0 = c(1).c(0);
}

// die Seite: Controller des Kerns und der Addons (Lmo\Web\Anwendung), auch die Ligenuebersicht
$lmo_seite = $lmo_anwendung->zeige($lmo_kontext);
if ($lmo_seite !== null) {
    echo $lmo_seite->html;
    $druck = $lmo_seite->druckbar ? 1 : 0;
} elseif ($file != '' && $action == 'spieler') {
    // Spieler-Addon (nach alter Art: seine Funktionen lesen Variablen mit global, deshalb hier eingebunden)
    if ($lmo_main_e->mitToren()) {
        require(PATH_TO_ADDONDIR . '/spieler/lmo-statshow.php');
    }
}
// Tippspiel-Addon (nach alter Art, wie das Spieler-Addon)
if ($action == 'tipp' && $eintippspiel == 1) {require(PATH_TO_ADDONDIR . '/tipp/lmo-tippstart.php');}
$output_hauptteil .= ob_get_contents();
ob_end_clean();

// Links zur Druckansicht (Lmo\Web\Seiten\Druckansicht); die Dateien schreibt das Speichern der Liga
$output_savehtml .= \Lmo\Web\Seiten\Druckansicht::html($lmo_kontext, isset($druck) && $druck == 1);
if ($file != '' && isset($stand)) {
    // PDF-Addon (nach alter Art, nicht mitgeliefert: sieht alle Variablen dieser Datei, z. B. $output_savehtml)
    if (file_exists(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php')) include(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php');
    // PDF-Addon
}

// Fusszeile: Ligenuebersicht, letzte Auswertung, Berechnungszeit (Lmo\Web\Seiten\Fusszeile)
$lmo_fusszeile = \Lmo\Web\Seiten\Fusszeile::teile($lmo_kontext, $backlink == 1, $calctime == 1 ? getmicrotime() - $startzeit : null);
$output_ligenuebersicht .= $lmo_fusszeile['Ligenuebersicht'];
$output_letzteauswertung .= $lmo_fusszeile['LetzteAuswertung'];
$output_berechnungszeit .= $lmo_fusszeile['Berechnungszeit'];

// Spieler-Addon
if ($file != '' && $einspieler == 1 && $lmo_main_e->mitToren()) {
    include(PATH_TO_ADDONDIR . '/spieler/lmo-statloadconfig.php');
    $output_spielerstatistik .= $action != 'spieler' ? '<a href="' . $addm . 'spieler" title="' . $text['spieler'][12] . '">' . $spieler_ligalink . '</a>' : $spieler_ligalink;
    $output_spielerstatistik .= '&nbsp;&nbsp;';
}

// Ticker-Addon
$output_newsticker = '';
if ($file != '' && $lmo_main_e->mitNewsticker()) {
    ob_start();
    $tickerligen = $file;
    include(PATH_TO_ADDONDIR . '/ticker/ticker.php');
    $output_newsticker .= ob_get_contents();
    ob_end_clean();
}

// Tippspiel-Addon
$output_tippspiel = '';
if ($eintippspiel == 1) {
    if ($tipp_immeralle == 1 || str_contains($tipp_ligenzutippen, substr(basename($file), 0, -4))) {
        $output_tippspiel .= $action != 'tipp' ? '<a href="' . $addm . 'tipp" title="' . $text['tipp'][0] . '">' . $text['tipp'][0] . '</a>' : $text['tipp'][0];
    }
}

// Seite ausgeben: die Teile des Rahmens, wie die Platzhalter der Vorlagen heissen
$lmo_teile = [
    'Ligenuebersicht' => $output_ligenuebersicht,
    'Berechnungszeit' => $output_berechnungszeit,
    'LetzteAuswertung' => $output_letzteauswertung,
    'Savehtml' => $output_savehtml,
    'Hauptteil' => $output_hauptteil,
    'Tabelle' => $output_tabelle,
    'Kreuztabelle' => $output_kreuztabelle,
    'Fieberkurve' => $output_fieberkurve,
    'Spielerstatistik' => $output_spielerstatistik,
    'Ligastatistik' => $output_ligastatistik,
    'Kalender' => $output_kalender,
    'Ergebnisse' => $output_ergebnisse,
    'Spielplan' => $output_spielplan,
    'Info' => $output_info,
    'HTML_LANG' => $output_html_lang,
    'Infolink' => $p1,
    'Sprachauswahl' => $output_sprachauswahl,
    'Titel' => $output_titel,
    'Stylesheet' => $output_stylesheet,
    'Javascript' => $output_javascript,
    'Newsticker' => $output_newsticker,  // Ticker-Addon
    'Tippspiel' => $output_tippspiel,    // Tippspiel-Addon
];
// Teile, die Addons nach neuer Art zum Rahmen beitragen (Lmo\Addon\MitRahmenteilen), stehen dahinter
foreach ($lmo_kontext->addons->rahmenteile($lmo_kontext) as $lmo_teil => $lmo_wert) {
    $lmo_teile[$lmo_teil] = ($lmo_teile[$lmo_teil] ?? '') . $lmo_wert;
}
if (\Lmo\Web\Rahmen::ersetzt($lmo_alte_vorlage)) {
    echo \Lmo\Web\Rahmen::zeige(PATH_TO_LMO . '/vorlagen', $lmo_alte_vorlage, $lmo_teile);
} else {
    // eigene Vorlage nach alter Art (veraltet): fehlt ihr der Platzhalter Infolink, gibt d() den Copyright-Hinweis aus
    $template = new LMO_Template(PATH_TO_TEMPLATEDIR);
    $template->loadTemplatefile($lmo_alte_vorlage);
    d($template->toString());
    foreach ($lmo_teile as $lmo_teil => $lmo_wert) {
        $template->setVariable($lmo_teil, $lmo_wert);
    }
    $template->show();
}

?>
