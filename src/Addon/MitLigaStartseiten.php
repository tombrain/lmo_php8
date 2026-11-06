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

use Lmo\Web\Kontext;

/**
 * Erweiterungspunkt: Seiten eines Addons als Startseite einer Liga (OnRun in der Ligadatei). Sie
 * stehen in den Grundeinstellungen der Liga zur Wahl, und ohne action zeigt der LMO die gewaehlte.
 * Ist fuer sie niemand zustaendig (etwa das Addon abgeschaltet), zeigt er die Tabelle, im Pokal
 * die Ergebnisse. Der Wert muss ueber 6 liegen (0 bis 6 sind die Seiten des Kerns) und darf sich
 * nicht aendern, denn er steht in den Ligadateien.
 */
interface MitLigaStartseiten extends Addon
{
    /** @return list<LigaStartseite> */
    public function ligaStartseiten(Kontext $kontext): array;
}
