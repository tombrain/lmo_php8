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


// Tipp noch änderbar oder nicht (Lmo\Addon\Tipp\Tippzeit). Gezeigten Spieltag, geladene Liga und den
// Sicherheitsabstand gibt der Aufrufer mit; die Einstellungen liest lmo_tippzeit() aus der Konfiguration.

/**
 * Die Tippzeit nach den Einstellungen des Tippspiels (tipp/cfg.txt, deftime aus cfg.txt).
 */
function lmo_tippzeit(): \Lmo\Addon\Tipp\Tippzeit {
    global $tipp_imvorraus;
    global $tipp_tippBis;
    global $tipp_tippohne;
    global $deftime;

    return new \Lmo\Addon\Tipp\Tippzeit((int)$tipp_tippBis, $tipp_tippohne == 1, (string)$deftime, (int)$tipp_imvorraus);
}

/**
 * @param mixed $st gezeigter Spieltag
 * @param \Lmo\Liga\Rohdaten\Liga|null $lmo_liga geladene Liga (ihr aktueller Spieltag)
 * @param int $plus eine Minute Sicherheitsabstand zwischen Seitenaufruf und abspeichern
 */
function tippaenderbar($mterm0, $datum1, $datum2, $st, $lmo_liga, $plus) {
    // aktueller Spieltag der geladenen Liga
    $stx = isset($lmo_liga) ? $lmo_liga->einstellungen()->aktuellerSpieltag() : 0;

    return lmo_tippzeit()->aenderbar((int)$mterm0, (string)$datum1, (string)$datum2, is_int($st) || is_string($st) ? $st : null, $stx, (int)$plus);
}

function zeit($mterm0, $datum1, $datum2) {
    return lmo_tippzeit()->bis((int)$mterm0, (string)$datum1, (string)$datum2) ?? '';
}
?>
