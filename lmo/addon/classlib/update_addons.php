<?php
/**
 * Updatefunktion für Addons
 *
 * @param string $fileName
 */
function updateAddons($fileName) {
    include PATH_TO_LMO . '/init.php';
    // init.php legt auch hier im Bereich der Funktion die Konfiguration als Variablen an (bis 4.5
    // band die Classlib dafuer zusaetzlich lmo-cfgload.php ein)
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
    // die Liga laden (Lmo\Web\LigaOeffnen, bis 4.5 lmo-openfile.php)
    $lmo_geoeffnet = \Lmo\Web\LigaOeffnen::oeffne(PATH_TO_LMO . '/' . $dirliga, $file, $st ?? null, static fn (string $datei): bool => check_hilfsadmin($datei), (string)$defdateformat, URL_TO_IMGDIR, $text);
    echo $lmo_geoeffnet->meldung;
    $file = $lmo_geoeffnet->datei;
    if ($lmo_geoeffnet->liga !== null) {
        $lmo_liga = $lmo_geoeffnet->liga;
        $st = $lmo_geoeffnet->st;
        $stats = $lmo_geoeffnet->stats;
        $stand = $lmo_geoeffnet->stand;
        $text = $lmo_geoeffnet->text;
    }

    // SAVEHTML
    //HTML-Ausgabe erstellen
    // (Lmo\Web\Druckdateien, bis 4.5 lmo-savehtml.php und lmo-savehtml1.php)
    if ($einsavehtml == 1) {
        (new \Lmo\Web\Druckdateien(PATH_TO_LMO . '/' . $diroutput, $text, new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR), (string)$defdateformat, PATH_TO_LMO . '/vorlagen'))
            ->schreibe($lmo_liga, $file, (string)$st);
    }
    // SAVEHTML

    // ZUSTAT
    //Statistiken aktualisieren
    // (Lmo\Liga\Saisonstatistik, bis 4.5 lmo-zustat.php; wie dort nur angemeldet)
    if (($einzutore == 1 || $einzutoretab == 1 || $einzustats == 1) && isset($_SESSION['lmouserok']) && $_SESSION['lmouserok'] > 0) {
        \Lmo\Liga\Saisonstatistik::schreibe($lmo_liga, PATH_TO_LMO . '/' . $diroutput . basename($file) . '.php', (string)$text[145]);
    }
    // ZUSTAT

    // Auswertungen der Addons beim Speichern (Lmo\Addon\MitErgebnisAuswertung), wie in der
    // Administration, ueber alle Spieltage; bis 4.5 band die Classlib hier fest die Dateien des
    // Tippspiels ein
    $lmo_update_anwendung = \Lmo\Web\Installation::ausKonstanten()->anwendung($cfgarray, $text, static fn (string $datei): bool => check_hilfsadmin($datei), isset($_SESSION['lmouserok']) && $_SESSION['lmouserok'] > 0, (string)constant('LMO_VERSION_NR'));
    $lmo_update_kontext = $lmo_update_anwendung->kontext(\Lmo\Web\Anfrage::ausGlobalen(), $_SERVER['PHP_SELF'], $file, \Lmo\Web\Sitzung::ausGlobalen());
    echo $lmo_update_kontext->addons->werteBeimSpeichern($lmo_update_kontext, \Lmo\Liga\Rohdaten\Liga::lies($fileName), 0);
}
?>