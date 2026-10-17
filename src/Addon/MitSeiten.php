<?php

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
