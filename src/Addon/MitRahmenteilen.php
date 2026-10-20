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
 * Erweiterungspunkt: Teile fuer den Rahmen der Seiten (Lmo\Web\Rahmen::TEILE), z. B. ein Menuepunkt
 * ("Spielerstatistik", "Tippspiel") oder ein Bereich ueber dem Menue ("Newsticker"). Liefern mehrere
 * Addons denselben Teil, stehen sie hintereinander.
 */
interface MitRahmenteilen extends Addon
{
    /** @return array<string, string> Teil des Rahmens => fertiges HTML */
    public function rahmenteile(Kontext $kontext): array;
}
