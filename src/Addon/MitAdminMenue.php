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
 * Erweiterungspunkt: Punkte in den Menues der Administration, im Hauptmenue (nur fuer den
 * Hauptadmin, hinter "Optionen", z. B. "Tippspiel"), auf der Startseite (nur fuer den Hauptadmin,
 * hinter "Optionen") und in der Navigation beim Bearbeiten einer Liga (hinter "Ergebnisse", z. B.
 * "Spielerstatistik"). Die Seiten dazu liefert MitAdminSeiten.
 */
interface MitAdminMenue extends Addon
{
    /** @return list<Menuepunkt> Ziel: Wert fuer todo */
    public function adminMenue(Kontext $kontext): array;

    /** @return list<Menuepunkt> Ziel: Wert fuer st */
    public function ligaMenue(Kontext $kontext): array;

    /** @return list<Menuepunkt> Ziel: Wert fuer todo; ohne Tooltip */
    public function startseite(Kontext $kontext): array;
}
