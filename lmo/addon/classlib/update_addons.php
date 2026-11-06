<?php
/**
 * Updatefunktion für Addons
 *
 * @param string $fileName
 */
function updateAddons($fileName) {
    include PATH_TO_LMO . '/init.php';
    //Importieren der Konfigurations-Variablen. Alternativ müssten alle gebrauchten Variabeln hier als global deklariert werden
    include PATH_TO_LMO . '/lmo-cfgload.php';
    // die Sprachtexte (fuer die Auswertungen der Addons)
    global $text;

    //Falls diese Datei geändert wird, empfiehlt es sich, alle Fehler
    //anzeigen zu lassen, um evtl. nicht gesetzte Variablen frühzeitig zu erkennen
    //error_reporting(E_ALL);
    $liga = basename($fileName, '.l98');
    // $file relativ zum Ligenverzeichnis wie im restlichen LMO; mit basename() wurden Ligen in
    // Unterordnern nicht gefunden (HTML-Export und Statistik liefen dann ohne Daten)
    $ligenDir = PATH_TO_LMO . '/' . $dirliga;
    $file = strpos($fileName, $ligenDir) === 0 ? substr($fileName, strlen($ligenDir)) : $liga . '.l98';
    $action = 'admin';
    $array = array();
    //Liga-Datei öffnen
    include PATH_TO_LMO . '/lmo-openfile.php';

    // SAVEHTML
    //HTML-Ausgabe erstellen
    if ($einsavehtml == 1) {
        include(PATH_TO_LMO . '/lmo-savehtml.php');
        include(PATH_TO_LMO . '/lmo-savehtml1.php');
    }
    // SAVEHTML

    // ZUSTAT
    //Statistiken aktualisieren
    if ($einzutore == 1 || $einzutoretab == 1 || $einzustats == 1) {
        include PATH_TO_LMO . '/lmo-zustat.php';
    }
    // ZUSTAT

    // Auswertungen der Addons beim Speichern (Lmo\Addon\MitErgebnisAuswertung), wie in der
    // Administration, ueber alle Spieltage; bis 4.5 band die Classlib hier fest die Dateien des
    // Tippspiels ein
    $lmo_update_anwendung = require PATH_TO_LMO . '/lmo-anwendung.php';
    $lmo_update_kontext = $lmo_update_anwendung->kontext(\Lmo\Web\Anfrage::ausGlobalen(), $_SERVER['PHP_SELF'], $file, \Lmo\Web\Sitzung::ausGlobalen());
    echo $lmo_update_kontext->addons->werteBeimSpeichern($lmo_update_kontext, \Lmo\Liga\Rohdaten\Liga::lies($fileName), 0);
}
?>