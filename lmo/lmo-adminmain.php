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

require_once(PATH_TO_LMO . '/lmo-admintest.php');
function getmicrotime()
{
    list($usec, $sec) = explode(' ', microtime());
    return ((float)$usec + (float)$sec);
}
$startzeit = getmicrotime();
if ($action == 'admin') {
    $me = array('0', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
    $adda = $_SERVER['PHP_SELF'] . '?action=admin&amp;todo=';
    if (!isset($st)) {
        $sty = 0;
    } else {
        $sty = $st;
    }
    if (!isset($newpage)) {
        $newpage = 0;
    }
    $file = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
    $subdir = isset($_REQUEST['subdir']) ? lmo_clean_subdir($_REQUEST['subdir']) : dirname($file) . '/';
    if ($subdir == '/') {
        $subdir = '';
    }

    // Neueste Version laut Packagist: nur fuer den Hauptadmin, hoechstens einmal am Tag;
    // die Update-Seite fragt immer frisch ("Version erneut pruefen").
    if ($_SESSION['lmouserok'] == 2 && $lmo_version !== LMO_VERSION_DEV) {
        $lmo_update = lmo_update_info(PATH_TO_CONFIGDIR . '/.update-check.json', $todo == 'update');
        if ($lmo_update !== null) {
            $new_lmo_version = $lmo_update['version'];
            $new_lmo_version_time = $lmo_update['time'];
            $new_lmo_version_require_php = $lmo_update['php'];
            $new_lmo_version_changelog = $lmo_update['changelog'];
        }
    }

    $warning = (int)0;
    if (version_compare(PHP_VERSION, $min_php_version, '<')) echo getMessage('This LMO requires a web server with PHP ' . $min_php_version . ' or higher installed.<br>Your web server does not currently support this because your PHP version is:: <mark>' . PHP_VERSION . '</mark>', $warning = $warning + 1, true);

    // die Liga laden; was dabei ausgegeben wird, stand bis 4.5 vorn in der Menuezeile
    ob_start();
    require_once(PATH_TO_LMO . '/lmo-openfile.php');
    $lmo_admin_lademeldung = (string)ob_get_clean();
    // das Menue zeigt die Datei, wie sie nach dem Laden ist; der Ruecklink unten die nach dem Inhalt
    $lmo_admin_datei = $file;

    // Kontext der Anfrage (Lmo\Web\Kontext) mit den Addons der Installation; dazu die Bruecke zum
    // PDF-Addon nach alter Art (nicht Teil des LMO). Die Addons tragen ihre Menuepunkte und Seiten bei.
    $lmo_anwendung = require(PATH_TO_LMO . '/lmo-anwendung.php');
    $lmo_admin_kontext = $lmo_anwendung->kontext(\Lmo\Web\Anfrage::ausGlobalen(), $_SERVER['PHP_SELF'], $file, \Lmo\Web\Sitzung::ausGlobalen());
    $lmo_admin_addons = $lmo_admin_kontext->addons;
    if (file_exists(PATH_TO_ADDONDIR . '/pdf/lmo-adminpdfoptions.inc.php')) {
        $lmo_admin_addons = $lmo_admin_addons->mit(new \Lmo\Addon\AltesAdminAddon('pdf', 'pdfoptions', PATH_TO_ADDONDIR . '/pdf/lmo-adminpdfoptions.inc.php', 200, 201));
    }

    // Speichern einer Liga (Lmo\Web\Admin\LigaSpeichern; bis 4.5 lmo-saveliga.php im globalen Bereich).
    // Die Druckansicht schreibt noch der alte Code (lmo-savehtml.php, lmo-savehtml1.php) in eigenem
    // Bereich. Zurueck kommen die Meldungen und die Liga im gespeicherten Stand (Lmo\Liga\Normalform).
    $lmo_admin_ligaspeichern = new \Lmo\Web\Admin\LigaSpeichern(
        PATH_TO_LMO . '/' . $dirliga,
        PATH_TO_LMO . '/' . $diroutput,
        $lmo_admin_kontext->konfiguration,
        $text,
        URL_TO_IMGDIR,
        isset($_SESSION['lmouserok']) && ($_SESSION['lmouserok'] == 2 || $_SESSION['lmouserok'] == 1),
        static fn (\Lmo\Liga\Rohdaten\Liga $lmo_liga, string $file) => $lmo_statistik_erzeugen($lmo_liga, $file, $text),
        static function (\Lmo\Liga\Rohdaten\Liga $lmo_liga, string $st, string $file) use ($text, $diroutput, $defdateformat): void {
            include(PATH_TO_LMO . '/lmo-savehtml.php');
            include(PATH_TO_LMO . '/lmo-savehtml1.php');
        }
    );
    // die zuletzt gespeicherte Datei: auf sie zeigt der Ruecklink (etwa nach dem Anlegen einer Liga;
    // bis 4.5 setzte lmo-saveliga.php dafuer das globale $file)
    $lmo_admin_gespeichert = null;
    $lmo_admin_speichere = static function (\Lmo\Liga\Rohdaten\Liga $lmo_liga, string $lmo_datei, string $lmo_st) use ($lmo_admin_ligaspeichern, &$lmo_admin_gespeichert): array {
        $lmo_admin_gespeichert = $lmo_datei;
        return $lmo_admin_ligaspeichern->speichere($lmo_liga, $lmo_datei, $lmo_st);
    };

    // die Seiten des Kerns nach neuer Art (Lmo\Web\Admin); Hilfsadmins sehen nur ihre Ligen
    $lmo_admin_kern = array(
        new \Lmo\Web\Admin\StartseiteController(static fn (string $datei): bool => check_hilfsadmin($datei)),
        new \Lmo\Web\Admin\OeffnenController(static fn (string $datei): bool => check_hilfsadmin($datei)),
        new \Lmo\Web\Admin\LoeschenController(),
        new \Lmo\Web\Admin\HerunterladenController(),
        new \Lmo\Web\Admin\HochladenController(
            static fn (): ?array => isset($_POST['upl'], $_FILES['userfile']) ? array('tmp' => (string)$_FILES['userfile']['tmp_name'], 'name' => (string)$_FILES['userfile']['name']) : null,
            static fn (string $von, string $nach): bool => move_uploaded_file($von, $nach)
        ),
        new \Lmo\Web\Admin\AddonsController(PATH_TO_CONFIGDIR),
        new \Lmo\Web\Admin\BenutzerController(PATH_TO_CONFIGDIR . '/lmo-auth.php'),
        new \Lmo\Web\Admin\OptionenController(PATH_TO_CONFIGDIR),
        new \Lmo\Web\Admin\DesignController(PATH_TO_CONFIGDIR),
        new \Lmo\Web\Admin\NeuController($lmo_admin_speichere),
        new \Lmo\Web\Admin\AnzahlController($lmo_admin_speichere),
        new \Lmo\Web\Admin\MannschaftenController($lmo_admin_speichere),
        // die Bezeichnungen fuer Punkte und Tore vor denen der Liga (lmo-langload.php)
        new \Lmo\Web\Admin\GrundeinstellungenController($lmo_admin_speichere, (string)$orgpkt, (string)$orgtor),
        // zuletzt: alle uebrigen Seiten mit todo=edit
        new \Lmo\Web\Admin\ErgebnisseBearbeitenController($lmo_admin_speichere),
        new \Lmo\Web\Admin\TabellenkorrekturController($lmo_admin_speichere),
        // die neueste Version laut Packagist (oben frisch gefragt)
        new \Lmo\Web\Admin\SpieltageZuordnenController($lmo_admin_speichere),
        new \Lmo\Web\Admin\UpdateController($lmo_update ?? null, lmo_is_newer($new_lmo_version ?? null, $lmo_version), $lmo_version, (string)$lmo_license),
    );

    // der Inhalt: die Seite zu todo (und st); zuerst die der Addons, dann die des Kerns; ohne zustaendige Seite bleibt der Inhalt leer
    $lmo_admin_seite = null;
    foreach (array_merge($lmo_admin_addons->adminController(), $lmo_admin_kern) as $lmo_admin_controller) {
        if ($lmo_admin_controller->zustaendig($lmo_admin_kontext)) {
            $lmo_admin_seite = $lmo_admin_controller;
            break;
        }
    }
    // was das Menue zeigt, steht vor dem Inhalt fest (der alte Code aendert etwa $adda)
    $lmo_admin_menue = array(
        'adda' => $adda,
        'rolle' => (int)$_SESSION['lmouserok'],
        'todo' => (string)$todo,
        'datei' => (string)$lmo_admin_datei,
        'subdir' => (string)$subdir,
        'newpage' => $newpage,
        'addons' => $lmo_admin_addons->adminMenue($lmo_admin_kontext),
        'update' => $_SESSION['lmouserok'] == 2 && lmo_is_newer($new_lmo_version ?? null, $lmo_version),
    );
    ob_start();
    if ($lmo_admin_seite instanceof \Lmo\Addon\AlteAdminSeite) {
        // eine Seite nach alter Art wie bisher im globalen Bereich
        require($lmo_admin_seite->datei());
    } elseif ($lmo_admin_seite !== null) {
        echo $lmo_admin_seite->zeige($lmo_admin_kontext)->html;
    }
    $lmo_admin_inhalt = (string)ob_get_clean();

    // der Rahmen: Menue, Inhalt, Sprachwahl, Ruecklink und Ladezeit (Vorlage admin_rahmen.html.twig)
    echo \Lmo\Web\Vorlagen::zeige(PATH_TO_LMO . '/vorlagen', 'admin_rahmen.html.twig', $lmo_admin_menue + array(
        'text' => $text,
        'lmo' => URL_TO_LMO,
        'lademeldung' => $lmo_admin_lademeldung,
        'inhalt' => $lmo_admin_inhalt,
        'sprachwahl' => $einsprachwahl == 1 ? getLangSelector() : '',
        'ruecklink' => (string)($lmo_admin_gespeichert ?? $file),
        'ladezeit' => number_format((getmicrotime() - $startzeit), 4, '.', ','),
    ));
}
?>