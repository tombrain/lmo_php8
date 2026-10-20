<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;

/**
 * Addon "tipp": das Tippspiel (action=tipp) mit Menuepunkt, wenn es eingeschaltet ist (eintippspiel).
 *
 * Die Seiten zeigt vorerst noch der alte Code (lmo-tippstart.php und was er einbindet); addon.php
 * gibt ihn als Closure mit. Bis 4.5 band lmo-showmain2.php ihn direkt ein.
 */
final class TippAddon implements MitSeiten, MitRahmenteilen
{
    /** @param Closure(): void $alterCode gibt die Seite des Tippspiels aus (lmo-tippstart.php) */
    public function __construct(private Closure $alterCode)
    {
    }

    public function name(): string
    {
        return 'tipp';
    }

    public function controller(): array
    {
        $alterCode = $this->alterCode;

        return [new class ($alterCode) implements Controller {
            /** @param Closure(): void $alterCode */
            public function __construct(private Closure $alterCode)
            {
            }

            public function zustaendig(Kontext $kontext): bool
            {
                return $kontext->action == 'tipp' && $kontext->konfiguration->schalter('eintippspiel');
            }

            public function zeige(Kontext $kontext): Seite
            {
                ob_start();
                ($this->alterCode)();

                return new Seite((string) ob_get_clean());
            }
        }];
    }

    /**
     * Der Menuepunkt "Tippspiel", wenn die Liga getippt werden kann (alle Ligen: immeralle, sonst
     * die in ligenzutippen) oder keine Liga geladen ist.
     */
    public function rahmenteile(Kontext $kontext): array
    {
        if (!$kontext->konfiguration->schalter('eintippspiel')) {
            return [];
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $datei = $kontext->liga !== null ? $kontext->liga->datei : '';
        if (($cfg['immeralle'] ?? '') != 1 && !str_contains($cfg['ligenzutippen'] ?? '', substr(basename($datei), 0, -4))) {
            return [];
        }
        $titel = $kontext->texteVon('tipp')[0] ?? '';

        return ['Tippspiel' => $kontext->action != 'tipp'
            ? '<a href="' . $kontext->adresse . '?file=' . $datei . '&amp;action=tipp" title="' . $titel . '">' . $titel . '</a>'
            : $titel];
    }
}
