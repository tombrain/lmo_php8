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

    // Kein Installer mehr: init.php richtet LMO beim ersten Aufruf selbst ein (lmo-setup.php).
    define('LMO_AUTH', 1);
    require(__DIR__ . '/init.php');
    $subdir = '';
    if (!isset($_SESSION['lmouserok'])) {
        $_SESSION['lmouserok'] = 0;
    }
    if (!isset($_SESSION['lmousername'])) {
        $_SESSION['lmousername'] = '';
    }
    if (!isset($_SESSION['lmouserpass'])) {
        $_SESSION['lmouserpass'] = '';
    }
    if (!isset($_SESSION['lmouserfile'])) {
        $_SESSION['lmouserfile'] = '';
    }
    if (!isset($_SESSION['lmouserokerweitert'])) {
        $_SESSION['lmouserokerweitert'] = 0;
    }

    $todo = isset($_REQUEST['todo']) ? $_REQUEST['todo'] : '';
    $st = isset($_REQUEST['st']) ? $_REQUEST['st'] : NULL;
    if ($todo == 'logout') {
        $_SESSION['lmouserok'] = 0;
        $_SESSION['lmouserpass'] = '';
    }
    // Ersteinrichtung (Lmo\Web\Admin\Ersteinrichtung; bis 4.5 in lmo-setup.php und lmo-adminsetup.php):
    // das Formular vor jeder Ausgabe auswerten (neue Sitzungs-ID braucht freie Header)
    $lmo_einrichtung = new \Lmo\Web\Admin\Ersteinrichtung(PATH_TO_CONFIGDIR . '/lmo-auth.php');
    $lmo_einrichtung_fehler = 0;
    if (!$lmo_einrichtung->hatKonto()) {
        // Ohne Konto keine Admin-Sitzung (z. B. alte Sitzung nach geloeschter lmo-auth.php)
        $_SESSION['lmouserok'] = 0;
        if (isset($_POST['setup_submit'])) {
            $lmo_einrichtung_name = trim(is_string($_POST['setup_user'] ?? null) ? $_POST['setup_user'] : '');
            $lmo_einrichtung_fehler = $lmo_einrichtung->legeAn($lmo_einrichtung_name, is_string($_POST['setup_pass'] ?? null) ? $_POST['setup_pass'] : '', is_string($_POST['setup_pass2'] ?? null) ? $_POST['setup_pass2'] : '');
            if ($lmo_einrichtung_fehler === 0) {
                // direkt angemeldet, in einer neuen Sitzung
                session_regenerate_id(true);
                $_SESSION['lmousername'] = $lmo_einrichtung_name;
                $_SESSION['lmouserpass'] = '';
                $_SESSION['lmouserok'] = 2;
            }
        }
    }
    // Anmeldung (Lmo\Web\Admin\Anmeldung; bis 4.5 lmo-adminauth.php, lmo-admincheck_auth.php und
    // lmo-loadauth.php), ebenfalls vor jeder Ausgabe ausgewertet
    if ($lmo_einrichtung->hatKonto()) {
        $lmo_anmeldung = new \Lmo\Web\Admin\Anmeldung(PATH_TO_CONFIGDIR . '/lmo-auth.php');
        if (empty($_SESSION['lmouserok']) && isset($_POST['xusername']) && is_string($_POST['xusername'])) {
            $_SESSION['lmousername'] = $_POST['xusername'];
            // das Kennwort bleibt nicht in der Sitzung (bis 4.5 im Klartext in lmouserpass)
            $_SESSION['lmouserpass'] = '';
            $lmo_konto = $lmo_anmeldung->meldeAn($_SESSION['lmousername'], is_string($_POST['xuserpass'] ?? null) ? $_POST['xuserpass'] : '');
            if ($lmo_konto !== null) {
                // neue Sitzungs-ID gegen Session Fixation (bis 4.5 blieb die vor der Anmeldung)
                session_regenerate_id(true);
                $_SESSION['lmouserok'] = $lmo_konto['rang'];
                if ($_SESSION['lmouserok'] == 1) {
                    $_SESSION['lmouserfile'] = $lmo_konto['ligen'];
                    $_SESSION['lmouserokerweitert'] = $lmo_konto['erweitert'];
                }
            }
        }
    }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
                "http://www.w3.org/TR/html4/loose.dtd">
<html lang="<?php echo $text[798]; ?>">
<head>
<title>LMO Admin</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <link type="text/css" rel="stylesheet" href="<?php echo URL_TO_LMO; ?>/lmo-style-nc.php">
  <style type="text/css">@import url("<?php echo URL_TO_LMO; ?>/lmo-style.php");</style>
</head>
<body>
<div align="center">
<?php
    $action = 'admin';
    $array = array();
    if (!$lmo_einrichtung->hatKonto()) {
        $lmo_einrichtung_vorgabe = isset($_POST['setup_user']) && is_string($_POST['setup_user']) ? trim($_POST['setup_user']) : 'admin';
        echo $lmo_einrichtung->formular($text, (string)$_SERVER['PHP_SELF'], $lmo_einrichtung_vorgabe, $lmo_einrichtung_fehler, !empty($einsprachwahl) ? getLangSelector() : '', URL_TO_IMGDIR, PATH_TO_LMO . '/vorlagen');
    }
    if (isset($lmo_anmeldung) && $lmo_einrichtung->hatKonto() && empty($_SESSION['lmouserok'])) {
        echo $lmo_anmeldung->formular($text, (string)$_SERVER['PHP_SELF'], (string)$_SESSION['lmousername'], !empty($einsprachwahl) ? getLangSelector() : '', URL_TO_LMO, PATH_TO_LMO . '/vorlagen');
    }
    if (isset($_SESSION['lmouserok']) && $_SESSION['lmouserok'] > 0) {
        $file =! empty($_REQUEST['file']) ? $_REQUEST['file'] : '';
        if (!empty($file) && ($todo == 'open' || $todo == ''))
            $todo = 'edit';
        if ((!empty($file) && check_hilfsadmin($file)) || empty($file))
            require(PATH_TO_LMO . '/lmo-adminmain.php');
    }
?>
</div>
</body>
</html>
