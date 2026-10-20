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
 * @var int|string $calctime Einstellung aus cfg.txt
 * @var int|string $einsprachwahl Einstellung aus cfg.txt
 *
 * Weitere Variablen des einbindenden Codes:
 * @var mixed $startzeit
 *
 * Werden auch in dieser Datei gesetzt, aber nicht auf jedem Weg (koennen von aussen kommen):
 * @var mixed $ste
 */



// Adresse fuer Links der Liga; der LMO selbst braucht sie nicht mehr, Addons nach alter Art (pdf) vielleicht
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

if (!defined('LMO_TEMPLATE')) define('LMO_TEMPLATE', 'lmo-standard.tpl.php');

// Wenn ein Template der Form [liganame].tpl.php existiert, wird dieses benutzt. Das ermöglicht
// die Nutzung verschiedener Templates für unterschiedliche Ligen. Die mitgelieferten Vorlagen
// ersetzt der Rahmen mit Twig (Lmo\Web\Rahmen); eigene nach alter Art gelten weiter (veraltet).
$lmo_alte_vorlage = \Lmo\Web\Rahmen::alteVorlage(PATH_TO_TEMPLATEDIR, $file, LMO_TEMPLATE);

// Copyright-Hinweis
$p1 = '';
if ($file != '') {
    $p0 = 'p1';
    $$p0 = c(1).c(0);
}

// die Seite: Controller des Kerns und der Addons (Lmo\Web\Anwendung), auch die Ligenuebersicht
ob_start();
$lmo_druckbar = false;
$lmo_seite = $lmo_anwendung->zeige($lmo_kontext);
if ($lmo_seite !== null) {
    echo $lmo_seite->html;
    $lmo_druckbar = $lmo_seite->druckbar;
}
$lmo_hauptteil = (string) ob_get_contents();
ob_end_clean();

// die Teile des Kerns: Kopf, Menue, Hauptteil, Druckansicht, Fusszeile (Lmo\Web\Rahmen)
$lmo_teile = \Lmo\Web\Rahmen::teile(
    $lmo_kontext,
    $lmo_hauptteil,
    $lmo_druckbar,
    $einsprachwahl == 1 ? getLangSelector() : '',
    $calctime == 1 ? getmicrotime() - $startzeit : null
);

if ($file != '' && isset($stand)) {
    // PDF-Addon (nach alter Art, nicht mitgeliefert: ergaenzt $output_savehtml)
    $druck = $lmo_druckbar ? 1 : 0;
    $output_savehtml = $lmo_teile['Savehtml'];
    if (file_exists(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php')) include(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php');
    $lmo_teile['Savehtml'] = $output_savehtml;
    // PDF-Addon
}

// Seite ausgeben: die Teile des Rahmens, wie die Platzhalter der Vorlagen heissen; dazu der
// Copyright-Hinweis
$lmo_teile += [
    'Infolink' => $p1,
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
    // jeder Platzhalter wird ersetzt, auch die der Teile, die es auf dieser Seite nicht gibt
    $lmo_teile += array_fill_keys(\Lmo\Web\Rahmen::TEILE, '');
    foreach ($lmo_teile as $lmo_teil => $lmo_wert) {
        $template->setVariable($lmo_teil, $lmo_wert);
    }
    $template->show();
}

?>
