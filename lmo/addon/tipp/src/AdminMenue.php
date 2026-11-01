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

namespace Lmo\Addon\Tipp;

use Lmo\Web\Kontext;
use Lmo\Web\Vorlagen;

/**
 * Das Menue ueber den Seiten der Tippspiel-Administration: Auswertung, Newsletter/Reminder,
 * Userverwaltung, Optionen; die gezeigte Seite ohne Link. Bis 4.5 lmo-admintippmenu.php.
 */
final class AdminMenue
{
    /** @param string $todo die gezeigte Seite (tipp, tippemail, tippuser, tippuseredit, tippoptions) */
    public static function html(Kontext $kontext, string $todo): string
    {
        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_menue.html.twig', [
            'text' => $kontext->text,
            't' => $kontext->texteVon('tipp'),
            'adresse' => $kontext->adresse . '?action=admin&amp;todo=',
            'todo' => $todo,
            'hauptadmin' => $kontext->sitzung->zahl('lmouserok') == 2,
        ]);
    }
}
