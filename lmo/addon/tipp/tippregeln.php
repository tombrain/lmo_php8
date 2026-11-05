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


require(__DIR__ . '/../../init.php');
$_SESSION['lmouserlang'] = isset($_GET['lmouserlang']) ? $_GET['lmouserlang'] : (isset($_SESSION['lmouserlang']) ? $_SESSION['lmouserlang'] : 'Deutsch');

// die Tippregeln (Lmo\Addon\Tipp\Tippregeln) mit den Einstellungen, wie sie gerade gelten
$lmo_regeln_cfg = array();
foreach (array_keys($cfgarray['tipp'] ?? array()) as $lmo_regeln_name) {
  $lmo_regeln_cfg[$lmo_regeln_name] = (string)(${'tipp_' . $lmo_regeln_name} ?? '');
}
echo \Lmo\Addon\Tipp\Tippregeln::html($lmo_regeln_cfg, (string)$_SESSION['lmouserlang']);
