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

namespace Lmo\Addon\Viewer;

use Lmo\Addon\AlteAdminSeite;
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitAdminMenue;
use Lmo\Addon\MitAdminSeiten;
use Lmo\Web\Kontext;

/**
 * Addon "viewer": die Spiele mehrerer Ligen auf einer Seite zum Einbinden (viewer.php), mit den
 * Einstellungen in der Administration (todo=vieweroptions, noch lmo-adminvieweroptions.php) und
 * ihrem Punkt im Hauptmenue, beides nur fuer den Hauptadmin.
 */
final class ViewerAddon implements MitAdminMenue, MitAdminSeiten
{
    /** @param string $ordner Ordner des Addons (addon/viewer) */
    public function __construct(private string $ordner)
    {
    }

    public function name(): string
    {
        return 'viewer';
    }

    public function adminController(): array
    {
        return [new AlteAdminSeite(
            static fn (Kontext $kontext): bool => $kontext->anfrage->text('todo') == 'vieweroptions' && $kontext->sitzung->zahl('lmouserok') == 2,
            $this->ordner . '/lmo-adminvieweroptions.php'
        )];
    }

    /** Der Punkt "Viewer" im Hauptmenue der Administration. */
    public function adminMenue(Kontext $kontext): array
    {
        $t = $kontext->texteVon('viewer');

        return [new Menuepunkt('vieweroptions', $t[20] ?? '', $t[21] ?? '', $kontext->anfrage->text('todo') == 'vieweroptions')];
    }

    public function ligaMenue(Kontext $kontext): array
    {
        return [];
    }

    public function startseite(Kontext $kontext): array
    {
        return [];
    }
}
