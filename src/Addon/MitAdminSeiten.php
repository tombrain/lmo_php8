<?php

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
