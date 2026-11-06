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

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Kontext;

/**
 * Erweiterungspunkt: Auswertung beim Speichern der Ergebnisse in der Administration (todo=edit, auch
 * nach einer Aenderung am Spielplan), etwa die Tipps des Tippspiels. Sie laeuft, nachdem die
 * Ergebnisse in der Liga stehen und bevor die Liga gespeichert wird. Bis 4.5 band lmo-adminedit.php
 * dafuer Dateien des Addons im globalen Bereich ein.
 */
interface MitErgebnisAuswertung extends Addon
{
    /**
     * Wertet aus. $kontext->liga ist die bearbeitete Liga (ihre Datei), $liga ihr neuer Stand.
     *
     * @param int $st der gespeicherte Spieltag, 0 fuer alle
     * @return string Meldungen (HTML), '' ohne
     */
    public function werteBeimSpeichern(Kontext $kontext, Liga $liga, int $st): string;
}
