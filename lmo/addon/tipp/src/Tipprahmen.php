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

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Web\Kontext;
use Lmo\Web\Vorlagen;

/**
 * Der Rahmen der Seiten eines angemeldeten Tippers: das Menue des Tippspiels und die Ueberschrift
 * ueber der Seite. Bis 4.5 lmo-tippmenu.php und lmo-tippmain.php.
 */
final class Tipprahmen
{
    /** @param Closure(string): string $regeln gibt die Tippregeln aus (Datei aus tipp/cfg.txt regelnlink) */
    public function __construct(private Closure $regeln)
    {
    }

    /** Die Seite mit Menue und Ueberschrift um ihren Inhalt. */
    public function html(Kontext $kontext, string $inhalt): string
    {
        $liga = $kontext->liga;

        return $this->menue($kontext) . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'ueberschrift.html.twig', [
            't' => $kontext->texteVon('tipp'),
            'liganame' => $liga !== null ? $liga->liga->einstellungen()->name() : '',
            'inhalt' => $inhalt,
        ]);
    }

    /**
     * Die Seite fuer nicht angemeldete Besucher (Einsicht, Tabelle, Auswertungen): Menue und
     * Ueberschrift wie bis 4.5 in lmo-tippauth.php, dort ohne Abschluss der Zeile.
     */
    public function htmlFuerGaeste(Kontext $kontext, string $inhalt): string
    {
        $liga = $kontext->liga;

        return $this->menue($kontext) . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'ueberschrift_gaeste.html.twig', [
            't' => $kontext->texteVon('tipp'),
            'liganame' => $liga !== null ? $liga->liga->einstellungen()->name() : '',
            'inhalt' => $inhalt,
        ]);
    }

    /**
     * Eine Seite, die auch Gaeste sehen (Einsicht, Tabelle, Auswertungen): fuer einen angemeldeten
     * Tipper im Rahmen html(), fuer Gaeste in htmlFuerGaeste(). Ist der Tipper angemeldet, aber
     * nicht freigeschaltet, bleibt der Rahmen leer (wie bis 4.5 lmo-tippmain.php).
     *
     * @param Closure(bool): string $inhalt der Inhalt; bekommt, ob der Tipper angemeldet ist
     */
    public function auchFuerGaeste(Kontext $kontext, Closure $inhalt): string
    {
        $ok = $kontext->sitzung->text('lmotipperok');
        if ($ok == -5) {
            // "Kennwort vergessen" ohne Nick (die Mail ging nicht raus): wieder nicht angemeldet
            $kontext->sitzung->setze('lmotipperok', 0);
            $ok = '0';
        }
        $angemeldet = $ok > 0;
        if ($angemeldet && $ok != 5) {
            // angemeldet, aber nicht freigeschaltet: Menue und Ueberschrift ohne Inhalt
            return $this->html($kontext, '');
        }

        return $angemeldet ? $this->html($kontext, $inhalt(true)) : $this->htmlFuerGaeste($kontext, $inhalt(false));
    }

    /** Das Menue des Tippspiels allein (die Anmeldeseite baut ihre Ueberschrift selbst). */
    public function menue(Kontext $kontext): string
    {
        $anfrage = $kontext->anfrage;
        $cfg = $kontext->konfiguration->addon('tipp');
        $liga = $kontext->liga;
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $todo = $anfrage->text('todo');
        $datei = $anfrage->text('file');
        // die Seiten einer Liga ohne geladene Liga (auch die Tippuebersicht, todo=edit ohne Datei)
        // zeigen die Startseite; wie bis 4.5 lmo-tippmain.php ohne todo und ohne Datei
        if ($liga === null && in_array($todo, ['edit', 'einsicht', 'tabelle', 'wert', 'fieber'], true) && !($todo == 'wert' && $anfrage->text('all') == 1)) {
            $todo = '';
            $datei = '';
        }
        $adda = $kontext->adresse . '?action=tipp&amp;todo=';
        $st = $anfrage->gesetzt('st') ? $anfrage->text('st') : ($liga !== null ? (string) $liga->liga->einstellungen()->aktuellerSpieltag() : '');
        $endtab = $anfrage->gesetzt('endtab') ? $anfrage->text('endtab') : '0';
        $alle = $anfrage->gesetzt('all') && $anfrage->text('all') == 'yes';

        // Menuepunkt: Link, ausser auf der gezeigten Seite
        $punkt = static fn (bool $aktiv, string $href, string $titel, string $name): array => ['link' => $aktiv ? null : $href, 'titel' => $titel, 'name' => $name];
        $punkte = [];
        if ($anfrage->gesetzt('viewermode')) {
            $punkte[] = $punkt(true, '', '', $t[9] ?? '');
        } elseif ($datei != '') {
            if (($cfg['sttipp'] ?? '') != -1) {
                $punkte[] = $punkt($todo == 'edit', $adda . 'edit&amp;file=' . $datei . '&amp;st=' . $st, $t[9] ?? '', $t[9] ?? '');
            }
            if (($cfg['tippeinsicht'] ?? '') == 1) {
                $punkte[] = $punkt($todo == 'einsicht', $adda . 'einsicht&amp;file=' . $datei . '&amp;st=' . $st, $t[157] ?? '', $t[157] ?? '');
            }
            if (($liga === null || !$liga->liga->einstellungen()->istPokal()) && ($cfg['tipptabelle1'] ?? '') == 1) {
                $punkte[] = $punkt($todo == 'tabelle', $adda . 'tabelle&amp;file=' . $datei, $t[173] ?? '', $t[172] ?? '');
            }
            if (($cfg['tippfieber'] ?? '') == 1) {
                $punkte[] = $punkt($todo == 'fieber', $adda . 'fieber&amp;file=' . $datei, $text[134] ?? '', $text[133] ?? '');
            }
            $punkte[] = $punkt($todo == 'wert' && !$alle, $adda . 'wert&amp;file=' . $datei . '&amp;endtab=' . $endtab . '&amp;wertung=einzel', $t[54] ?? '', $t[54] ?? '');
        }

        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'menue.html.twig', [
            'text' => $text,
            't' => $t,
            'uebersicht' => $todo != '' ? $kontext->adresse . '?action=tipp' : null,
            'punkte' => $punkte,
            'regeln' => ($cfg['regeln'] ?? '') == 1 ? ($this->regeln)($cfg['regelnlink'] ?? '') : null,
            'abmelden' => $adda . 'logout',
        ]);
    }

    /** Eine Meldung wie getMessage() in lmo-functions.php: Fehler mit rotem, sonst mit gruenem Zeichen. */
    public static function meldung(Kontext $kontext, string $text, bool $fehler = false): string
    {
        return '<p class="' . ($fehler ? 'error' : 'message') . '"><img src="' . $kontext->pfade->bilder . '/' . ($fehler ? 'wrong' : 'right') . '.svg" border="0" width="20" alt=""> ' . $text . '</p>';
    }
}
