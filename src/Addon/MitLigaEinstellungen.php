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
 * Erweiterungspunkt: Schalter in den Grundeinstellungen einer Liga (Administration, todo=edit&st=-1,
 * Reiter Anzeige), etwa ob die Liga eine Spielerstatistik fuehrt. Der Schluessel eines Schalters
 * (Lmo\Addon\Schalter) ist der in [Options] der Ligadatei (1 an, 0 aus); das Formularfeld heisst
 * x<Schluessel>. Es muss ein Schluessel sein, den der LMO kennt (Lmo\Liga\Einstellungen::schluessel(),
 * etwa mittore oder ticker). Ein Addon liefert nur Schalter, wenn es fuer die Liga gilt (etwa
 * eingeschaltet ist).
 */
interface MitLigaEinstellungen extends Addon
{
    /** @return list<Schalter> */
    public function ligaEinstellungen(Kontext $kontext): array;
}
