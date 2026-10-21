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
 * Erweiterungspunkt: Schalter in den Optionen der Administration (todo=options&show=2), etwa um
 * das Addon ein- und auszuschalten. Die Optionen zeigen sie in einem eigenen Block und speichern
 * sie in config/<addon>/cfg.txt.
 */
interface MitSchaltern extends Addon
{
    /** @return list<Schalter> */
    public function schalter(Kontext $kontext): array;
}
