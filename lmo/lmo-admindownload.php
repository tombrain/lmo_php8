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

session_start();
require(__DIR__ . '/init.php');

// Ligen herunterladen (Lmo\Web\Admin\LigaDownload), aus Administration > Herunterladen

$lmo_download = (new \Lmo\Web\Admin\LigaDownload(
    PATH_TO_LMO . '/' . $dirliga,
    PATH_TO_LMO . '/' . $diroutput,
    $text,
    static fn (string $datei): bool => check_hilfsadmin($datei),
    URL_TO_IMGDIR
))->antwort($_GET, (int)($_SESSION['lmouserok'] ?? 0));
if ($lmo_download !== null) {
    foreach ($lmo_download['kopf'] as $lmo_kopf) {
        header($lmo_kopf);
    }
    echo $lmo_download['inhalt'];
}
