<?php
/* Liga Manager Online 4
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

require_once(__DIR__ . '/../../init.php');

/**
 * Diese Datei wird direkt aufgerufen; init.php stellt bereit:
 *
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $dirliga Ligenordner relativ zum LMO (Konfiguration)
 *
 * Einstellungen:
 * @var int|string $ticker_standard_ligen Einstellung aus ticker/cfg.txt
 *
 * Werden auch in dieser Datei gesetzt, aber nicht auf jedem Weg (koennen von aussen kommen):
 * @var mixed $ticker_breite
 * @var mixed $ticker_geschwindigkeit
 * @var mixed $ticker_notizanzeigen
 * @var mixed $ticker_tickerart
 * @var mixed $ticker_tickertitel
 */

// Durch gesetzte $tickerart bestimmter Parameter (für include/require)
$ticker_tickerart = isset($tickerart) ? $tickerart : $ticker_tickerart;
$ticker_ligen = isset($tickerligen) ? $tickerligen : $ticker_standard_ligen;
$ticker_tickertitel = isset($tickertitel) ? $tickertitel : $ticker_tickertitel;
$ticker_notizanzeigen = isset($tickernotizen) ? $tickernotizen : $ticker_notizanzeigen;
$ticker_breite = isset($tickerbreite) ? $tickerbreite : $ticker_breite;
$ticker_geschwindigkeit = isset($tickergeschwindigkeit) ? $tickergeschwindigkeit : $ticker_geschwindigkeit;

// Die Ligen werden als Rohdaten gelesen (Lmo\Liga\Rohdaten\Liga); die Liga der Seite in $lmo_liga bleibt unberuehrt.
$lmo_ticker_ligen = array();
foreach (explode(",", $ticker_ligen) as $lmo_ticker_datei) {
    $lmo_ticker_pfad = PATH_TO_LMO . '/' . $dirliga . $lmo_ticker_datei;
    if (!empty($lmo_ticker_datei) && file_exists($lmo_ticker_pfad) && check_hilfsadmin($lmo_ticker_datei)) {
        $lmo_ticker_ligen[] = substr($lmo_ticker_datei, -4) == '.l98' ? \Lmo\Liga\Rohdaten\Liga::lies($lmo_ticker_pfad) : new \Lmo\Liga\Rohdaten\Liga();
    } else {
        $lmo_ticker_ligen[] = null;
    }
}
// der Lauftext (Lmo\Addon\Ticker\Ticker)
$lmo_ticker_texte = \Lmo\Web\Sprachtexte::ausAltemText($text);
$lmo_ticker = new \Lmo\Addon\Ticker\Ticker((int)$ticker_tickerart, $ticker_notizanzeigen == 1, $lmo_ticker_texte->kern, $lmo_ticker_texte->von('ticker'));
echo $lmo_ticker->html(PATH_TO_LMO . '/vorlagen', URL_TO_LMO, $lmo_ticker->text($lmo_ticker_ligen), $ticker_tickertitel == 1, (string)$ticker_breite, (string)$ticker_geschwindigkeit);
