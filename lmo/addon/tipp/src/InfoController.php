<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Info-Seite im Tippspiel (todo=info): dieselbe wie action=info (Vorlage info.html.twig des
 * LMO) im Rahmen des Tippspiels, auch ohne Liga und fuer Gaeste. Bis 4.5 lmo-showinfo.php aus
 * lmo-tippmain.php bzw. lmo-tippauth.php.
 */
final class InfoController implements Controller
{
    /** @param string $version installierte Version des LMO (LMO_VERSION_NR) */
    public function __construct(private Tipprahmen $rahmen, private string $version)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'info'
            && !$kontext->anfrage->gesetzt('viewermode');
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite($this->rahmen->auchFuerGaeste($kontext, fn (bool $angemeldet): string => Vorlagen::zeige($kontext->pfade->vorlagen, 'info.html.twig', [
            'text' => $kontext->text,
            'version' => $this->version,
        ])));
    }
}
