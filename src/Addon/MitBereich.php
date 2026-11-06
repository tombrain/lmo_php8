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

namespace Lmo\Addon;

use Lmo\Web\Kontext;

/**
 * Erweiterungspunkt: ein eigener Bereich eines Addons, der auch ohne Liga aufgerufen wird (etwa
 * das Tippspiel mit action=tipp). Ohne Liga traegt die Seite dann den Titel des Bereichs statt
 * des allgemeinen, und die Fusszeile fuehrt zurueck zur Ligenuebersicht (Konfiguration backlink).
 */
interface MitBereich extends Addon
{
    /** Der Titel des Bereichs, wenn die Anfrage zu ihm gehoert ($kontext->action), sonst null. */
    public function bereichstitel(Kontext $kontext): ?string;
}
