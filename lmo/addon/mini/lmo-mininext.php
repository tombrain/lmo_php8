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

$mini_standardTemplate = isset($cfgarray['mini']['standardTemplate']) ? $cfgarray['mini']['standardTemplate'] : 'mininext.tpl.php';  // Templatefile
//for security reasons .. will be deleted - so only templates within the template path are possible

$mini_template = isset($_GET['mini_template'])
    ? str_replace('..', '', $_GET['mini_template'])
    : (isset($mini_template)
        ? str_replace('..', '', $mini_template)
        : $mini_standardTemplate);

/*
4-level fallback for these variables
->1. GET parameters
->2. Variable available (include)
->3. Config value
->4. Default value
*/

// Undocumented control parameters for advanced users
$mini_withArchiv = isset($_GET['mini_withArchiv'])
    ? ($_GET['mini_withArchiv'] == '1' ? 1 : 0)
    : (isset($mini_withArchiv)
        ? ($mini_withArchiv == '1' ? 1 : 0)
        : (isset($cfgarray['mini']['withArchiv'])
            ? $cfgarray['mini']['withArchiv']
            : 1));

$mini_unGreedy = isset($_GET['mini_unGreedy'])
    ? ($_GET['mini_unGreedy'] == '1' ? 1 : 0)
    : (isset($mini_unGreedy)
        ? ($mini_unGreedy == '1' ? 1 : 0)
        : (isset($cfgarray['mini']['unGreedy'])
            ? $cfgarray['mini']['unGreedy']
            : 1));

$mini_barWidth = isset($_GET['mini_barWidth']) && is_numeric($_GET['mini_barWidth'])
    ? $_GET['mini_barWidth']
    : (isset($mini_barWidth) && is_numeric($mini_barWidth)
        ? $mini_barWidth
        : (isset($cfgarray['mini']['barWidth'])
            ? $cfgarray['mini']['barWidth']
            : 120));

$file = isset($_GET['file']) ? $_GET['file'] : (isset($file) ? $file : NULL);
$archivFolder = isset($_GET['folder']) ? $_GET['folder'] : (isset($folder) ? $folder : basename($ArchivDir));  // Default

if (substr($archivFolder, -1) != '/') {
    $archivFolder .= '/';
}

if (str_contains($archivFolder, '../')) {
    exit();
}

$a = isset($_GET['a']) ? $_GET['a'] : (isset($a) ? $a : NULL);  // No. of Team 'a' (if not specified, favTeam is used)
$b = isset($_GET['b']) ? $_GET['b'] : (isset($b) ? $b : NULL);  // No. of Team 'b' (if not specified, next opponent of Team 'a' is used)

$mini_cache_refresh = isset($mini_cache_refresh) ? $mini_cache_refresh : 0;

//generate cache-file-id
$multi = md5($file . $archivFolder . $mini_template . $a . $b);

// Load the cache-counter-file for viewers simple cache mechanism
$mini_cache_filename = PATH_TO_LMO . '/' . $diroutput . 'mini_' . $multi . '.txt';
$mini_cache_counter_filename = PATH_TO_LMO . '/' . $diroutput . 'mini_' . $multi . '_count.txt';
$mini_cache_counter = 0;  //counter for cache hits

if (!file_exists($mini_cache_counter_filename)) {
    touch($mini_cache_counter_filename);
}
$mini_cache_counter_file = fopen($mini_cache_counter_filename, 'rb');
$mini_cache_counter = intval(trim(fgets($mini_cache_counter_file)));
fclose($mini_cache_counter_file);

