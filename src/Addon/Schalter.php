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
 * Ein Schalter, den ein Addon zu den Optionen der Administration beitraegt (MitSchaltern), etwa
 * "Tippspiel an". Der Wert steht in der Konfiguration des Addons (config/<addon>/cfg.txt) unter
 * dem Schluessel, 1 an, 0 aus (Konfiguration::addonSchalter()).
 */
final class Schalter
{
    /**
     * @param string $schluessel Schluessel in config/<addon>/cfg.txt; bis 4.5 stand er in config/cfg.txt
     * @param string $name Beschriftung (HTML)
     * @param string $titel Tooltip (HTML)
     * @param bool $vorgabe Wert, solange keine Konfiguration ihn nennt
     */
    public function __construct(
        public readonly string $schluessel,
        public readonly string $name,
        public readonly string $titel,
        public readonly bool $vorgabe,
    ) {
    }
}
