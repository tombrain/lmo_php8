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

namespace Lmo\Web\Admin;

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Kontext;

/**
 * Speichern nach einer Aenderung am Spielplan (Rueckrunde, Partien verschoben): zuerst die
 * Auswertungen der Addons ueber alle Spieltage, wie bis 4.5 updateAddons() der Classlib nach
 * writeFile(), dann speichern mit dem bisherigen aktuellen Spieltag.
 */
final class SpielplanSpeichern
{
    /**
     * @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga (LigaSpeichern) (Rohdaten, Datei, Spieltag)
     * @return array{string, Liga} Ausgaben und die gespeicherte Liga
     */
    public static function nach(Kontext $kontext, Liga $liga, string $datei, Closure $speichere): array
    {
        // Spieltag 0: alle Spieltage
        $ausgabe = $kontext->addons->werteBeimSpeichern($kontext, $liga, 0);
        [$gespeichert, $liga] = $speichere($liga, $datei, '0');

        return [$ausgabe . $gespeichert, $liga];
    }
}
