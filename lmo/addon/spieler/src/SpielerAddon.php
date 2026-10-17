<?php

declare(strict_types=1);

namespace Lmo\Addon\Spieler;

use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Web\Kontext;

/**
 * Addon "spieler": die Spielerstatistik einer Liga als eigene Seite (action=spieler) mit
 * Menuepunkt, wenn die Liga Spielerstatistiken hat (Option mittore). Den Menuepunkt gibt es
 * nur, wenn das Addon eingeschaltet ist (einspieler); gepflegt wird die Statistik im
 * Adminbereich (lmo-statadmin.php).
 */
final class SpielerAddon implements MitSeiten, MitRahmenteilen
{
    /** @param string $ordner Ordner der Statistiken (addon/spieler/stats) */
    public function __construct(private string $ordner)
    {
    }

    public function name(): string
    {
        return 'spieler';
    }

    public function controller(): array
    {
        return [new StatistikController($this->ordner)];
    }

    public function rahmenteile(Kontext $kontext): array
    {
        $liga = $kontext->liga;
        if ($liga === null || !$kontext->konfiguration->schalter('einspieler') || !$liga->liga->einstellungen()->mitToren()) {
            return [];
        }
        $t = $kontext->texteVon('spieler');
        $ligalink = Einstellungen::lies(Einstellungen::datei($this->ordner, $liga->datei), $kontext->konfiguration->addon('spieler'), $t)->ligalink;
        $menue = $kontext->action != 'spieler'
            ? '<a href="' . $kontext->adresse . '?file=' . $liga->datei . '&amp;action=spieler" title="' . ($t[12] ?? '') . '">' . $ligalink . '</a>'
            : $ligalink;

        return ['Spielerstatistik' => $menue . '&nbsp;&nbsp;'];
    }
}
