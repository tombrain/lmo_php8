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
  *
  */
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

/**
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var string $file Ligadatei relativ zum Ligenordner, leer wenn keine geladen ist
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $diroutput Ordner fuer erzeugte Dateien (Konfiguration)
 * @var string $addt1 Link zur Tabelle, es fehlt nur die Tabellenart
 * @var int|string $tabtype Tabellenart: 0 gesamt, 1 heim, 2 auswaerts, 3 Hinrunde, 4 Rueckrunde
 * @var int|string $tabonres Einstellung tabonres (Konfiguration)
 * @var int|string $tabpkt Punktespalte: 0 in der Mitte, 1 am rechten Rand (Konfiguration)
 * @var string $tabdat Stand der Tabelle als Text
 * @var int|string $endtab Spieltag, bis zu dem die Tabelle gerechnet ist
 * @var int|string $einzutoretab Torstatistik unter der Tabelle (Konfiguration)
 * @var int|float|string $gzutore Tore der ganzen Saison (lmo-zustat.php)
 * @var int|float|string $gdstore Tore je Spiel der ganzen Saison (lmo-zustat.php)
 * @var \Lmo\Liga\Tabelle $lmo_tabelle die Tabelle (lmo-showrestab.php)
 * @var list<int> $lmo_tab_plaetze Nummern der Mannschaften vom ersten zum letzten Platz (lmo-showrestab.php)
 * @var \Lmo\Liga\Tabelle $lmo_tab_heim Heimbilanz, nur bei tabonres=2 (lmo-showrestab.php)
 * @var \Lmo\Liga\Tabelle $lmo_tab_auswaerts Auswaertsbilanz, nur bei tabonres=2 (lmo-showrestab.php)
 * @var array<int|string, mixed> $platz0 Platz je Mannschaft (lmo-showrestab.php)
 * @var array<int|string, mixed> $platz1 Platz je Mannschaft am Spieltag davor (lmo-showrestab.php)
 */

// Einstellungen der Liga mit Vorgabewerten (Lmo\Liga\Einstellungen)
$lmo_einstellungen = $lmo_liga->einstellungen();
$breite = 11;
if ($lmo_einstellungen->mitMinuspunkten()) {
    $breite = $breite+2;
}

// Auswahl der Tabellenart ueber der Tabelle: Gesamt, Heim, Auswaerts, Hinrunde, Rueckrunde
$lmo_auswahl = array();
if ($lmo_einstellungen->mitHinRueckrundentabelle() || $lmo_einstellungen->mitHeimAuswaertstabelle()) {
    $lmo_arten = array(array(0, 16, 27));
    if ($tabonres != 2 && $lmo_einstellungen->mitHeimAuswaertstabelle()) {
        $lmo_arten[] = array(1, 41, 28);
        $lmo_arten[] = array(2, 42, 29);
    }
    if ($lmo_einstellungen->mitHinRueckrundentabelle()) {
        $lmo_arten[] = array(4, 4003, 4003);
        $lmo_arten[] = array(3, 4002, 4002);
    }
    foreach ($lmo_arten as list($lmo_art, $lmo_text, $lmo_titel)) {
        $lmo_auswahl[] = array(
            'art' => $lmo_art,
            'text' => $text[$lmo_text],
            'titel' => $text[$lmo_titel],
            'link' => $tabtype != $lmo_art ? $addt1 . $lmo_art : null,
        );
    }
}

// Werte einer Zeile zum Anzeigen, Punkte und Tore mit den Faktoren der Liga
$lmo_werte = static function (\Lmo\Liga\Tabellenzeile $lmo_z) use ($lmo_einstellungen): array {
    return array(
        'spiele' => $lmo_z->spiele,
        'siege' => $lmo_z->siege,
        'unentschieden' => $lmo_z->unentschieden,
        'niederlagen' => $lmo_z->niederlagen,
        'punkte' => applyFactor($lmo_z->punkte, $lmo_einstellungen->punktfaktor()),
        'minuspunkte' => applyFactor($lmo_z->minuspunkte, $lmo_einstellungen->punktfaktor()),
        'tore' => applyFactor($lmo_z->tore, $lmo_einstellungen->torfaktor()),
        'gegentore' => applyFactor($lmo_z->gegentore, $lmo_einstellungen->torfaktor()),
        'tordifferenz' => applyFactor($lmo_z->tordifferenz(), $lmo_einstellungen->torfaktor()),
    );
};

