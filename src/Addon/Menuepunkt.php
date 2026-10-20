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
 * Ein Punkt, den ein Addon zu einem Menue der Administration beitraegt (MitAdminMenue): im
 * Hauptmenue der Wert fuer todo, in der Navigation einer Liga der fuer st. Auf der gezeigten Seite
 * (aktiv) steht der Name ohne Link.
 */
final class Menuepunkt
{
    /**
     * @param string $ziel Wert fuer todo (Hauptmenue) bzw. st (Navigation einer Liga)
     * @param string $name Text des Punkts (HTML)
     * @param string $titel Tooltip des Links (HTML)
     * @param bool $aktiv die Seite des Punkts wird gerade gezeigt
     */
    public function __construct(
        public readonly string $ziel,
        public readonly string $name,
        public readonly string $titel,
        public readonly bool $aktiv,
    ) {
    }
}
