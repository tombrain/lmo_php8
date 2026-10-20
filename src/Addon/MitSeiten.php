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

use Lmo\Web\Controller\Controller;

/** Erweiterungspunkt: eigene Seiten im oeffentlichen Teil (z. B. action=spieler). */
interface MitSeiten extends Addon
{
    /**
     * Die Controller des Addons; die Anwendung fragt sie nach ihren eigenen.
     *
     * @return list<Controller>
     */
    public function controller(): array;
}