$lmo_zeilen = array();
$j = 1;
for ($x = 1; $x <= $lmo_einstellungen->mannschaften(); $x++) {
    // Mannschaft auf diesem Platz und ihre Zeile (Lmo\Liga\Tabellenzeile)
    $i = $lmo_tab_plaetze[$x - 1];
    $lmo_zeile = $lmo_tabelle->zeilen[$i];
    // die Mannschaft dieser Zeile mit ihren Strafen, Torkorrekturen, ihrer Notiz und Homepage
    $lmo_mannschaft = $lmo_liga->mannschaften[$i] ?? new \Lmo\Liga\Rohdaten\Mannschaft();
    $lmo_name = $lmo_mannschaft->anzeigename();
    $lmo_strafp = $lmo_mannschaft->werte['SP'] ?? '0';
    $lmo_strafm = $lmo_mannschaft->werte['SM'] ?? '0';
    $lmo_tor1 = $lmo_mannschaft->werte['TOR1'] ?? '0';
    $lmo_tor2 = $lmo_mannschaft->werte['TOR2'] ?? '0';
    $lmo_strafdat = $lmo_mannschaft->werte['STDA'] ?? '0';
    $lmo_notiz = $lmo_mannschaft->werte['NOT'] ?? '';
    $lmo_url = $lmo_mannschaft->werte['URL'] ?? '';

    // Farbe der Zeile: Meister, Europapokal, Relegation, Abstieg (nur in der Gesamttabelle)
    $lmo_tabelle_class = 'nobr';
    if ($tabtype == 0) {
        if (($x == 1) && $lmo_einstellungen->mitMeister()) {
            $lmo_tabelle_class = 'lmoTabelleMeister nobr';
            $j = 2;
        }
        if (($x >= $j) && ($x < $j+$lmo_einstellungen->plaetzeChampionsLeague()) && ($lmo_einstellungen->plaetzeChampionsLeague() > 0)) {
            $lmo_tabelle_class = 'lmoTabelleCleague nobr';
        }
        if (($x >= $j+$lmo_einstellungen->plaetzeChampionsLeague()) && ($x < $j+$lmo_einstellungen->plaetzeChampionsLeague()+$lmo_einstellungen->plaetzeClQualifikation()) && ($lmo_einstellungen->plaetzeClQualifikation() > 0)) {
            $lmo_tabelle_class = 'lmoTabelleCleaguequali nobr';
        }
        if (($x >= $j+$lmo_einstellungen->plaetzeChampionsLeague()+$lmo_einstellungen->plaetzeClQualifikation()) && ($x < $j+$lmo_einstellungen->plaetzeChampionsLeague()+$lmo_einstellungen->plaetzeClQualifikation()+$lmo_einstellungen->plaetzeUefaCup()) && ($lmo_einstellungen->plaetzeUefaCup() > 0)) {
            $lmo_tabelle_class = 'lmoTabelleUefa nobr';
        }
        if (($x <= $lmo_einstellungen->mannschaften()-$lmo_einstellungen->plaetzeAbstieg()) && ($x > $lmo_einstellungen->mannschaften()-$lmo_einstellungen->plaetzeAbstieg()-$lmo_einstellungen->plaetzeRelegation()) && ($lmo_einstellungen->plaetzeRelegation() > 0)) {
            $lmo_tabelle_class = 'lmoTabelleRelegation nobr';
        }
        if (($x <= $lmo_einstellungen->mannschaften()) && ($x > $lmo_einstellungen->mannschaften()-$lmo_einstellungen->plaetzeAbstieg()) && ($lmo_einstellungen->plaetzeAbstieg() > 0)) {
            $lmo_tabelle_class = 'lmoTabelleAbsteiger nobr';
        }
    }

    // Pfeil: 1 nach oben, 2 nach unten gegenueber dem Spieltag davor
    $y = 0;
    if ($endtab > 1 && isset($platz0[$i])) {
        if ($platz0[$i] < $platz1[$i]) {
            $y = 1;
        } elseif ($platz0[$i] > $platz1[$i]) {
            $y = 2;
        }
    }

    $lmo_tabellennotiz = '&nbsp;';
    if ($lmo_notiz != '' || $lmo_strafp != 0 || $lmo_strafm != 0 || $lmo_tor1 != 0 || $lmo_tor2 != 0) {
        /** Notizen anzeigen
        *
        * Achtung: Da beim Speichern Strafpunkte/-tore positiv sind und Bonuspunkte negativ (altes System des LMO)
        * muss mit -1 multipliziert werden, um die Punkte/Tore richtig anzuzeigen
        */
        $lmo_info = ' <strong>' . HTML_smallTeamIcon($file, $lmo_name, ' alt=""') . $lmo_name . '</strong>';
        //Straf-/Bonuspunkte
        if ($lmo_strafp != 0 || $lmo_strafm != 0) {
            $lmo_info .= '<strong>' . $text[128] . ':</strong>';
            //Punkte
            $lmo_info .= $lmo_strafp < 0 ? '+' . ((-1) * applyFactor($lmo_strafp, $lmo_einstellungen->punktfaktor())) : ((-1) * applyFactor($lmo_strafp, $lmo_einstellungen->punktfaktor()));
            //Minuspunkte
            if ($lmo_einstellungen->mitMinuspunkten()) {
                $lmo_info .= ':' . ($lmo_strafm < 0 ? '+' . ((-1) * applyFactor($lmo_strafm, $lmo_einstellungen->punktfaktor())) : ((-1) * applyFactor($lmo_strafm, $lmo_einstellungen->punktfaktor())));
            }
            //Ab Spieltag
            if ($lmo_strafdat != 0)
                $lmo_info .= '<br>' . $text[524] . '&nbsp;' . $text[2] . '&nbsp;' . $lmo_strafdat . '<br>';
        }
        //Straf-/Bonustore
        if ($lmo_tor1 != 0 || $lmo_tor2 != 0) {
            $lmo_info .= '<br>';
            $lmo_info .= '<strong>' . $text[522] . ':</strong>';
            //Tore
            $lmo_info .= $lmo_tor1 < 0 ? '+' . ((-1) * applyFactor($lmo_tor1, $lmo_einstellungen->torfaktor())) . ':' : ((-1) * applyFactor($lmo_tor1, $lmo_einstellungen->torfaktor())) . ':';
            //Gegentore
            $lmo_info .= $lmo_tor2 < 0 ? '+' . ((-1) * applyFactor($lmo_tor2, $lmo_einstellungen->torfaktor())) : ((-1) * applyFactor($lmo_tor2, $lmo_einstellungen->torfaktor()));
            //Ab Spieltag
            if ($lmo_strafdat != 0)
                $lmo_info .= '<br>' . $text[524] . '&nbsp;' . $text[2] . '&nbsp;' . $lmo_strafdat . '<br>';
        }
        //Teamnotizen
        if ($lmo_notiz != '') {
            $lmo_info .= '<br>';
            $lmo_info .= '<strong>' . $text[22] . ':</strong>' . $lmo_notiz;
        }
        //Ausgabe als neutrales Element (kein Link)
        $lmo_tabellennotiz = '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($lmo_info)) . '" class="tooltip-trigger"><img src="' . URL_TO_IMGDIR . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
    }

    $lmo_zeilen[] = array(
        'platz' => $x,
        'klasse' => $lmo_tabelle_class,
        'fett' => $i == $lmo_einstellungen->lieblingsmannschaft(),
        'pfeil' => $y,
        'symbol' => HTML_smallTeamIcon($file, $lmo_name, ' alt=""'),
        'name' => $lmo_name,
        'url' => ($lmo_url != '' && $lmo_einstellungen->mitMannschaftslinks()) ? $lmo_url : null,
        'notiz' => $lmo_tabellennotiz,
        'gesamt' => $lmo_werte($lmo_zeile),
        'heim' => $tabonres == 2 ? $lmo_werte($lmo_tab_heim->zeilen[$i]) : null,
        'auswaerts' => $tabonres == 2 ? $lmo_werte($lmo_tab_auswaerts->zeilen[$i]) : null,
    );
}

