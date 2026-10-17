<?php

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
