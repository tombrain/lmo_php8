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

/**
 * Die Liste der Ligen eines Ordners im Adminbereich ("Liga oeffnen"; Lmo\Web\Seiten\Ligenliste mit
 * der Vorlage vorlagen/ligenliste.html.twig). Die Sortierung merkt sich die Sitzung wie bisher.
 *
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var array<int|string, mixed> $text Sprachtexte
 * @var string $dirliga Ligenordner relativ zum LMO (Konfiguration)
 * @var string $subdir Unterordner des Ligenordners
 * @var string $liga_sort Sortierung der Liste (Konfiguration)
 * @var string $liga_sort_direction Richtung der Sortierung (Konfiguration)
 * @var string $defdateformat Datumsformat in date()-Schreibweise (Konfiguration)
 */

$todo = isset($_GET['todo']) && $_GET['todo'] != '' ? $_GET['todo'] : (isset($_GET['file']) && $_GET['file'] != '' ? 'edit' : '');
$_SESSION['liga_sort'] = isset($_REQUEST['liga_sort']) ? $_REQUEST['liga_sort'] : $liga_sort;
$_SESSION['liga_sort_direction'] = isset($_REQUEST['liga_sort_direction']) ? $_REQUEST['liga_sort_direction'] : $liga_sort_direction;
$subdir = str_replace(array('../', './'), array('', ''), $subdir);
$lmo_ligenliste = new \Lmo\Web\Seiten\Ligenliste(
    \Lmo\Web\Sprachtexte::ausAltemText($text)->kern,
    new \Lmo\Web\Darstellung(
        static fn (string $datei, string $name, string $attribute): string => '',
        static fn (string $datei, string $attribute): string => (string)HTML_smallLigaIcon($datei, $attribute, '')
    ),
    PATH_TO_LMO . '/vorlagen',
    URL_TO_IMGDIR,
    URL_TO_LMO,
    static fn (string $datei): bool => check_hilfsadmin($datei)
);
echo $lmo_ligenliste->zeige(
    rtrim(PATH_TO_LMO . '/' . $dirliga . $subdir, '/') . '/',
    $subdir,
    $_SERVER['PHP_SELF'],
    $_SERVER['PHP_SELF'] . '?todo=' . $todo . '&amp;file=',
    isset($_SESSION['lmouserok']) && $_SESSION['lmouserok'] > 0,
    (string)$_SESSION['liga_sort'],
    (string)$_SESSION['liga_sort_direction'],
    $defdateformat
);