// Torstatistik der Saison unter der Tabelle (aus der Statistikdatei, lmo-zustat.php)
$lmo_torstatistik = '';
if ($einzutoretab == 1) {
    $zustat_file = str_replace('.l98', '.l98.php',  basename($file));
    $zustat_dir = basename($diroutput);
    if (file_exists(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file)) {
        require(PATH_TO_LMO . '/' . $zustat_dir . '/' . $zustat_file);
        $lmo_torstatistik = $text[4000] . $text[38] . ': ' . applyFactor($gzutore, $lmo_einstellungen->torfaktor()) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ' ' . $text[38] . '&nbsp;' . $text[4001] . ': ' . applyFactor($gdstore, $lmo_einstellungen->torfaktor());
    }
}

echo \Lmo\Web\Vorlagen::zeige(PATH_TO_LMO . '/vorlagen', 'tabelle.html.twig', array(
    'text' => $text,
    'bilder' => URL_TO_IMGDIR,
    'auswahl' => $lmo_auswahl,
    'tabonres' => (int)$tabonres,
    'tabpkt' => (int)$tabpkt,
    'tabdat' => (string)$tabdat,
    'breite' => $breite,
    'gesamtbreite' => $tabonres == 2 ? 3 * $breite + 11 : $breite + 11,
    'punkte_attr' => $lmo_einstellungen->mitMinuspunkten() ? ' colspan="3" align="center"' : ' align="right"',
    'minus' => $lmo_einstellungen->mitMinuspunkten(),
    'ohne_unentschieden' => $lmo_einstellungen->ohneUnentschieden(),
    'zeilen' => $lmo_zeilen,
    'torstatistik' => $lmo_torstatistik,
));
