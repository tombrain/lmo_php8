<?php

declare(strict_types=1);

namespace Lmo\Addon;

use Lmo\Web\Kontext;

/**
 * Erweiterungspunkt: Punkte in den Menues der Administration, im Hauptmenue (nur fuer den
 * Hauptadmin, hinter "Optionen", z. B. "Tippspiel") und in der Navigation beim Bearbeiten einer
 * Liga (hinter "Ergebnisse", z. B. "Spielerstatistik"). Die Seiten dazu liefert MitAdminSeiten.
 */
interface MitAdminMenue extends Addon
{
    /** @return list<Menuepunkt> Ziel: Wert fuer todo */
    public function adminMenue(Kontext $kontext): array;

    /** @return list<Menuepunkt> Ziel: Wert fuer st */
    public function ligaMenue(Kontext $kontext): array;
}
