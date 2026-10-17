<?php

declare(strict_types=1);

namespace Lmo\Web\Controller;

use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Info-Seite (action=info, in Liga und Pokal): Version des LMO und die Hinweise aus den
 * Sprachtexten. Vorlage info.html.twig.
 */
final class InfoController implements Controller
{
    /** @param string $version installierte Version des LMO (LMO_VERSION_NR) */
    public function __construct(private string $version)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && $kontext->action == 'info';
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'info.html.twig', [
            'text' => $kontext->text,
            'version' => $this->version,
        ]));
    }
}
