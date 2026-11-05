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
$LastClass = '';

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
// If Cache File is present and up to date, print this out.
if (is_readable($TableCacheFile) && $cfgarray['mini']['CacheTable'] == 1) {
    $CacheFile = file ($TableCacheFile);
    if (trim(array_shift($CacheFile)) == (filemtime(PATH_TO_LMO . '/' . $dirliga . $m_liga) . '-' . $m_template . '|$m_ueber|$m_unter|$m_platz')) {
        // echo 'Cache Output: <br>';
        $CacheOutput = print implode('', $CacheFile);
    }
}

if (empty($CacheOutput)) {
    //'Direkt Output: <br>';
    // die Liga und ihre Tabelle wie im Kern (Lmo\Liga\Rohdaten\Liga, Lmo\Liga\Tabelle; bis 4.5 die Classlib)
    $lmo_mini_datei = PATH_TO_LMO . '/' . $dirliga . $m_liga;
    $lmo_mini_liga = is_readable($lmo_mini_datei) && $m_liga && !is_dir($lmo_mini_datei) ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_mini_datei) : null;
    if ($lmo_mini_liga !== null) {
        $template = new LMO_Template( PATH_TO_TEMPLATEDIR . '/mini' );
        $template->loadTemplatefile($m_template . '.tpl.php');
        $lmo_mini_einstellungen = $lmo_mini_liga->einstellungen();
        $lmo_mini_bilder = new \Lmo\Web\Bilder(PATH_TO_IMGDIR, URL_TO_IMGDIR);
        // die Tabelle bis zu einem Spieltag als Zeilen wie bisher: Platz, Mannschaft, Punkte, Tore, Spiele
        $lmo_mini_tabelle = static function (int $bis) use ($lmo_mini_liga): array {
            $tabelle = \Lmo\Liga\Tabelle::berechne($lmo_mini_liga, \Lmo\Liga\Tabelle::GESAMT, 1, $bis);
            // mit Handicap in der Reihenfolge nach der Angabe des Spieltags (HS), wie die Haupttabelle;
            // bis zum letzten Spieltag gerechnet gilt die des aktuellen
            $e = $lmo_mini_liga->einstellungen();
            $runde = $bis >= $e->spieltage() ? max(1, $e->aktuellerSpieltag()) : $bis;
            $reihenfolge = $e->mitHandicap()
                ? $tabelle->reihenfolgeNachHandicap(isset($lmo_mini_liga->spieltage[$runde]) ? (string)($lmo_mini_liga->spieltage[$runde]->werte['HS'] ?? '0') : '0')
                : $tabelle->reihenfolge;
            $zeilen = array();
            foreach ($reihenfolge as $i => $nr) {
                $z = $tabelle->zeilen[$nr];
                $m = $lmo_mini_liga->mannschaften[$nr] ?? new \Lmo\Liga\Rohdaten\Mannschaft();
                $zeilen[] = array('pos' => $i + 1, 'nr' => $nr, 'name' => (string)$m->name, 'kurz' => (string)$m->kurz, 'mittel' => (string)$m->mittel,
                    'notiz' => (string)($m->werte['NOT'] ?? ''), 'pPkt' => $z->punkte, 'mPkt' => $z->minuspunkte, 'pTor' => $z->tore, 'mTor' => $z->gegentore,
                    'spiele' => $z->spiele, 's' => $z->siege, 'u' => $z->unentschieden, 'n' => $z->niederlagen);
            }
            return $zeilen;
        };
        $lmo_mini_wert = static fn (string $schluessel): string => (string)$lmo_mini_einstellungen->wert($schluessel);

        $LigaTabelle = $lmo_mini_tabelle($lmo_mini_einstellungen->spieltage());
        $AnzahlTeams = count($LigaTabelle);
        // Tendenz = Platz am Vorspieltag minus Platz jetzt. Vor dem ersten Spieltag gibt es keinen
        // Vorspieltag: Tendenz 0 fuer alle.
        $LastGameDay = $lmo_mini_einstellungen->aktuellerSpieltag() > 1
            ? $lmo_mini_tabelle($lmo_mini_einstellungen->aktuellerSpieltag() - 1)
            : $LigaTabelle;
        $Favorit = $lmo_mini_einstellungen->lieblingsmannschaft();
        $viewPosition = 1;
        if (is_null($m_platz) || $m_platz <= 0 || $m_platz > $AnzahlTeams ) {
            foreach ($LigaTabelle as $value) {
                if ($Favorit == $value['nr']) {
                    $viewPosition = $value['pos'];
                }
            }
        } else {
            $viewPosition = $m_platz;
        }
        $beginTabellenPlatz = $viewPosition - $m_unter;
        $endTabellenPlatz = $viewPosition + $m_ueber;
        if ($beginTabellenPlatz <= 0) {
            $beginTabellenPlatz = 1;
            $endTabellenPlatz = $m_unter + 1 + $m_ueber < $AnzahlTeams ? $m_unter + 1 + $m_ueber : $AnzahlTeams;
        }
        if ($endTabellenPlatz > $AnzahlTeams ) {
            $endTabellenPlatz = $AnzahlTeams;
            $beginTabellenPlatz = $AnzahlTeams - $m_unter - 1 - $m_ueber > 0 ? $AnzahlTeams - $m_unter - 1 - $m_ueber : 1;
        }

        for ($j = 0; $j < $AnzahlTeams ; $j++) {
            if ($LigaTabelle[$j]['pos'] >= $beginTabellenPlatz && $LigaTabelle[$j]['pos'] <= $endTabellenPlatz ) {
                $template->setCurrentBlock('Inhalt');
                $template->setVariable('Platz', '<strong>' . $LigaTabelle[$j]['pos'] . '</strong>');
                $template->setVariable('TeamBild', $lmo_mini_bilder->bild($LigaTabelle[$j]['name'], 'teams', 'small'));
                $template->setVariable('TeamBildMiddle', $lmo_mini_bilder->bild($LigaTabelle[$j]['name'], 'teams', 'middle'));
                $template->setVariable('TeamBildBig', $lmo_mini_bilder->bild($LigaTabelle[$j]['name'], 'teams', 'big'));
                $template->setVariable('TeamLang', $LigaTabelle[$j]['name']);
                $template->setVariable('TeamMittel', $LigaTabelle[$j]['mittel']);
                $template->setVariable('Team', $LigaTabelle[$j]['kurz']);
                $template->setVariable('Teamnotiz', $LigaTabelle[$j]['notiz']);
                if ($lmo_mini_wert('MinusPoints') == 1) {
                    $template->setVariable('Punkte', $LigaTabelle[$j]['pPkt']);
                } else {
                    $template->setVariable('Punkte', $LigaTabelle[$j]['pPkt'] . ':' . $LigaTabelle[$j]['mPkt']);
                }
                $template->setVariable('PlusTore', $LigaTabelle[$j]['pTor']);
                $template->setVariable('MinusTore', $LigaTabelle[$j]['mTor']);
                if (($m_diff = $LigaTabelle[$j]['pTor'] - $LigaTabelle[$j]['mTor']) > 0) {
                    $m_diff = '+' . $m_diff;
                }
                $template->setVariable('Tordifferenz', $m_diff);
                // Dart League
                if (isset($m_tabelle)) {
                    //not sure if this code is still functional
                    $template->setVariable('PlusSaetze', $m_tabelle[$j][12]);
                    $template->setVariable('MinusSaetze', $m_tabelle[$j][13]);
                    if (( $satzDiff = $m_tabelle[$j][12] - $m_tabelle[$j][13]) > 0 ) {
                        $satzDiff = '+' . $satzDiff;
                    }
                    $template->setVariable('SatzDifferenz', $satzDiff);
                }
                // Dart Liga
                $template->setVariable('Spiele', $LigaTabelle[$j]['spiele']);
                $template->setVariable('Siege', $LigaTabelle[$j]['s']);
                $template->setVariable('Unentschieden', $LigaTabelle[$j]['u']);
                $template->setVariable('Niederlagen', $LigaTabelle[$j]['n']);
                $Tendenz = 0;
                foreach ($LastGameDay as $LetzterSpieltag) {
                    if ($LigaTabelle[$j]['nr'] == $LetzterSpieltag['nr']) {
                        $Tendenz = $LetzterSpieltag['pos'] - $LigaTabelle[$j]['pos'];
                        $template->setVariable('Tendenz', ($Tendenz > 0 ? '+' . $Tendenz : $Tendenz));
                        break;
                    }
                }
                $TendenzImgFile = $Tendenz > 0 ? $cfgarray['mini']['TendenzPfeilUpImg'] : ( $Tendenz < 0 ? $cfgarray['mini']['TendenzPfeilDownImg'] : $cfgarray['mini']['TendenzPunktImg']);
                $template->setVariable(array('TendenzIMG' => '<img src="' . URL_TO_IMGDIR . '/' . $TendenzImgFile . '" border="0" title="' . $Tendenz . '">'));
                // Calculate style classes using the liga options -- to use old templates, style is also output
                $style = '';
                $css_class = '';

                // Striped Tables
                if (!empty($cfgarray['mini']['stripedTable'])) {
                    $stripedColor = explode(';', trim($cfgarray['mini']['stripedTable']));
                    $style .= $j % 2 == 0 ? 'background-color: ' . $stripedColor[0] . ';\n' : 'background-color: ' . $stripedColor[1] . ';\n';
                }

                if ($LigaTabelle[$j]['pos'] <= $lmo_mini_wert('Champ')) {
                    // Meister
                    $css_class = isset($cfgarray['mini']['tabelle_classMeister'])? $cfgarray['mini']['tabelle_classMeister'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background1']) ? 'background: ' . $cfgarray['lmo_tabelle_background1'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color1']) ? 'color: ' . $cfgarray['lmo_tabelle_color1'] . ';' : '';
                } elseif ($LigaTabelle[$j]['pos'] <= ($lmo_mini_wert('Champ') + $lmo_mini_wert('CL'))) {
                    // Champions League
                    $css_class = isset($cfgarray['mini']['tabelle_classCLAufsteiger']) ? $cfgarray['mini']['tabelle_classCLAufsteiger'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background2']) ? 'background: ' . $cfgarray['lmo_tabelle_background2'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color2']) ? 'color: ' . $cfgarray['lmo_tabelle_color2'] . ';' : '';
                } elseif ($LigaTabelle[$j]['pos'] <= ($lmo_mini_wert('Champ') + $lmo_mini_wert('CL') + $lmo_mini_wert('CK'))) {
                    // CL Qualifikation
                    $css_class = isset($cfgarray['mini']['tabelle_classCLQuali']) ? $cfgarray['mini']['tabelle_classCLQuali'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background3']) ? 'background: ' . $cfgarray['lmo_tabelle_background3'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color3']) ? 'color: ' . $cfgarray['lmo_tabelle_color3'] . ';' : '';
                } elseif ($LigaTabelle[$j]['pos'] <= ($lmo_mini_wert('Champ') + $lmo_mini_wert('CL') + $lmo_mini_wert('CK') + $lmo_mini_wert('UC'))) {
                    // UEFA Cup
                    $css_class = isset($cfgarray['mini']['tabelle_classUEFA']) ? $cfgarray['mini']['tabelle_classUEFA'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background4']) ? 'background: ' . $cfgarray['lmo_tabelle_background4'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color4']) ? 'color: ' . $cfgarray['lmo_tabelle_color4'] . ';' : '';
                } elseif ($LigaTabelle[$j]['pos'] > ($AnzahlTeams-$lmo_mini_wert('AB'))) {
                    // Abstiegsplätze
                    $css_class = isset($cfgarray['mini']['tabelle_classAbsteiger']) ? $cfgarray['mini']['tabelle_classAbsteiger'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background6']) ? 'background: ' . $cfgarray['lmo_tabelle_background6'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color6']) ? 'color: ' . $cfgarray['lmo_tabelle_color6'] . ';' : '';
                } elseif ($LigaTabelle[$j]['pos'] > ($AnzahlTeams-$lmo_mini_wert('AB') - $lmo_mini_wert('AR'))) {
                    // Abstiegs Relegation
                    $css_class = isset($cfgarray['mini']['tabelle_classAbstiegsRelegation']) ? $cfgarray['mini']['tabelle_classAbstiegsRelegation'] : '';
                    $style = isset($cfgarray['lmo_tabelle_background5']) ? 'background: ' . $cfgarray['lmo_tabelle_background5'] . ' repeat;' : '';
                    $style .= isset($cfgarray['lmo_tabelle_color5']) ? 'color: ' . $cfgarray['lmo_tabelle_color5'] . ';' : '';
                } else {
                    $css_class = '';     // 'Normale Tabellenplaetze
                }

                // Lined Style Tables
                if ($j && $LastClass != $css_class && !empty($cfgarray['mini']['StyleStripLine'])) {
                    $style .= 'border-top: ' . $cfgarray['mini']['StyleStripLine'] . ';';
                }
                $LastClass = $css_class;

                //FavTeam
                if ($Favorit > 0 && $LigaTabelle[$j]['nr'] == $Favorit) {
                    $style .= 'font-weight:bolder;';
                    $css_class .= $cfgarray['mini']['classFavorit'];
                }

                $template->setVariable(array('Style' => $style, 'Class' => $css_class));
                $template->parseCurrentBlock();
            }
        }
        $template->setVariable('LigaBild', $lmo_mini_bilder->bild(basename($m_liga), 'liga', 'small'));
        $template->setVariable('LigaBildMiddle', $lmo_mini_bilder->bild(basename($m_liga), 'liga', 'middle'));
        $template->setVariable('LigaBildBig', $lmo_mini_bilder->bild(basename($m_liga), 'liga', 'big'));
        $template->setVariable('ligaDatum', $text['mini'][14] . ': ' . date('d.m.Y', (int)filemtime($lmo_mini_datei)));
        $template->setVariable('URL_TO_LMO', URL_TO_LMO);
        $template->setVariable('URL_TO_TEMPLATEDIR', URL_TO_TEMPLATEDIR . '/mini/');
        $template->setVariable('Link', URL_TO_LMO . '/?action=table&amp;file=' . $m_liga);
        $template->setVariable('Tabelle', $text[16]);
        // write cache File
        if ( is_writable(dirname($TableCacheFile)) && $cfgarray['mini']['CacheTable'] == 1 ) {
            $CacheOutput = filemtime(PATH_TO_LMO . '/' . $dirliga . $m_liga) . '-' . $m_template . '|$m_ueber|$m_unter|$m_platz\n' . $template->get();
            if ($fileHandle = @fopen($TableCacheFile, 'wb')) {
                fwrite($fileHandle, $CacheOutput);
                fclose($fileHandle);
            }
        }
        $template->show();
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
