<?php

namespace Lmo\Addon;

use Lmo\Web\Kontext;

/** Erweiterungspunkt: weiteres HTML unter einer Seite (z. B. ein Link auf den Spielplan als PDF). */
interface MitZusatzUnterSeite extends Addon
{
    /** HTML unter der Seite $kontext->action, '' fuer nichts. */
    public function zusatzUnterSeite(Kontext $kontext): string;
}