if (!file_exists($mini_cache_filename) ||
    filemtime(PATH_TO_LMO . '/' . $dirliga . $file) > filemtime($mini_cache_filename)||
    $mini_cache_counter == 0 ||
    $mini_cache_counter > $mini_cache_refresh) {  // not cached or cache limit reached->generate new view

    //If IFRAME - complete HTML document
    if (basename($_SERVER['PHP_SELF']) == 'lmo-mininext.php') {?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <title>LMO Nextgame</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
  <style type="text/css">
    html,body {margin:0;padding:0;background:transparent;}
  </style>
</head>
<body><?php
    }

    // die Liga (Lmo\Liga\Rohdaten\Liga), ihre Werte fuer die Vorlage (Lmo\Addon\Mini\Minispielplan) und
    // die Vorlage selbst (Lmo\Addon\Mini\Vorlagen: die mitgelieferte mit Twig, eigene nach alter Art)
    $lmo_mini_datei = PATH_TO_LMO . '/' . $dirliga . $file;
    $lmo_mini_rohdaten = $file && file_exists($lmo_mini_datei) && !is_dir($lmo_mini_datei) ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_mini_datei) : null;
    if ($lmo_mini_rohdaten !== null) {
        // die Ligadateien des Archivs in der Reihenfolge des Verzeichnisses; erst gelesen, wenn es ein Spiel gibt
        $lmo_mini_archiv = static function () use ($mini_withArchiv, $dirliga, $archivFolder, $text): array {
            $dateien = array();
            $ordner = PATH_TO_LMO . '/' . $dirliga . $archivFolder;
            if ($mini_withArchiv == 1) {
                if (!file_exists($ordner)) {
                    echo getMessage($text['mini'][6] . ' ' . $ordner, true);
                } else {
                    $verzeichnis = dir($ordner);
                    while ($verzeichnis && ($eintrag = $verzeichnis->read()) !== false) {
                        $punkt = strrpos($eintrag, '.');
                        if ($punkt !== false && strtolower(substr($eintrag, $punkt + 1)) == 'l98') {
                            $dateien[] = $verzeichnis->path . $eintrag;
                        }
                    }
                    if ($verzeichnis) {
                        $verzeichnis->close();
                    }
                }
            }
            return $dateien;
        };
        $lmo_mini_werte = (new \Lmo\Addon\Mini\Minispielplan($text, new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR)))
            ->werte($lmo_mini_rohdaten, $lmo_mini_datei, $a, $b, $lmo_mini_archiv, $mini_unGreedy == 1, $mini_barWidth, time());
        if ($lmo_mini_werte['abbruch'] !== null) {
            // weder Mannschaft a noch eine Lieblingsmannschaft (wie bis 4.5 ohne Rest der Seite)
            echo getMessage($lmo_mini_werte['abbruch'], true);
            exit;
        }
        $mini_output = (new \Lmo\Addon\Mini\Vorlagen(PATH_TO_TEMPLATEDIR . '/mini', __DIR__ . '/vorlagen'))
            ->zeige($mini_template, $lmo_mini_werte['werte'], $lmo_mini_werte['bloecke']);

        //save cache file
        if ($mini_cache_file = fopen(PATH_TO_LMO . '/' . $diroutput . 'mini_' . $multi . '.txt', 'wb')) {
            fwrite($mini_cache_file, $mini_output);
            fclose($mini_cache_file);
        }
        //reset cache counter
        $mini_cache_counter_file = fopen(PATH_TO_LMO . '/' . $diroutput . 'mini_' . $multi . '_count.txt', 'wb');
        fwrite($mini_cache_counter_file, '1');
        fclose($mini_cache_counter_file);

        echo $mini_output;
    } else
        echo getMessage($text['mini'][5], true); // if file
    // If IFRAME - complete HTML document
    if (basename($_SERVER['PHP_SELF']) == 'lmo-mininext.php') {?>
</body>
</html><?php
    }
} else {  //get cache
    if ($mini_cache_file = fopen($mini_cache_filename, 'rb')) {
        fpassthru($mini_cache_file);
        fclose($mini_cache_file);
    }
    //increment cache counter

    $mini_cache_counter_file = fopen(PATH_TO_LMO . '/' . $diroutput . 'mini_' . $multi . '_count.txt', 'wb');
    fwrite($mini_cache_counter_file, ++ $mini_cache_counter);
    fclose($mini_cache_counter_file);
}
