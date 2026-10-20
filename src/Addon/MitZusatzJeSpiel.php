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

use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;

/** Erweiterungspunkt: weiteres HTML am Ende jeder Ergebniszeile (z. B. bisherige Ergebnisse der beiden Mannschaften). */
interface MitZusatzJeSpiel extends Addon
{
    /** HTML fuer das Spiel, '' fuer nichts. */
    public function zusatzJeSpiel(Spiel $spiel, Kontext $kontext): string;
}
