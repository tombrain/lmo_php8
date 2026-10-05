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
    // Ersteinrichtung: Formular vor jeder Ausgabe auswerten (neue Sitzungs-ID braucht freie Header)
    $setup_error = 0;
    if (!lmo_has_admin()) {
        // Ohne Konto keine Admin-Sitzung (z. B. alte Sitzung nach geloeschter lmo-auth.php)
        $_SESSION['lmouserok'] = 0;
        if (isset($_POST['setup_submit'])) {
            $setup_error = lmo_create_admin(trim($_POST['setup_user'] ?? ''), $_POST['setup_pass'] ?? '', $_POST['setup_pass2'] ?? '');
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
    if (!lmo_has_admin()) {
        require(PATH_TO_LMO . '/lmo-adminsetup.php');
    }
    if (lmo_has_admin()) {
        require(PATH_TO_LMO . '/lmo-adminauth.php');
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
