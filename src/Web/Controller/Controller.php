<?php

declare(strict_types=1);

namespace Lmo\Web\Controller;

use Lmo\Web\Kontext;
use Lmo\Web\Seite;

/** Ein Controller zeigt eine oder mehrere Seiten des LMO (je nach action). */
interface Controller
{
    /** Zeigt dieser Controller die gewaehlte Seite? Sonst fragt die Anwendung den naechsten. */
    public function zustaendig(Kontext $kontext): bool;

    /** Die Seite; nur aufgerufen, wenn zustaendig() ja gesagt hat. */
    public function zeige(Kontext $kontext): Seite;
}
