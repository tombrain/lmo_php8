<?php

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
