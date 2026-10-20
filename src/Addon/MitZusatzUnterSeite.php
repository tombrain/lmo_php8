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

/** Erweiterungspunkt: weiteres HTML unter einer Seite (z. B. ein Link auf den Spielplan als PDF). */
interface MitZusatzUnterSeite extends Addon
{
    /** HTML unter der Seite $kontext->action, '' fuer nichts. */
    public function zusatzUnterSeite(Kontext $kontext): string;
}
