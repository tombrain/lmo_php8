<?php
/** Liga Manager Online 4
  *
  * Ersteinrichtung: legt das erste Admin-Konto an, solange config/lmo-auth.php fehlt.
  * Ersetzt das fruehere Standardkonto admin/lmo aus dem Installer.
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of
  * the License, or (at your option) any later version.
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  *
  */
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

// Texte aus den Sprachdateien (lang/lang-*.txt, Nummern 5013-5023; 306 = Nutzername)
// Das abgeschickte Formular wertet lmoadmin.php vor der Ausgabe aus ($setup_error).
$setup_error = $setup_error ?? 0;
$setup_user = isset($_POST['setup_user']) ? trim($_POST['setup_user']) : 'admin';
if (!lmo_has_admin()) {
?>
  <table class="lmoMain" cellspacing="0" cellpadding="0" border="0">
    <tr>
      <td align="center"><h1><?php echo $text[77] . ' ' . $text[54] . ' - ' . $text[5013]; ?></h1></td>
    </tr>
    <tr>
      <td align="center">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
          <table class="lmoMiddle" width="99%" cellspacing="0" cellpadding="0" border="0">
            <tr>
              <td align="center"><p><?php echo $text[5014]; ?></p><?php
    if ($setup_error !== 0) {
        echo getMessage($text[$setup_error], true);
    } ?></td>
            </tr>
            <tr>
              <td align="center">
                <table class="lmoInner" cellspacing="0" cellpadding="0" border="0">
                  <tr>
                    <td align="right"><?php echo $text[306]; ?></td>
                    <td align="left"><input class="lmo-formular-input" type="text" name="setup_user" size="20" maxlength="40" value="<?php echo htmlspecialchars($setup_user); ?>" autocomplete="username"></td>
                  </tr>
                  <tr>
                    <td align="right"><?php echo $text[5015]; ?></td>
                    <td align="left"><input class="lmo-formular-input" type="password" name="setup_pass" size="20" autocomplete="new-password"></td>
                  </tr>
                  <tr>
                    <td align="right"><?php echo $text[5016]; ?></td>
                    <td align="left"><input class="lmo-formular-input" type="password" name="setup_pass2" size="20" autocomplete="new-password"></td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td align="left"><input class="lmo-formular-button" type="submit" name="setup_submit" value="<?php echo $text[5017]; ?>"></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </form>
      </td>
    </tr>
<?php
    // Sprachauswahl wie im Adminbereich (lmo-adminmain.php); die Wahl bleibt in der Sitzung
    if (!empty($einsprachwahl)) { ?>
    <tr>
      <td class="lmoFooter" align="left"><?php echo getLangSelector(); ?></td>
    </tr>
<?php
    } ?>
  </table><?php
}
