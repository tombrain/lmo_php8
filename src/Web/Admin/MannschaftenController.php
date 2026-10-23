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
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Mannschaft;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Mannschaften einer Liga (todo=edit&st=-2, Hauptadmin und erweiterte Hilfsadmins): Namen,
 * Strafen und Korrekturen (nur Liga), Notiz und Homepage. save=1 speichert das Formular,
 * team=<Nummer> entfernt eine Mannschaft (eine Liga behaelt mindestens vier), team=-1 fuegt eine
 * hinzu (hoechstens 40). Bis 4.5 lmo-adminteams.php.
 */
final class MannschaftenController implements Controller
{
    /** @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga wie lmo-saveliga.php (Rohdaten, Datei, gewaehlter Spieltag) */
    public function __construct(private Closure $speichere)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        $sitzung = $kontext->sitzung;

        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -2
            && ($sitzung->zahl('lmouserok') == 2 || ($sitzung->zahl('lmouserok') == 1 && $sitzung->zahl('lmouserokerweitert') == 1));
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga (fehlt oder fuer diesen Hilfsadmin nicht freigegeben) bleibt die Seite leer
        if ($geladen === null) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $liga = $geladen->liga;
        $pokal = $liga->einstellungen()->istPokal();
        $anzahl = $liga->einstellungen()->mannschaften();
        $team = $anfrage->text('team');
        $speichern = false;
        if ($anfrage->text('save') == 1) {
            for ($i = 1; $i <= $anzahl; $i++) {
                self::uebernimm($liga, $liga->mannschaft($i), $i, $anfrage, $pokal);
            }
            $speichern = true;
        } elseif ($team != '') {
            if ($team >= 1 && $team <= $anzahl) {
                // eine Liga behaelt mindestens vier Mannschaften
                if ($anzahl > 4) {
                    $liga->entferneMannschaft((int) $team);
                    $speichern = true;
                }
            } elseif ($team == -1 && $anzahl < 40) {
                $liga->neueMannschaft('Neue Mannschaft');
                $speichern = true;
            }
        }
        $meldung = '';
        if ($speichern) {
            [$meldung, $liga] = ($this->speichere)($liga, $geladen->datei, $anfrage->text('st'));
        }

        $einstellungen = $liga->einstellungen();
        $mannschaften = [];
        for ($i = 1; $i <= $einstellungen->mannschaften(); $i++) {
            $m = $liga->mannschaften[$i] ?? new Mannschaft();
            $name = $m->anzeigename();
            $mannschaften[] = [
                'nr' => $i,
                'name' => $name,
                'mittel' => $m->mittelname(),
                'kurz' => $m->kurzname(),
                'strafpunkte' => (-1) * (int) ($m->werte['SP'] ?? 0),
                'strafminus' => (-1) * (int) ($m->werte['SM'] ?? 0),
                'tore' => (-1) * (int) ($m->werte['TOR1'] ?? 0),
                'gegentore' => (-1) * (int) ($m->werte['TOR2'] ?? 0),
                'abSpieltag' => $m->werte['STDA'] ?? '0',
                // Notiz und Homepage wie bisher mit htmlentities()
                'notiz' => htmlentities($m->werte['NOT'] ?? ''),
                'notizLeer' => trim($m->werte['NOT'] ?? '') == '',
                'url' => htmlentities($m->werte['URL'] ?? ''),
                'urlLeer' => trim($m->werte['URL'] ?? '') == '',
                'wappen' => $kontext->darstellung->wappen($geladen->datei, $name, " alt=''"),
                'wappenGross' => $kontext->darstellung->wappenGross($geladen->datei, $name, " alt=''"),
            ];
        }
        $addr = LigaNavigation::adresse($kontext, 'edit', $geladen->datei);
        $k = $kontext->konfiguration;

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_mannschaften.html.twig', [
            'text' => $kontext->text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'navigation' => LigaNavigation::fuer($kontext, $liga, 'edit', $anfrage->text('st')),
            'name' => $einstellungen->name(),
            'datei' => $geladen->datei,
            'st' => $anfrage->text('st'),
            'pokal' => $pokal,
            'minuspunkte' => $einstellungen->mitMinuspunkten(),
            'breite' => $pokal ? 2 : 4,
            'mannschaften' => $mannschaften,
            'addr' => $addr,
            'addz' => $addr . '-2&amp;team=',
            // der Link zur Anzahl der Spieltage nach dem Hinzufuegen oder Entfernen einer Mannschaft;
            // wie bis 4.5 auch nach jedem Speichern mit Torstatistik: lmo-zustat.php (aus lmo-saveliga.php)
            // hinterliess die globale Variable $team, die die Seite dafuer pruefte
            'anzahlLink' => $team != '' || ($speichern && ($k->schalter('einzutore') || $k->schalter('einzutoretab') || $k->schalter('einzustats'))),
        ]));
    }

    /** Die Felder des Formulars fuer eine Mannschaft. */
    private static function uebernimm(Liga $liga, Mannschaft $m, int $i, Anfrage $anfrage, bool $pokal): void
    {
        // ein leer gelassener Name bleibt der bisherige
        if ($anfrage->text('xteams' . $i) != '') {
            $m->name = trim($anfrage->text('xteams' . $i));
        }
        $mittel = $anfrage->text('xteamm' . $i);
        if ($mittel == '') {
            // search longest name part an take this as middle name
            foreach (preg_split('%[ |/]%', $m->anzeigename()) ?: [] as $teil) {
                if (strlen($teil) >= strlen($mittel)) {
                    $mittel = trim(substr($teil, 0, 12));
                }
            }
        }
        $m->mittel = $mittel;
        $m->kurz = trim($anfrage->text('xteamk' . $i));
        if ($m->kurz == '') {
            $m->kurz = trim(substr($mittel, 0, 5));
        }
        $m->setzeUrl(trim($anfrage->text('xteamu' . $i)));
        $m->setzeNotiz(trim($anfrage->text('xteamn' . $i)));
        if (!$pokal) {
            // im Formular zaehlt ein Bonus positiv, in der Datei steht er mit umgekehrtem Vorzeichen
            $m->setzeStrafpunkte((-1) * intval($anfrage->text('xstrafp' . $i)));
            if ($liga->einstellungen()->mitMinuspunkten()) {
                $m->setzeStrafminuspunkte((-1) * intval($anfrage->text('xstrafm' . $i)));
            }
            $m->setzeTorkorrektur((-1) * intval($anfrage->text('xtorkorrektur1' . $i)), (-1) * intval($anfrage->text('xtorkorrektur2' . $i)));
            $m->setzeStrafeAbSpieltag(intval($anfrage->text('xstrafdat' . $i)));
        }
    }
}
