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

namespace Lmo\Addon\Spieler;

use Lmo\Addon\AlteAdminSeite;
use Lmo\Addon\Menuepunkt;
use Lmo\Addon\MitAdminMenue;
use Lmo\Addon\MitAdminSeiten;
use Lmo\Addon\MitRahmenteilen;
use Lmo\Addon\MitSchaltern;
use Lmo\Addon\MitSeiten;
use Lmo\Addon\Schalter;
use Lmo\Web\Konfiguration;
use Lmo\Web\Kontext;

/**
 * Addon "spieler": die Spielerstatistik einer Liga als eigene Seite (action=spieler) mit
 * Menuepunkt, wenn die Liga Spielerstatistiken hat (Option mittore). Den Menuepunkt gibt es
 * nur, wenn das Addon eingeschaltet ist (einspieler); gepflegt wird die Statistik im
 * Adminbereich beim Bearbeiten der Liga (st=-4, noch lmo-statadmin.php).
 */
final class SpielerAddon implements MitSeiten, MitRahmenteilen, MitAdminMenue, MitAdminSeiten, MitSchaltern
{
    /** Der Schalter in den Optionen: Spielerstatistik an (config/spieler/cfg.txt, bis 4.5 config/cfg.txt) */
    public const SCHALTER = 'einspieler';

    /** @param string $ordner Ordner der Statistiken (addon/spieler/stats) */
    public function __construct(private string $ordner)
    {
    }

    public function name(): string
    {
        return 'spieler';
    }

    /** Ist das Addon eingeschaltet (SCHALTER, ohne Konfiguration an)? */
    public static function an(Konfiguration $konfiguration): bool
    {
        return $konfiguration->addonSchalter('spieler', self::SCHALTER, true);
    }

    /** Der Schalter "Spielerstatistik" in den Optionen */
    public function schalter(Kontext $kontext): array
    {
        $t = $kontext->texteVon('spieler');

        return [new Schalter(self::SCHALTER, $t[18] ?? '', $t[60] ?? '', true)];
    }

    public function controller(): array
    {
        return [new StatistikController($this->ordner)];
    }

    /** Die Pflege der Statistik beim Bearbeiten einer Liga (st=-4), fuer Haupt- und Hilfsadmins. */
    public function adminController(): array
    {
        return [new AlteAdminSeite(
            static fn (Kontext $kontext): bool => $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -4 && self::an($kontext->konfiguration),
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
        if (!self::an($kontext->konfiguration)) {
            return [];
        }
        $t = $kontext->texteVon('spieler');

        return [new Menuepunkt('-4', $t[18] ?? '', $t[1] ?? '', $kontext->anfrage->text('st') == -4)];
    }

    public function startseite(Kontext $kontext): array
    {
        return [];
    }

    public function rahmenteile(Kontext $kontext): array
    {
        $liga = $kontext->liga;
        if ($liga === null || !self::an($kontext->konfiguration) || !$liga->liga->einstellungen()->mitToren()) {
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
