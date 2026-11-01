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

use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Userverwaltung des Tippspiels (todo=tippuser, nur Hauptadmin): alle Tipper mit Status,
 * Name, Verein und letztem Tipp, sortierbar (tipper_sort, tipper_sort_direction); del=<Nick>
 * loescht einen Tipper samt seinen Tipps. Bis 4.5 lmo-admintippuser.php.
 */
final class AdminTipperController implements Controller
{
    /** @param string $daten Datenordner des Tippspiels (daten/tipp) */
    public function __construct(private string $daten)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'tippuser' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $a = $kontext->anfrage;
        $cfg = $kontext->konfiguration->addon('tipp');
        $tippordner = $this->daten . '/' . (string) ($cfg['dirtipp'] ?? '');
        $datei = Tipperdatei::lies($this->daten . '/' . (string) ($cfg['tippauthtxt'] ?? 'lmo-tippauth.txt'));
        $meldung = '';
        $del = $a->text('del');
        if ($del != '') {
            $tipper = null;
            foreach ($datei->alle() as $einer) {
                if ($del == $einer->nick()) {
                    $tipper = $einer;
                    break;
                }
            }
            if ($tipper !== null) {
                // seine Tipps: <Liga>_<Nick>.tip (bis 4.5 jede Datei, die auf <Nick>.tip endete, mit "ter"
                // also auch die Tipps von "tester")
                foreach (scandir(rtrim($tippordner, '/')) ?: [] as $tippdatei) {
                    if (substr($tippdatei, -5 - strlen($tipper->nick())) == '_' . $tipper->nick() . '.tip') {
                        @unlink($tippordner . $tippdatei);
                    }
                }
                $datei = $datei->ohne($tipper->nick());
                $meldung = $datei->speichere()
                    ? Meldung::html($kontext->pfade->bilder, $kontext->text[138] ?? '')
                    : Meldung::html($kontext->pfade->bilder, $kontext->text[283] ?? '', true);
            }
        }

        $sortierung = $a->gesetzt('tipper_sort') ? $a->text('tipper_sort') : 'id';
        $zeilen = [];
        foreach ($datei->alle() as $nr => $tipper) {
            // letzter Tipp: die juengste Tippdatei des Tippers (<Liga>_<Nick>.tip)
            $letzter = 0;
            foreach (scandir(rtrim($tippordner, '/')) ?: [] as $tippdatei) {
                if (substr($tippdatei, -5 - strlen($tipper->nick())) == '_' . $tipper->nick() . '.tip') {
                    $letzter = max($letzter, (int) filemtime($tippordner . $tippdatei));
                }
            }
            $name = $tipper->name();
            $leer = strpos($name, ' ');
            $zeilen[] = [
                'id' => $nr + 1,
                'nick' => $tipper->nick(),
                // Vorname&nbsp;Nachname, am ersten Leerzeichen getrennt; ohne Leerzeichen der ganze Name
                // (bis 4.5 fehlte dann der erste Buchstabe)
                'name' => $leer === false ? $name : substr($name, 0, $leer) . '&nbsp;' . substr($name, $leer + 1),
                'email' => $tipper->email(),
                'team' => $tipper->verein(),
                'ltipp' => $letzter,
                'freig' => $tipper->feld(2),
            ];
        }
        usort($zeilen, self::vergleich($sortierung));
        if ($a->text('tipper_sort_direction', 'asc') == 'desc') {
            $zeilen = array_reverse($zeilen);
        }

        return new Seite($meldung . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'admin_tipper.html.twig', [
            'text' => $kontext->text,
            't' => $kontext->texteVon('tipp'),
            'menue' => AdminMenue::html($kontext, 'tippuser'),
            'adresse' => $kontext->adresse,
            'lmo' => $kontext->pfade->adresse,
            'bilder' => $kontext->pfade->bilder,
            'sortierung' => $sortierung,
            'zeilen' => array_map(static fn (array $z): array => $z + ['zeit' => $z['ltipp'] > 0 ? date('d.m.Y H:i', $z['ltipp']) : ''], $zeilen),
        ]));
    }

    /**
     * Vergleich nach einer Spalte (bis 4.5 cmp()): Zahlen nach Wert, sonst natuerlich ohne Akzente.
     *
     * @return \Closure(array<string, int|string>, array<string, int|string>): int
     */
    private static function vergleich(string $spalte): \Closure
    {
        $ohneAkzente = static fn (string $s): string => strtr($s, '¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ', 'YuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');

        return static function (array $a, array $b) use ($spalte, $ohneAkzente): int {
            $x = $a[$spalte] ?? '';
            $y = $b[$spalte] ?? '';
            if (is_numeric($x) && is_numeric($y)) {
                return $x == $y ? 0 : ($x > $y ? 1 : -1);
            }

            return strnatcasecmp($ohneAkzente(is_scalar($x) ? (string) $x : ''), $ohneAkzente(is_scalar($y) ? (string) $y : ''));
        };
    }
}
