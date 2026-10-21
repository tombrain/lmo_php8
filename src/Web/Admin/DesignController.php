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
 * Das Design (todo=design, nur der Hauptadmin): Farben, Rahmen und Schriften des Stylesheets
 * (lmo-style.php) auf sieben Seiten (show). Mit save=1 werden die Felder der gezeigten Seite
 * gespeichert. Bis 4.5 lmo-admindesign.php.
 */
final class DesignController implements Controller
{
    /** Die Textfelder je Seite (Feld x<Name>) */
    private const FELDER = [
        0 => ['lmo_main_background1', 'lmo_main_background2', 'lmo_main_background4', 'lmo_main_background5', 'lmo_main_background6', 'lmo_main_background7',
            'lmo_main_color1', 'lmo_main_color2', 'lmo_main_color4', 'lmo_main_color5', 'lmo_main_color6', 'lmo_main_color7',
            'lmo_main_border1', 'lmo_main_border4', 'lmo_main_border5', 'lmo_main_border6', 'lmo_main_border7',
            'lmo_main_fontfamily1', 'lmo_main_fontfamily2', 'lmo_main_fontsize1', 'lmo_main_fontsize2'],
        1 => ['lmo_middle_background1', 'lmo_middle_background2', 'lmo_middle_background4', 'lmo_middle_background5', 'lmo_middle_background6', 'lmo_middle_background7',
            'lmo_middle_color1', 'lmo_middle_color2', 'lmo_middle_color4', 'lmo_middle_color5', 'lmo_middle_color6', 'lmo_middle_color7',
            'lmo_middle_border1', 'lmo_middle_border4', 'lmo_middle_border5', 'lmo_middle_border6', 'lmo_middle_border7',
            'lmo_middle_fontfamily1', 'lmo_middle_fontfamily2', 'lmo_middle_fontsize1', 'lmo_middle_fontsize2'],
        2 => ['lmo_inner_background1', 'lmo_inner_background2', 'lmo_inner_background3', 'lmo_inner_color1', 'lmo_inner_color2', 'lmo_inner_color3',
            'lmo_inner_border1', 'lmo_inner_fontfamily1', 'lmo_inner_fontsize1'],
        3 => ['lmo_tabelle_background1', 'lmo_tabelle_background2', 'lmo_tabelle_background3', 'lmo_tabelle_background4',
            'lmo_tabelle_background5', 'lmo_tabelle_background6', 'lmo_tabelle_background7', 'lmo_tabelle_background8',
            'lmo_tabelle_color1', 'lmo_tabelle_color2', 'lmo_tabelle_color3', 'lmo_tabelle_color4',
            'lmo_tabelle_color5', 'lmo_tabelle_color6', 'lmo_tabelle_color7', 'lmo_tabelle_color8'],
        4 => ['lmo_turnier_background1', 'lmo_turnier_background2', 'lmo_turnier_color1', 'lmo_turnier_color2'],
        5 => ['lmo_kreuz_fontsize1', 'lmo_kreuzkal_background2', 'lmo_fieber_color1', 'lmo_fieber_color2',
            'lmo_footer_fontsize1', 'lmo_kreuzkal_background1', 'lmo_kreuzkal_color1'],
        6 => ['lmo_formular_background1', 'lmo_formular_background2', 'lmo_formular_color1', 'lmo_formular_color2', 'lmo_formular_border1', 'lmo_formular_border2'],
    ];

    /** Die Schalter fuer unterstrichene Links auf Seite 5 (Wert "underline" oder leer) */
    private const UNTERSTRICHEN = ['lmo_main_underline1', 'lmo_middle_underline1', 'lmo_inner_underline1'];

    /** @param string $konfigurationsordner Ordner der cfg.txt (PATH_TO_CONFIGDIR) */
    public function __construct(private string $konfigurationsordner)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'design' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $show = $anfrage->zahl('show');
        $konfiguration = $kontext->konfiguration;
        $meldung = '';
        if ($anfrage->zahl('save') == 1) {
            $aenderungen = [];
            foreach (self::FELDER[$show] ?? [] as $schluessel) {
                if ($anfrage->gesetzt('x' . $schluessel)) {
                    $aenderungen[$schluessel] = $anfrage->text('x' . $schluessel);
                }
            }
            if ($show == 5) {
                foreach (self::UNTERSTRICHEN as $schluessel) {
                    $aenderungen[$schluessel] = $anfrage->gesetzt('x' . $schluessel) ? 'underline' : '';
                }
            }
            // gespeichert werden nur Werte, die die Konfiguration schon hat (wie lmo-savecfg.php)
            $konfiguration = $konfiguration->mit(array_intersect_key($aenderungen, $konfiguration->alle()));
            $meldung = $konfiguration->speichere($this->konfigurationsordner)
                ? Meldung::html($kontext->pfade->bilder, $text[138] ?? '')
                : Meldung::html($kontext->pfade->bilder, $text[283] ?? '', true);
        }

        $werte = [];
        foreach (array_merge(array_merge(...self::FELDER), self::UNTERSTRICHEN) as $schluessel) {
            $werte[$schluessel] = $konfiguration->wert($schluessel);
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_design.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'js' => $kontext->pfade->adresse . '/js',
            'show' => $show,
            'k' => $werte,
        ]));
    }
}
