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

// Tippspiel mit Menuepunkt (Lmo\Addon\Tipp\TippAddon); seine Seiten zeigen die Controller des Addons.
// Tipper und Tipps liegen im Datenordner daten/tipp (bis 4.5 hier im Addon-Ordner, von dort ziehen sie einmal um).
// Was das Addon von aussen braucht, geben diese Closures mit: die Tippregeln (noch alter Code, im
// globalen Bereich eingebunden) und die Mails (mit lmo_mailer(), Absender und Texten aus dem globalen Bereich).
return new \Lmo\Addon\Tipp\TippAddon(__DIR__, static function (string $lmo_tipp_regeln): string {
    // die Tippregeln im Menue: eine Seite nach alter Art, wie bisher im globalen Bereich eingebunden
    foreach (array_keys($GLOBALS) as $lmo_tipp_name) {
        if ($lmo_tipp_name !== 'GLOBALS' && $lmo_tipp_name[0] !== '_' && $lmo_tipp_name !== 'lmo_tipp_regeln') {
            ${$lmo_tipp_name} = &$GLOBALS[$lmo_tipp_name];
        }
    }
    unset($lmo_tipp_name);
    ob_start();
    include(__DIR__ . '/' . $lmo_tipp_regeln);
    return (string)ob_get_clean();
}, static function (string $xtipperemail, string $xtippernick, string $xtipperpass): string {
    // die Freischaltmail mit Nick, Kennwort und Anmeldelink (bis 4.5 lmo-admintippfreischaltung.php)
    $text = $GLOBALS['text'];
    $mail = lmo_mailer($GLOBALS['aadr'], $text['tipp'][92]);
    $mail->Subject = $text['tipp'][77] . ' (' . $_SERVER['HTTP_HOST'] . ')';
    $mail->Body = str_replace(array('\\n', '[nick]', '[pass]', '[url]'), array("\n", $xtippernick, $xtipperpass, lmo_request_origin() . $_SERVER['PHP_SELF'] . '?action=tipp&xtippername=' . $xtippernick . '&xtipperpass=' . $xtipperpass), $text['tipp'][298]);
    $mail->addAddress($xtipperemail, $xtippernick);
    return $mail->send() ? getMessage($text['tipp'][78]) : getMessage($text['tipp'][80] . " Details: {$mail->ErrorInfo}", true);
}, static function (string $lmo_an, string $lmo_nick, string $lmo_kennwort): ?string {
    // "Kennwort vergessen": die Mail mit dem neuen Kennwort (bis 4.5 lmo-tippemailpass.php)
    $text = $GLOBALS['text'];
    $mail = lmo_mailer($GLOBALS['aadr'], $text['tipp'][92]);
    $mail->Subject = $text['tipp'][79] . ' (' . $_SERVER['HTTP_HOST'] . ')';
    $mail->addAddress($lmo_an);
    $loginUrl = lmo_request_origin() . $_SERVER['PHP_SELF'] . '?action=tipp';
    $mail->Body = $text['tipp'][297] . ' ' . $lmo_nick . ",\n\n" .
                  $text['tipp'][77] . "\n\n" .
                  $text['tipp'][23] . ': ' . $lmo_nick . "\n" .
                  $text[308] . ': ' . $lmo_kennwort . "\n\n" .
                  $text['tipp'][255] . ': ' . $loginUrl;
    return $mail->send() ? null : (string)$mail->ErrorInfo;
}, static function (string $xtippernick): string {
    // die Mail an den Admin ueber einen neuen Tipper (mailbeianmeldung; bis 4.5 lmo-admintippbenachrichtigung.php)
    $text = $GLOBALS['text'];
    $mail = lmo_mailer($GLOBALS['aadr'], $text['tipp'][92]);
    $mail->Subject = $text['tipp'][13] . ' (' . $_SERVER['HTTP_HOST'] . ')';
    $mail->Body = str_replace(array('\\n', '[nick]'), array("\n", $xtippernick), $text['tipp'][303]);
    $mail->addAddress($GLOBALS['aadr'], $text['tipp'][92]);
    return $mail->send() ? '' : (string)$mail->ErrorInfo;
}, (string)constant('LMO_VERSION_NR'), \Lmo\Addon\Tipp\TippAddon::datenordner(PATH_TO_DATADIR, PATH_TO_ADDONDIR, PATH_TO_CONFIGDIR), static function (string $lmo_an, string $lmo_betreff, string $lmo_text): ?string {
    // eine Mail aus Newsletter/Reminder (bis 4.5 in lmo-tippemail.php)
    $mail = lmo_mailer($GLOBALS['aadr'], $GLOBALS['text']['tipp'][92]);
    $mail->Subject = $lmo_betreff . ' (' . $_SERVER['HTTP_HOST'] . ')';
    $mail->Body = $lmo_text;
    $mail->addAddress($lmo_an);
    return $mail->send() ? null : (string)$mail->ErrorInfo;
}, PATH_TO_CONFIGDIR, function_exists('lmo_request_origin') ? (string)lmo_request_origin() : '');
