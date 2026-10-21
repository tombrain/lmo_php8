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

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\Ligenliste;
use Lmo\Web\Vorlagen;

/**
 * Die Startseite der Administration (ohne todo): was es zu tun gibt und die Ligen zum Oeffnen;
 * Hilfsadmins sehen nur ihre Ligen und nur Oeffnen, Download und Abmelden. Addons tragen Punkte
 * bei (Lmo\Addon\MitAdminMenue::startseite()). Bis 4.5 lmo-adminpad.php mit lmo-dirlist.php.
 */
final class StartseiteController implements Controller
{
    /** @param Closure(string): bool $darfLesen Ligadatei => darf der Angemeldete sie sehen? (check_hilfsadmin) */
    public function __construct(private Closure $darfLesen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == '' && $kontext->sitzung->zahl('lmouserok') > 0;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $hauptadmin = $kontext->sitzung->zahl('lmouserok') == 2;

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_start.html.twig', [
            'text' => $kontext->text,
            'adda' => $kontext->adresse . '?action=admin&amp;todo=',
            'hauptadmin' => $hauptadmin,
            'ligen' => self::ligenliste($kontext, $this->darfLesen, ''),
            'addons' => $hauptadmin ? $kontext->addons->startseite($kontext) : [],
        ]));
    }

    /**
     * Die Ligen des gewaehlten Ordners zum Oeffnen (bis 4.5 lmo-dirlist.php); die Sortierung aus der
     * Adresse merkt sich die Sitzung, sonst gilt die gemerkte oder die aus der Konfiguration.
     *
     * @param Closure(string): bool $darfLesen
     * @param string $todo Wert fuer todo in den Links auf die Ligen
     */
    public static function ligenliste(Kontext $kontext, Closure $darfLesen, string $todo): string
    {
        $anfrage = $kontext->anfrage;
        $sitzung = $kontext->sitzung;
        foreach (['liga_sort', 'liga_sort_direction'] as $name) {
            $sitzung->setze($name, $anfrage->gesetzt($name) ? $anfrage->text($name) : $kontext->konfiguration->wert($name));
        }
        // Unterordner wie bisher aus der Adresse, sonst der Ordner der gewaehlten Datei
        $unterordner = $anfrage->gesetzt('subdir') ? $anfrage->text('subdir') : dirname($anfrage->text('file')) . '/';
        $unterordner = str_replace(['../', './'], '', $unterordner == '/' ? '' : $unterordner);
        $liste = new Ligenliste($kontext->text, $kontext->darstellung, $kontext->pfade->vorlagen, $kontext->pfade->bilder, $kontext->pfade->adresse, $darfLesen);

        return $liste->zeige(
            rtrim($kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner() . $unterordner, '/') . '/',
            $unterordner,
            $kontext->adresse,
            $kontext->adresse . '?todo=' . $todo . '&amp;file=',
            true,
            $sitzung->text('liga_sort'),
            $sitzung->text('liga_sort_direction'),
            $kontext->konfiguration->datumsformat()
        );
    }
}
