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
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der Liga: der geladene oder gerade gespeicherte Stand
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $st gewaehlter Spieltag
 * @var string $todo gewaehlte Aktion (Aufrufparameter)
 *
 * Weitere Variablen des einbindenden Codes:
 * @var mixed $addb
 * @var mixed $addr
 * @var \Lmo\Addon\Addons $lmo_admin_addons Addons der Installation (lmo-adminmain.php)
 * @var \Lmo\Web\Kontext $lmo_admin_kontext Kontext der Anfrage (lmo-adminmain.php)
 */


// die Navigation beim Bearbeiten einer Liga (Lmo\Web\Admin\LigaNavigation, Vorlage admin_liganavigation.html.twig)
echo \Lmo\Web\Admin\LigaNavigation::html($lmo_admin_kontext, $lmo_admin_addons, $lmo_liga, (string)$todo, (string)$st, (string)$addr, (string)$addb);
