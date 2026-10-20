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

namespace Lmo\Web;

/** Was ein Controller liefert: das HTML des Hauptteils und ob es dazu eine Druckansicht gibt. */
final class Seite
{
    /**
     * @param string $html Hauptteil der Seite
     * @param bool $druckbar Links zur Druckansicht (lmo-savehtml.php) unter der Seite zeigen
     */
    public function __construct(
        public readonly string $html,
        public readonly bool $druckbar = false,
    ) {
    }
}
