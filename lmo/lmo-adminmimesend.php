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

// Liga per E-Mail (Lmo\Web\Admin\LigaMailSeite), eigenes Fenster aus Administration > Herunterladen
session_start();
require(__DIR__ . '/init.php');

echo (new \Lmo\Web\Admin\LigaMailSeite(
    PATH_TO_LMO . '/' . $dirliga,
    PATH_TO_LMO . '/' . $diroutput,
    $text,
    static fn (string $datei): bool => check_hilfsadmin($datei),
    static fn (): \PHPMailer\PHPMailer\PHPMailer => lmo_mailer($aadr, $text[341]),
    URL_TO_LMO,
    URL_TO_IMGDIR,
    PATH_TO_LMO . '/vorlagen'
))->html($_GET, (int)($_SESSION['lmouserok'] ?? 0), (string)$_SERVER['PHP_SELF']);
