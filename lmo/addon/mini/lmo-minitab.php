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

require_once(__DIR__ . '/../../init.php');
$cfgarray['mini']['classFavorit'] = '';

/*
4-level fallback for these variables
->1. GET parameters
->2. Variable available (include)
->3. Config value
->4. Default value
*/

$m_template = !empty($_GET['mini_template'])
    ? str_replace('..', '', $_GET['mini_template'])
    : (isset($mini_template)
        ?  str_replace('..', '', $mini_template)
        : $cfgarray['mini']['standardTemplate'] );

//if (!is_readable(PATH_TO_TEMPLATEDIR . '/mini/$m_template.tpl.php')) $m_template = 'standard'; // Standard template called 'standard.tpl.php'

$m_liga = !empty($_GET['mini_liga'])
    ? urldecode($_GET['mini_liga'])
    : (isset($mini_liga)? $mini_liga : (isset($mini_standardLiga)
        ? $cfgarray['mini']['standardLiga']
        : ''));

$m_ueber= !empty($_GET['mini_ueber'])
    ? urldecode($_GET['mini_ueber'])
    : (isset($mini_ueber)? $mini_ueber : (isset($cfgarray['mini']['standardAnzahlueber'])
        ? $cfgarray['mini']['standardAnzahlueber']
        : 2));

$m_unter= !empty($_GET['mini_unter'])
    ? urldecode($_GET['mini_unter'])
    : (isset($mini_unter)? $mini_unter : (isset($cfgarray['mini']['standardAnzahlunter'])
        ? $cfgarray['mini']['standardAnzahlunter']
        : 2));

$m_platz= !empty($_GET['mini_platz'])
    ? urldecode($_GET['mini_platz'])
    : (isset($mini_platz)? $mini_platz : (!empty($cfgarray['mini']['standardTabellenPlatz'])
        ? $cfgarray['mini']['standardTabellenPlatz']
        : NULL));

// Als Zahlen weiterrechnen: in der mitgelieferten Konfiguration sind die Werte leer (''), und
// PHP 8 bricht bei "Zahl - ''" mit einem TypeError ab. Standard wie oben: 2 Plaetze darueber/darunter.
$m_ueber = is_numeric($m_ueber) ? (int) $m_ueber : 2;
$m_unter = is_numeric($m_unter) ? (int) $m_unter : 2;
$m_platz = is_numeric($m_platz) ? (int) $m_platz : NULL;


//If IFRAME - complete HTML document
if (basename($_SERVER['PHP_SELF']) == 'lmo-minitab.php') {?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <title>Minitable</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
  <style type="text/css">
    html,body {margin:0;padding:0;background:transparent;}
  </style>
</head>
<body>
<?php
}

$CacheOutput = '';
$TableCacheFile = PATH_TO_LMO . '/' . $diroutput . basename($m_liga) . '-' . $m_template . '.cache';
// der Schluessel in der ersten Zeile: Stand der Liga, Vorlage und Ausschnitt (bis 4.5 stand der
// Ausschnitt nur als Text '$m_ueber' darin und die Zeile endete nicht, der Cache griff nie)
$lmo_mini_cacheschluessel = (is_file(PATH_TO_LMO . '/' . $dirliga . $m_liga) ? filemtime(PATH_TO_LMO . '/' . $dirliga . $m_liga) : '') . '-' . $m_template . '|' . $m_ueber . '|' . $m_unter . '|' . $m_platz;
// If Cache File is present and up to date, print this out.
if (is_readable($TableCacheFile) && $cfgarray['mini']['CacheTable'] == 1) {
    $CacheFile = file ($TableCacheFile);
    if (trim((string)array_shift($CacheFile)) == $lmo_mini_cacheschluessel) {
        // echo 'Cache Output: <br>';
        $CacheOutput = print implode('', $CacheFile);
    }
}

if (empty($CacheOutput)) {
    // die Liga (Lmo\Liga\Rohdaten\Liga), ihre Werte fuer die Vorlage (Lmo\Addon\Mini\Minitabelle) und
    // die Vorlage selbst (Lmo\Addon\Mini\Vorlagen: mitgelieferte mit Twig, eigene nach alter Art)
    $lmo_mini_datei = PATH_TO_LMO . '/' . $dirliga . $m_liga;
    $lmo_mini_liga = is_readable($lmo_mini_datei) && $m_liga && !is_dir($lmo_mini_datei) ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_mini_datei) : null;
    if ($lmo_mini_liga !== null) {
        $lmo_mini_werte = (new \Lmo\Addon\Mini\Minitabelle($cfgarray, $text, new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR), URL_TO_IMGDIR, URL_TO_LMO, URL_TO_TEMPLATEDIR))
            ->werte($lmo_mini_liga, (string)$m_liga, (int)filemtime($lmo_mini_datei), $m_ueber, $m_unter, $m_platz);
        $lmo_mini_html = (new \Lmo\Addon\Mini\Vorlagen(PATH_TO_TEMPLATEDIR . '/mini', __DIR__ . '/vorlagen'))
            ->zeige($m_template . '.tpl.php', $lmo_mini_werte['werte'], $lmo_mini_werte['bloecke']);
        // write cache File
        if (is_writable(dirname($TableCacheFile)) && $cfgarray['mini']['CacheTable'] == 1) {
            $CacheOutput = $lmo_mini_cacheschluessel . "\n" . $lmo_mini_html;
            if ($fileHandle = @fopen($TableCacheFile, 'wb')) {
                fwrite($fileHandle, $CacheOutput);
                fclose($fileHandle);
            }
        }
        echo $lmo_mini_html;
    } else {
        echo getMessage($text['mini'][5] . ' ' . $m_liga, true);
    }
}
//Falls IFRAME - komplettes HTML-Dokument
if (basename($_SERVER['PHP_SELF']) == 'lmo-minitab.php') {?>
</body>
</html>
<?php
}?>
