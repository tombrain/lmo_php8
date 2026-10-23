<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Web;

/**
 * Eine Meldung ueber der Seite, wie getMessage() in lmo-functions.php: ein Fehler mit rotem, sonst
 * mit gruenem Zeichen. Der Text ist fertiges HTML.
 */
final class Meldung
{
    /** @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR) */
    public static function html(string $bilder, string $text, bool $fehler = false): string
    {
        return $fehler
            ? '<p class="error"><img src="' . $bilder . '/wrong.svg" border="0" width="20" alt=""> ' . $text . '</p>'
            : '<p class="message"><img src="' . $bilder . '/right.svg" border="0" width="20" alt=""> ' . $text . '</p>';
    }
}
