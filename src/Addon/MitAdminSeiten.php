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

/**
 * Erweiterungspunkt: eigene Seiten in der Administration (z. B. todo=tippoptions). Die
 * Administration fragt diese Controller vor ihren eigenen; ein Controller prueft selbst, ob er
 * zustaendig ist (todo, st, Rechte in der Sitzung: lmouserok 2 Hauptadmin, 1 Hilfsadmin).
 */
interface MitAdminSeiten extends Addon
{
    /** @return list<Controller> */
    public function adminController(): array;
}
