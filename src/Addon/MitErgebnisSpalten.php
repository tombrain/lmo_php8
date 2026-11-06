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
 * Erweiterungspunkt: weitere Spalten der Ergebnisse eines Spieltags in der Administration
 * (todo=edit), etwa "ohne Tippwertung" des Tippspiels. Ein Addon liefert nur Spalten, wenn sie fuer
 * die Liga ($kontext->liga) und den Angemeldeten ($kontext->sitzung) gelten; sonst [].
 */
interface MitErgebnisSpalten extends Addon
{
    /** @return list<ErgebnisSpalte> */
    public function ergebnisSpalten(Kontext $kontext): array;
}
