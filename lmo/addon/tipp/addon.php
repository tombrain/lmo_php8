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

// Tippspiel mit Menuepunkt (Lmo\Addon\Tipp\TippAddon). Seine Seiten zeigt noch der alte Code: Er
// erwartet den globalen Bereich (Einstellungen aus init.php, $text, Variablen, die seine Funktionen
// mit global lesen). Die Closure bindet deshalb alle globalen Variablen per Referenz und bindet ihn
// dann ein. Was er an diesen Variablen aendert, aendert wie bisher die globalen; neue Variablen
// bleiben in der Closure (seine Funktionen lesen per global nur die Einstellungen und die Zaehler
// der Auswertung, Lmo\Addon\Tipp\Punkte und Tippzeit).
return new \Lmo\Addon\Tipp\TippAddon(static function (): void {
    foreach (array_keys($GLOBALS) as $lmo_tipp_name) {
        if ($lmo_tipp_name !== 'GLOBALS' && $lmo_tipp_name[0] !== '_') {
            ${$lmo_tipp_name} = &$GLOBALS[$lmo_tipp_name];
        }
    }
    unset($lmo_tipp_name);
    // wie bis 4.5 lmo-showmain2.php vor dem Tippspiel: die Liga aus der Adresse laden (lmo-openfile.php)
    // und den Stand der Tabelle setzen
    // $file ist oben gebunden; PHPStan sieht das nicht, deshalb hier der globale Wert
    if (($GLOBALS['file'] ?? '') != '') {
        require(PATH_TO_LMO . '/lmo-openfile.php');
        if ($file != '' && isset($lmo_liga)) {
            if (empty($_GET['endtab'])) {
                $endtab = $lmo_liga->einstellungen()->spieltage();
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
        }
    }
    require __DIR__ . '/lmo-tippstart.php';
}, __DIR__, static function (string $lmo_tipp_regeln): string {
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
    // die Freischaltmail (Nick und Kennwort): noch der alte Code, mit den Einstellungen aus dem globalen Bereich
    foreach (array_keys($GLOBALS) as $lmo_tipp_name) {
        if ($lmo_tipp_name !== 'GLOBALS' && $lmo_tipp_name[0] !== '_' && !in_array($lmo_tipp_name, array('xtipperemail', 'xtippernick', 'xtipperpass'), true)) {
            ${$lmo_tipp_name} = &$GLOBALS[$lmo_tipp_name];
        }
    }
    unset($lmo_tipp_name);
    // lmo-tipptest.php bricht ohne diese Konstante ab; bisher definierte sie lmo-tippstart.php
    if (!defined('LMO_TIPPAUTH')) {
        define('LMO_TIPPAUTH', 1);
    }
    ob_start();
    include(__DIR__ . '/lmo-admintippfreischaltung.php');
    return (string)ob_get_clean();
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
});
