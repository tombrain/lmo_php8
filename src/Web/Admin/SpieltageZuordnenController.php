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
use DateTime;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Partien anderen Spieltagen zuordnen (todo=edit&st=-10, nur Hauptadmin und nur in einer Liga;
 * sonst bleibt die Seite leer): je Spieltag seine Partien mit der Auswahl des Spieltags und die
 * spielfreien Mannschaften. Jede Auswahl sp_<Spieltag>_<Heim>_<Gast>, die auf einen anderen
 * Spieltag zeigt, verschiebt die Partie dorthin. Verschoben und geschrieben wird noch ueber die
 * Classlib (Bruecke in lmo-adminmain.php); die Anzeige zeigt die Liga so, wie die Classlib sie
 * liest. Bis 4.5 lmo-adminrounds.php.
 */
final class SpieltageZuordnenController implements Controller
{
    /**
     * @param Closure(string, list<array{int, int, int, int}>): array{string, list<array{int, int, int, int, string, string}>, ?Liga} $verschiebe
     *        verschiebt in der Datei die Partien (alter Spieltag, neuer Spieltag, Heim, Gast) und schreibt sie;
     *        zurueck kommen die Ausgabe dabei, die gelungenen Verschiebungen (dazu die Namen von Heim und Gast)
     *        und die Liga, wie sie danach in der Datei steht (null ohne Verschiebung)
     */
    public function __construct(private Closure $verschiebe)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -10 && $kontext->sitzung->zahl('lmouserok') >= 1;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga leer, ebenso fuer Hilfsadmins und im Pokal: dort wuerde ein einzelnes Spiel
        // aus seiner Paarung gerissen (bis 4.5 ging das)
        if ($geladen === null || $kontext->sitzung->zahl('lmouserok') != 2 || $geladen->liga->einstellungen()->istPokal()) {
            return new Seite('');
        }
        $text = $kontext->text;
        $liga = $geladen->liga;

        // jede Auswahl, die auf einen anderen Spieltag zeigt, in der Reihenfolge des Formulars
        $wuensche = [];
        foreach ($kontext->anfrage->alle() as $name => $wert) {
            $teile = preg_split('#_#', $name);
            if ($teile !== false && count($teile) == 4 && $teile[0] == 'sp' && is_scalar($wert) && $teile[1] != $wert) {
                $wuensche[] = [(int) $teile[1], (int) $wert, (int) $teile[2], (int) $teile[3]];
            }
        }
        $ausgabe = '';
        $meldung = '';
        $markiert = [];
        if ($wuensche !== []) {
            [$ausgabe, $verschoben, $neu] = ($this->verschiebe)($geladen->datei, $wuensche);
            foreach ($verschoben as [$alt, $nach, $heim, $gast, $heimName, $gastName]) {
                // je Verschiebung eine Zeile (bis 4.5 nur die letzte)
                $meldung .= ($text[5007] ?? '') . ' ' . $heimName . ' - ' . $gastName . ' ' . ($text[5004] ?? '') . ' ' . $alt . '. ' . ($text[5005] ?? '') . " $nach . " . ($text[5010] ?? '') . ' ' . ($text[5006] ?? '') . '.<BR>';
                $markiert[] = $heim . '-' . $gast;
            }
            if ($neu !== null) {
                $liga = $neu;
            }
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_spieltage_zuordnen.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'navigation' => LigaNavigation::fuer($kontext, $liga, 'edit', '-10'),
            'name' => $liga->einstellungen()->name(),
            'datei' => $geladen->datei,
            'st' => $kontext->anfrage->text('st'),
            'ausgabe' => $ausgabe,
            'meldung' => $meldung != '' ? Meldung::html($kontext->pfade->bilder, $meldung) : '',
        ] + self::spieltage($liga, $markiert)));
    }

    /**
     * Die Spieltage, wie die Classlib die Liga liest: Spieltage, solange es sie in der Datei gibt;
     * je Spieltag seine Partien; Mannschaften nur mit einer Nummer bis zur Anzahl.
     *
     * @param list<string> $markiert "Heim-Gast" der gerade verschobenen Partien
     * @return array{spieltage: list<array<string, mixed>>, anzahl: int}
     */
    private static function spieltage(Liga $liga, array $markiert): array
    {
        $namen = [];
        foreach ($liga->mannschaften as $nr => $mannschaft) {
            $namen[$nr] = (string) $mannschaft->name;
        }
        $team = static fn (int $nr): ?int => $nr > 0 && $nr <= count($namen) ? $nr : null;
        $spieltage = [];
        for ($r = 1; isset($liga->spieltage[$r]) && ($liga->spieltage[$r]->werte !== [] || $liga->spieltage[$r]->partien !== []); $r++) {
            $spieltag = $liga->spieltage[$r];
            $zeilen = [];
            $dabei = [];
            for ($p = 1; isset($spieltag->partien[$p]) && $spieltag->partien[$p]->heim !== null; $p++) {
                $partie = $spieltag->partien[$p];
                $spiel = $partie->spiele[1] ?? [];
                $heim = $team((int) $partie->heim);
                $gast = $team((int) $partie->gast);
                $dabei[] = $heim;
                $dabei[] = $gast;
                $hTore = $spiel['GA'] ?? '';
                $gTore = $spiel['GB'] ?? '';
                if ($hTore == -1 and $gTore == -1) {
                    $hTore = '__';
                    $gTore = '__';
                } elseif ($hTore < -1 or $gTore < -1) {
                    $hTore = 0;
                    $gTore = 0;
                }
                $zeit = $spiel['AT'] ?? '';
                $anstoss = '';
                if (is_numeric($zeit) && $zeit > 0) {
                    $dt = (new DateTime())->setTimestamp((int) $zeit);
                    $anstoss = $dt->format('d.m.Y') . ' ' . $dt->format('H:i');
                }
                $zeilen[] = [
                    'anstoss' => $anstoss === '' ? ' ' : $anstoss,
                    'heim' => $heim,
                    'gast' => $gast,
                    'heimName' => $heim !== null ? $namen[$heim] : '',
                    'gastName' => $gast !== null ? $namen[$gast] : '',
                    'toreHeim' => (string) $hTore,
                    'toreGast' => (string) $gTore,
                    'markiert' => in_array($heim . '-' . $gast, $markiert),
                ];
            }
            // die Mannschaften ohne Partie an diesem Spieltag
            $spielfrei = [];
            foreach ($namen as $nr => $name) {
                if (!in_array($nr, $dabei)) {
                    $spielfrei[] = $name;
                }
            }
            $von = $spieltag->ersterTagText();
            $bis = $spieltag->letzterTagText();
            $spieltage[] = [
                'nr' => $r,
                'vonBis' => $von != '' && $bis != '' ? $von . ' - ' . $bis : $von . $bis,
                'zeilen' => $zeilen,
                'spielfrei' => $spielfrei,
            ];
        }

        return ['spieltage' => $spieltage, 'anzahl' => count($spieltage)];
    }
}
