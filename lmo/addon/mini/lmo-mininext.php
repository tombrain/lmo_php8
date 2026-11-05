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

$template_folder = PATH_TO_TEMPLATEDIR . '/mini/';

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

    $template = new LMO_Template($template_folder);  // folder
    $template->loadTemplatefile($mini_template);
    // die Spiele der Liga (Lmo\Liga\Begegnungen; bis 4.5 die Classlib), Mannschaften als Nummern
    $team_a = NULL;
    $team_b = NULL;
    $partie = NULL;
    $lastPartie = NULL;
    $showLastGame = true;
    $lmo_mini_bilder = new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR);
    $lmo_mini_datei = PATH_TO_LMO . '/' . $dirliga . $file;
    $lmo_mini_rohdaten = $file && file_exists($lmo_mini_datei) && !is_dir($lmo_mini_datei) ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_mini_datei) : null;
    $liga = $lmo_mini_rohdaten !== null ? \Lmo\Liga\Begegnungen::aus($lmo_mini_rohdaten) : null;
    if ($liga !== null && $lmo_mini_rohdaten !== null) {
        $lmo_mini_favorit = $liga->mannschaft((string)$lmo_mini_rohdaten->einstellungen()->wert('favTeam'));
        if (!is_null($a)) {
            $team_a = $liga->mannschaft($a);
        } elseif (is_null($team_a = $lmo_mini_favorit)) {
            echo getMessage($text['mini'][8], true);
            exit;
        }

        if (is_null($team_b = $liga->mannschaft($b))) {
            // We determine the nearest opponent of team 'a' if team 'b' is not specified
            $now = time();
            $showLastGame = true;
            // ohne Mannschaft a (ungueltige Nummer) die Spiele der Lieblingsmannschaft, wie bis 4.5
            $lmo_mini_fuer = $team_a ?? $lmo_mini_favorit;
            foreach ($lmo_mini_fuer === null ? array() : $liga->nachZeit($lmo_mini_fuer) as $game) {  // Only sort by time regardless of game day
                if ($now < $game->zeit) {  // find the last game
                    $partie = $game;
                    $template->setVariable('gameTxt', $text['mini'][1]);  // There is a future game
                    $template->setVariable('gameNote', $partie->notiz);
                    break;  // found an opponent
                }
                $lastPartie = $game;
            }
            if (!isset($partie)) {  // No more games found, so show last game (season ended)
                $partie = $lastPartie;
                $lastPartie = NULL;
                $showLastGame = false;
                $template->setVariable('gameTxt', $text['mini'][2]);
            } else {
                $showLastGame = true;
            }

            if (isset($partie)) {
                $team_b = $partie->heim == $team_a ? $partie->gast : $partie->heim;
            }
        } else {  // Team 'a' and 'b' were specified so show the result of this game
            $partie = $team_a === null ? NULL : ($liga->zwischen($team_a, $team_b, false)[0] ?? NULL);
            $template->setVariable('gameTxt', $text['mini'][3]);
        }

        if (isset($partie)) {
            $heim = $liga->daten($partie->heim);
            $gast = $liga->daten($partie->gast);
            $template->setVariable('gameDate', $partie->datum());
            $template->setVariable('gameTime', $partie->uhrzeit());
            $template->setVariable('ligaDatum', $text['mini'][14] . ': ' . date('d.m.Y', (int)filemtime($lmo_mini_datei)));

            $now = time();
            $gameDateTime = (int)$partie->zeit;
            $days = floor(($gameDateTime - $now) / 86400);
            $rest = ($gameDateTime - $now) - ($days * 86400);
            $hours = floor($rest / 3600);
            $rest = $rest - ($hours * 3600);
            $minutes = floor($rest / 60);
            $rest = $rest - ($minutes * 60);
            $template->setVariable('countDown', $text['mini'][15] . ': ' . $days . ', ' . $text['mini'][16] . ': ' . $hours . ', ' . $text['mini'][17] . ': ' . $minutes);

            // die Fusszeile (bis 4.5 mit der Version der Classlib)
            $template->setVariable('copy', $text['mini'][0]);
            $template->setVariable('imgHomeSmall', $lmo_mini_bilder->bild((string)$heim->name, 'teams', 'small'));
            $template->setVariable('imgHomeMiddle', $lmo_mini_bilder->bild((string)$heim->name, 'teams', 'middle'));
            $template->setVariable('imgHomeBig', $lmo_mini_bilder->bild((string)$heim->name, 'teams', 'big'));
            $template->setVariable('imgGuestSmall', $lmo_mini_bilder->bild((string)$gast->name, 'teams', 'small'));
            $template->setVariable('imgGuestMiddle', $lmo_mini_bilder->bild((string)$gast->name, 'teams', 'middle'));
            $template->setVariable('imgGuestBig', $lmo_mini_bilder->bild((string)$gast->name, 'teams', 'big'));

            $template->setVariable('homeName', (string)$heim->name);
            $template->setVariable('guestName', (string)$gast->name);
            $template->setVariable('homeNameMiddle', (string)$heim->mittel);
            $template->setVariable('guestNameMiddle', (string)$gast->mittel);
            $template->setVariable('homeNameShort', (string)$heim->kurz);
            $template->setVariable('guestNameShort', (string)$gast->kurz);

            if (!$showLastGame) {
                $template->setVariable('homeTore', $partie->toreHeimText());
                $template->setVariable('guestTore', $partie->toreGastText());
            }

            // previous game
            if (isset($lastPartie)) {
                $vorherHeim = $liga->daten($lastPartie->heim);
                $vorherGast = $liga->daten($lastPartie->gast);
                $template->setCurrentBlock('previous');
                $template->setVariable('previous_gameDate', $lastPartie->datum());
                $template->setVariable('previous_gameTime', $lastPartie->uhrzeit());
                $template->setVariable('previous_gameNote', $lastPartie->notiz);
                $template->setVariable('previous_imgHomeSmall', $lmo_mini_bilder->bild((string)$vorherHeim->name, 'teams', 'small'));
                $template->setVariable('previous_imgHomeMiddle', $lmo_mini_bilder->bild((string)$vorherHeim->name, 'teams', 'middle'));
                $template->setVariable('previous_imgHomeBig', $lmo_mini_bilder->bild((string)$vorherHeim->name, 'teams', 'big'));
                $template->setVariable('previous_imgGuestSmall', $lmo_mini_bilder->bild((string)$vorherGast->name, 'teams', 'small'));
                $template->setVariable('previous_imgGuestMiddle', $lmo_mini_bilder->bild((string)$vorherGast->name, 'teams', 'middle'));
                $template->setVariable('previous_imgGuestBig', $lmo_mini_bilder->bild((string)$vorherGast->name, 'teams', 'big'));

                $template->setVariable('previous_homeName', (string)$vorherHeim->name);
                $template->setVariable('previous_guestName', (string)$vorherGast->name);
                $template->setVariable('previous_homeNameMiddle', (string)$vorherHeim->mittel);
                $template->setVariable('previous_guestNameMiddle', (string)$vorherGast->mittel);
                $template->setVariable('previous_homeNameShort', (string)$vorherHeim->kurz);
                $template->setVariable('previous_guestNameShort', (string)$vorherGast->kurz);

                $template->setVariable('previous_hTore', $lastPartie->toreHeimText());
                $template->setVariable('previous_gTore', $lastPartie->toreGastText());
                $template->setVariable('previous_gameTxt', $text['mini'][7]);
                $template->parseCurrentBlock();
            }

            $archivPaarungen = array();
            $archivSortDummy = array();
            // determine games of the current league
            $nameB = (string)$liga->daten((int)$team_b)->name;
            foreach ($team_a === null || $team_b === null ? array() : $liga->zwischen($team_a, $team_b, true) as $spiel) {
                if ($spiel->toreHeim != -1 && $spiel->toreGast != -1) {
                    $archivSortDummy[] = $spiel->zeit;
                    $archivPaarungen[] = array('time' => $spiel->zeit, 'where' => $spiel->heim == $team_a ? $text['mini'][13] : $text['mini'][12], 'partie' => $spiel, 'match' => NULL);
                }
            }

            // Read archive folder (die Ligadateien darin in der Reihenfolge des Verzeichnisses)
            $lmo_mini_archiv = PATH_TO_LMO . '/' . $dirliga . $archivFolder;
            $dataArray = array();
            if ($mini_withArchiv == 1) {
                if (!file_exists($lmo_mini_archiv)) {
                    echo getMessage($text['mini'][6].' '.$lmo_mini_archiv, true);
                } else {
                    $lmo_mini_verzeichnis = dir($lmo_mini_archiv);
                    while ($lmo_mini_verzeichnis && ($data = $lmo_mini_verzeichnis->read()) !== false) {
                        $punkt = strrpos($data, '.');
                        if ($punkt !== false && strtolower(substr($data, $punkt + 1)) == 'l98') {
                            $dataArray[] = $lmo_mini_verzeichnis->path . $data;
                        }
                    }
                    if ($lmo_mini_verzeichnis) {
                        $lmo_mini_verzeichnis->close();
                    }
                }
            }

            $nameA = (string)$liga->daten((int)$team_a)->name;
            foreach ($dataArray as $ligaFile) {
                if ($ligaFile != $lmo_mini_datei && is_file($ligaFile)) {
                    $newLiga = \Lmo\Liga\Begegnungen::aus(\Lmo\Liga\Rohdaten\Liga::lies($ligaFile));
                    $newTeam_a = $newLiga->mannschaftNamens($nameA);
                    $seachNames = $mini_unGreedy == 1 ? \Lmo\Liga\Begegnungen::aehnlicheNamen($newLiga->namen(), $nameB) : NULL;  // ungreedy Searching
                    if (isset($seachNames) && count($seachNames) == 1) {
                        $newTeam_b = $newLiga->mannschaftNamens($seachNames[0]);  // Ungreedy Searching was successful
                        $match = $seachNames[0];
                    } else {
                        $newTeam_b = $newLiga->mannschaftNamens($nameB);  // Searching was too imprecise (more than one result)
                        $match = NULL;
                    }
                    if (!is_null($newTeam_a) && !is_null($newTeam_b)) {
                        foreach ($newLiga->zwischen($newTeam_a, $newTeam_b, true) as $spiel) {
                            if ($spiel->toreHeim != -1 && $spiel->toreGast != -1) {
                                $archivSortDummy[] = $spiel->zeit;
                                $archivPaarungen[] = array('time' => $spiel->zeit, 'where' => $spiel->heim == $newTeam_a ? $text['mini'][13] : $text['mini'][12], 'partie' => $spiel, 'match' => $match);
                            }
                        }
                    }
                }
            }
            // die neuesten zuerst
            $lmo_mini_reihenfolge = array_keys($archivPaarungen);
            usort($lmo_mini_reihenfolge, static fn (int $x, int $y): int => array($archivSortDummy[$y], $archivPaarungen[$y]['where']) <=> array($archivSortDummy[$x], $archivPaarungen[$x]['where']));
            $archivPaarungen = array_map(static fn (int $i): array => $archivPaarungen[$i], $lmo_mini_reihenfolge);

            $spAnzahl = count($archivPaarungen);

            $template->setCurrentBlock('matches');  // inner block with the matches

            $lostCount = $drawCount = $winCount = 0;

            foreach ($archivPaarungen as $paarung) {
                $template->setVariable('date', $paarung['partie']->datum());
                $template->setVariable('hTore', $paarung['partie']->toreHeimText());
                $template->setVariable('gTore', $paarung['partie']->toreGastText());
                $template->setVariable('where', $paarung['where']);
                if (isset($paarung['match']) && strtolower($paarung['match']) != strtolower($nameB)) {
                    $template->setVariable('matchingName', '<acronym title="' . $paarung['match'] . '">*</acronym>');
                }
                $valuate = $paarung['partie']->wertung();
                if ($paarung['where'] == $text['mini'][13] && $valuate == 1) {
                    $template->setVariable('class', 'win');
                    $template->setVariable('hTore', $paarung['partie']->toreHeimText());
                    $template->setVariable('gTore', $paarung['partie']->toreGastText());
                    $winCount++;
                } elseif ($paarung['where'] == $text['mini'][12] && $valuate == 2) {
                    $template->setVariable('class', 'win');
                    $template->setVariable('hTore', $paarung['partie']->toreGastText());
                    $template->setVariable('gTore', $paarung['partie']->toreHeimText());
                    $winCount++;
                } elseif ($paarung['where'] == $text['mini'][13] && $valuate == 2) {
                    $template->setVariable('class', 'lost');
                    $template->setVariable('hTore', $paarung['partie']->toreHeimText());
                    $template->setVariable('gTore', $paarung['partie']->toreGastText());
                    $lostCount++;
                } elseif ($paarung['where'] == $text['mini'][12] && $valuate == 1) {
                    $template->setVariable('class', 'lost');
                    $template->setVariable('hTore', $paarung['partie']->toreGastText());
                    $template->setVariable('gTore', $paarung['partie']->toreHeimText());
                    $lostCount++;
                } elseif ($valuate == 0) {
                    $template->setVariable('class', 'draw');
                    $template->setVariable('hTore', $paarung['partie']->toreHeimText());
                    $template->setVariable('gTore', $paarung['partie']->toreGastText());
                    $drawCount++;
                } else {
                    $template->setVariable('class', 'noResult');
                }
                $template->parseCurrentBlock();
            }

            $w = intval($mini_barWidth * $winCount / ($spAnzahl + .1));
            $d = intval($mini_barWidth * $drawCount / ($spAnzahl + .1));
            $l = intval($mini_barWidth * $lostCount / ($spAnzahl + .1));

            $template->setCurrentBlock('main');
            $template->setVariable('matchesTxt', $text['mini'][4]);
            $template->setVariable('winCount', $winCount);
            $template->setVariable('drawCount', $drawCount);
            $template->setVariable('lostCount', $lostCount);
            $template->setVariable('matchCount', count($archivPaarungen));
            $template->setVariable('winWidth', $w);
            $template->setVariable('drawWidth', $d);
            $template->setVariable('lostWidth', $l);
            $template->setVariable('winTxt', $text['mini'][9]);
            $template->setVariable('drawTxt', $text['mini'][10]);
            $template->setVariable('lostTxt', $text['mini'][11]);
        }
        $mini_output = $template->get();

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
