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

use Lmo\Liga\Rohdaten\Liga;

/** Eine geladene Liga mit ihrer Datei und dem Stand (Zeit der letzten Aenderung der Datei). */
final class GeladeneLiga
{
    /**
     * @param string $datei Ligadatei relativ zum Ligenordner
     * @param string $stand Zeit der letzten Aenderung, im Datumsformat der Installation
     */
    public function __construct(
        public readonly string $datei,
        public readonly Liga $liga,
        public readonly string $stand,
    ) {
    }
}
