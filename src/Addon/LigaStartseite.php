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

/**
 * Eine Seite eines Addons, die als Startseite einer Liga gewaehlt werden kann (Lmo\Addon\MitLigaStartseiten).
 */
final class LigaStartseite
{
    /**
     * @param int $wert der Wert in OnRun der Ligadatei; 0 bis 6 sind die Seiten des Kerns
     * @param string $action die Seite (Parameter action), etwa spieler
     * @param string $name Name in der Auswahl der Grundeinstellungen
     * @param string $schalter Schluessel in [Options]; steht er auf 0, ist die Seite in der Auswahl gesperrt ('' fuer keinen)
     */
    public function __construct(
        public readonly int $wert,
        public readonly string $action,
        public readonly string $name,
        public readonly string $schalter = ''
    ) {
    }
}
