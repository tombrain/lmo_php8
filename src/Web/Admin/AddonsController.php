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
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Einstellungen der Addons (todo=addons, nur der Hauptadmin): links die Addons mit eigener
 * cfg.txt, rechts die Werte des gewaehlten (show, Nummer in der Liste ab 0) als Textfelder. Mit
 * save=1 werden sie gespeichert. Bis 4.5 lmo-adminaddon.php.
 */
final class AddonsController implements Controller
{
    /** @param string $konfigurationsordner Ordner der cfg.txt (PATH_TO_CONFIGDIR) */
    public function __construct(private string $konfigurationsordner)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'addons' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $konfiguration = $kontext->konfiguration;
        $meldung = '';
        if ($anfrage->zahl('save') == 1) {
            // Es werden alle Addon-Konfigurationen dargestellt als Texteingabe behandelt
            // und anschliessend abgespeichert - Es erfolgen keine Prüfungen auf Variablentyp und -wert.
            // Die Felder heissen nur x<Schluessel>: wie bisher gilt ein Feld fuer jedes Addon mit diesem Schluessel.
            $aenderungen = [];
            foreach ($konfiguration->alle() as $addon => $werte) {
                if (is_array($werte)) {
                    foreach (array_keys($werte) as $schluessel) {
                        if ($anfrage->gesetzt('x' . $schluessel)) {
                            $aenderungen[$addon][(string) $schluessel] = trim($anfrage->text('x' . $schluessel));
                        }
                    }
                }
            }
            $konfiguration = $konfiguration->mit($aenderungen);
            $meldung = $konfiguration->speichere($this->konfigurationsordner)
                ? Meldung::html($kontext->pfade->bilder, $text[138] ?? '')
                : Meldung::html($kontext->pfade->bilder, $text[283] ?? '', true);
        }

        $addons = [];
        foreach ($konfiguration->alle() as $addon => $werte) {
            if (is_array($werte)) {
                $addons[] = ['name' => $addon, 'werte' => $konfiguration->addon($addon)];
            }
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_addons.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'warnung' => Meldung::html($kontext->pfade->bilder, $text[571] ?? '', true),
            'addons' => $addons,
            'show' => $anfrage->zahl('show'),
            'datei' => $kontext->liga->datei ?? '',
        ]));
    }
}
