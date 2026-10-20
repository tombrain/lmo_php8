<?php

declare(strict_types=1);

namespace Lmo\Addon\Spieler;

use Lmo\Addon\AlteAdminSeite;
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitAdminMenue;
use Lmo\Addon\MitAdminSeiten;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSeiten;
use Lmo\Web\Kontext;

/**
 * Addon "spieler": die Spielerstatistik einer Liga als eigene Seite (action=spieler) mit
 * Menuepunkt, wenn die Liga Spielerstatistiken hat (Option mittore). Den Menuepunkt gibt es
 * nur, wenn das Addon eingeschaltet ist (einspieler); gepflegt wird die Statistik im
 * Adminbereich beim Bearbeiten der Liga (st=-4, noch lmo-statadmin.php).
 */
final class SpielerAddon implements MitSeiten, MitRahmenteilen, MitAdminMenue, MitAdminSeiten
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

    /** Die Pflege der Statistik beim Bearbeiten einer Liga (st=-4), fuer Haupt- und Hilfsadmins. */
    public function adminController(): array
    {
        return [new AlteAdminSeite(
            static fn (Kontext $kontext): bool => $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -4 && $kontext->konfiguration->schalter('einspieler'),
            dirname($this->ordner) . '/lmo-statadmin.php'
        )];
    }

    public function adminMenue(Kontext $kontext): array
    {
        return [];
    }

    /** Der Punkt "Spielerstatistik" in der Navigation beim Bearbeiten einer Liga, wenn das Addon eingeschaltet ist. */
    public function ligaMenue(Kontext $kontext): array
    {
        if (!$kontext->konfiguration->schalter('einspieler')) {
            return [];
        }
        $t = $kontext->texteVon('spieler');

        return [new Menuepunkt('-4', $t[18] ?? '', $t[1] ?? '', $kontext->anfrage->text('st') == -4)];
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
