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

namespace Lmo\Web\Admin;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Nach einer neueren Version des LMO sehen (todo=update, nur Hauptadmin): ihre Angaben laut
 * Packagist und der Hinweis auf composer update, sonst "aktuell" mit einem Link zum erneuten
 * Pruefen. Bis 4.5 lmo-adminupdate.php.
 */
final class UpdateController implements Controller
{
    /**
     * @param array{version: string, time: string, php: string, changelog: string}|null $neueste die neueste Version laut Packagist (lmo_update_info()), null ohne Angabe
     * @param bool $neuer ist sie neuer als die installierte (lmo_is_newer())?
     * @param string $installiert die installierte Version
     * @param string $lizenz Lizenz laut composer.json
     */
    public function __construct(private ?array $neueste, private bool $neuer, private string $installiert, private string $lizenz)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'update' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_update.html.twig', [
            'text' => $kontext->text,
            'neuer' => $this->neuer && $this->neueste !== null,
            'neueste' => $this->neueste,
            'installiert' => $this->installiert,
            'lizenz' => $this->lizenz,
            'adresse' => $kontext->adresse . '?action=admin&amp;todo=update',
        ]));
    }
}
