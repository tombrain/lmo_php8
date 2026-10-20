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

if (!function_exists('getmicrotime')) {
    function getmicrotime()
    {
        list($usec, $sec) = explode(' ' ,microtime());
        return ((float)$usec + (float)$sec);
    }
}
$startzeit = getmicrotime();
require(__DIR__ . '/init.php');

if((isset($_REQUEST['action']) && $_REQUEST['action'] == 'tipp') && session_id() == '') {
    session_start();
}

$array = array();
$ftype = '.l98';
if (isset($_REQUEST['st'])) {$st = $_REQUEST['st'];}
if(!isset($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
    $action = '';
} else {
    $action = $_REQUEST['action'];
}
if (!isset($file)) {
    if(!isset($_REQUEST['file'])) {
        $_REQUEST['file'] = '';
        $file = '';
    } else {
        $file = $_REQUEST['file'];
    }
}
$subdir = isset($_REQUEST['subdir']) ? $_REQUEST['subdir'] : '';

if($_REQUEST['action'] == 'admin') {
    $_REQUEST['action'] = '';
    $action = '';
}

/**
 * Von init.php:
 *
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $calctime Einstellung aus cfg.txt
 * @var int|string $einsprachwahl Einstellung aus cfg.txt
 */

// die Anwendung (Lmo\Web\Anwendung) und der Kontext dieser Anfrage
$lmo_anwendung = require(PATH_TO_LMO . '/lmo-anwendung.php');
$lmo_kontext = $lmo_anwendung->kontext(\Lmo\Web\Anfrage::ausGlobalen(), $_SERVER['PHP_SELF'], $file);
if ($file != '' && $lmo_kontext->liga === null) {
    // Datei existiert nicht, ist keine Liga oder darf nicht gelesen werden (Hilfsadmin): wie bisher
    // die Meldung vor der Seite und weiter ohne Liga
    echo getMessage($text[224], true);
    $file = '';
}

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

// die Seite: Controller des Kerns und der Addons (Lmo\Web\Anwendung)
$lmo_seite = $lmo_anwendung->zeige($lmo_kontext);

// die Teile des Kerns: Kopf, Menue, Hauptteil, Druckansicht, Fusszeile (Lmo\Web\Rahmen)
$lmo_teile = \Lmo\Web\Rahmen::teile(
    $lmo_kontext,
    $lmo_seite !== null ? $lmo_seite->html : '',
    $lmo_seite !== null && $lmo_seite->druckbar,
    $einsprachwahl == 1 ? getLangSelector() : '',
    $calctime == 1 ? getmicrotime() - $startzeit : null
);

// PDF-Addon (nach alter Art, nicht mitgeliefert): ergaenzt die Links zur Druckansicht ($output_savehtml)
if ($lmo_kontext->liga !== null && file_exists(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php')) {
    $lmo_teile['Savehtml'] = (static function (\Lmo\Web\GeladeneLiga $lmo_geladen, string $output_savehtml, int $druck): string {
        // was es aus dem globalen Bereich erwartet (Einstellungen, $text); dazu die Liga wie aus lmo-openfile.php
        foreach (array_keys($GLOBALS) as $lmo_pdf_name) {
            if ($lmo_pdf_name !== 'GLOBALS' && $lmo_pdf_name[0] !== '_' && !in_array($lmo_pdf_name, array('lmo_geladen', 'output_savehtml', 'druck'), true)) {
                ${$lmo_pdf_name} = &$GLOBALS[$lmo_pdf_name];
            }
        }
        unset($lmo_pdf_name);
        $lmo_liga = $lmo_geladen->liga;
        $file = $lmo_geladen->datei;
        $stand = $lmo_geladen->stand;
        include(PATH_TO_ADDONDIR . '/pdf/lmo-showmain2.inc.php');
        return $output_savehtml;
    })($lmo_kontext->liga, $lmo_teile['Savehtml'], $lmo_seite !== null && $lmo_seite->druckbar ? 1 : 0);
}

// Seite ausgeben: die Teile des Rahmens, wie die Platzhalter der Vorlagen heissen; dazu der
// Copyright-Hinweis
$lmo_teile['Infolink'] = $p1;
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