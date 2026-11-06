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

namespace Lmo\Web;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Spieltag;
use Lmo\Liga\Spiel;
use Lmo\Liga\Tabelle;
use Lmo\Liga\Tabellenzeile;

/**
 * Die Druckansicht einer Liga als HTML-Dateien im Ausgabeordner (bis 4.5 lmo-savehtml.php und
 * lmo-savehtml1.php), geschrieben beim Speichern (einsavehtml):
 *  - <liga>-st.html: der gespeicherte Spieltag, die Tabelle bis dahin und der folgende Spieltag
 *  - <liga>-sp.html: der ganze Spielplan und die Tabelle
 * Vorlagen druckansicht_spieltag.html.twig und druckansicht_spielplan.html.twig. Im Pokal nichts.
 * Die Links darauf zeigt Lmo\Web\Seiten\Druckansicht.
 */
final class Druckdateien
{
    /**
     * @param string $ausgabeordner Ordner der Dateien, mit / am Ende
     * @param array<int|string, mixed> $text Sprachtexte wie im alten Code (mit 'date')
     * @param string $datumsformat Vorgabe fuer das Datum der Anstosszeit (defdateformat)
     */
    public function __construct(
        private string $ausgabeordner,
        private array $text,
        private Bilder $bilder,
        private string $datumsformat,
        private string $vorlagen
    ) {
    }

    /**
     * Schreibt die Dateien; die zum Spieltag nur, wenn einer gewaehlt ist.
     *
     * @param string $datei Ligadatei relativ zum Ligenordner
     * @param string $st der gespeicherte Spieltag ('' ohne)
     */
    public function schreibe(Liga $liga, string $datei, string $st): void
    {
        $e = $liga->einstellungen();
        if ($e->istPokal()) {
            return;
        }
        $spieltag = (int) $st;
        // Tabelle bis zum gespeicherten Spieltag, sonst bis zum aktuellen; fuehrt die Liga ein
        // Handicap, gilt die Reihenfolge der Tabellenkorrektur des aktuellen Spieltags
        $tabelle = Tabelle::fuerAnsicht($liga, Tabelle::GESAMT, $spieltag > 0 ? $spieltag : $e->aktuellerSpieltag());
        $reihenfolge = $tabelle->reihenfolge;
        if ($e->mitHandicap()) {
            $reihenfolge = $tabelle->reihenfolgeNachHandicap($liga->spieltage[$e->aktuellerSpieltag()]->werte['HS'] ?? '0');
        }
        $daten = [
            'text' => $this->text,
            'name' => $e->name(),
            'tabelle' => $this->tabelle($liga, $tabelle, $reihenfolge),
            'minuspunkte' => $e->mitMinuspunkten(),
        ];
        $pfad = $this->ausgabeordner . basename($datei);

        // wie bis 4.5 nur bei einem Spieltag, der als Zahl groesser 0 ist
        if (is_numeric($st) && $st > 0) {
            file_put_contents($pfad . '-st.html', Vorlagen::zeige($this->vorlagen, 'druckansicht_spieltag.html.twig', $daten + $this->spieltag($liga, $st)));
        }
        file_put_contents($pfad . '-sp.html', Vorlagen::zeige($this->vorlagen, 'druckansicht_spielplan.html.twig', $daten + ['spieltage' => $this->spielplan($liga)]));
    }

    /**
     * Der gespeicherte und der folgende Spieltag. Wer spielfrei ist, rechnet der folgende wie bis
     * 4.5 auf den Partien des gespeicherten weiter.
     *
     * @return array<string, mixed>
     */
    private function spieltag(Liga $liga, string $st): array
    {
        $e = $liga->einstellungen();
        $nr = (int) $st;
        $belegt = [];
        $spiele = $this->spiele($liga, $nr, '', $belegt);
        // gibt es an diesem Spieltag ueberhaupt eine Partie?
        $angesetzt = array_filter(self::sortiert($liga, $nr), static fn (Spiel $s): bool => $s->heim > 0) !== [];
        $daten = [
            'st' => $st,
            'angesetzt' => $angesetzt,
            'datum' => $this->datum($liga->spieltage[$nr] ?? new Spieltag()),
            'spiele' => $spiele,
            'spielfrei' => $this->spielfrei($liga, $belegt),
            'letzter' => $nr === $e->spieltage(),
            'folge' => null,
        ];
        if (!$daten['letzter'] && $angesetzt) {
            $daten['folge'] = [
                'st' => $nr + 1,
                'datum' => $this->datum($liga->spieltage[$nr + 1] ?? new Spieltag()),
                'spiele' => $this->spiele($liga, $nr + 1, '&nbsp;', $belegt),
                'spielfrei' => $this->spielfrei($liga, $belegt),
            ];
        }

        return $daten;
    }

    /**
     * Alle Spieltage mit Partien.
     *
     * @return list<array<string, mixed>>
     */
    private function spielplan(Liga $liga): array
    {
        $spieltage = [];
        for ($nr = 1; $nr <= $liga->einstellungen()->spieltage(); $nr++) {
            $belegt = [];
            $spiele = $this->spiele($liga, $nr, '', $belegt);
            $spieltage[] = [
                'st' => $nr,
                'angesetzt' => array_filter(self::sortiert($liga, $nr), static fn (Spiel $s): bool => $s->heim > 0) !== [],
                'datum' => $this->datum($liga->spieltage[$nr] ?? new Spieltag()),
                'spiele' => $spiele,
                'spielfrei' => $this->spielfrei($liga, $belegt),
            ];
        }

        return $spieltage;
    }

    /**
     * Die Spiele eines Spieltags mit beiden Mannschaften, sortiert nach Anstoss.
     *
     * @param string $ohneAnstoss was ohne Anstosszeit in der Spalte steht
     * @param array<int, array{int, int}> $belegt Partie => Heim und Gast, fuer die Spielfreien
     * @return list<array<string, mixed>>
     */
    private function spiele(Liga $liga, int $nr, string $ohneAnstoss, array &$belegt): array
    {
        $e = $liga->einstellungen();
        $faktor = $e->torfaktor();
        $spiele = [];
        foreach (self::sortiert($liga, $nr) as $spiel) {
            if ($spiel->heim <= 0 || $spiel->gast <= 0) {
                continue;
            }
            $belegt[$spiel->partie] = [$spiel->heim, $spiel->gast];
            $heim = $spiel->heimMannschaft()->anzeigename();
            $gast = $spiel->gastMannschaft()->anzeigename();
            $spiele[] = [
                'anstoss' => $spiel->anstoss !== null ? strtr(date($e->datumsformat($this->datumsformat), $spiel->anstoss), $this->monateUndTage()) : $ohneAnstoss,
                'heim' => $heim,
                'gast' => $gast,
                'heimBild' => $this->bilder->bild($heim, 'teams'),
                'gastBild' => $this->bilder->bild($gast, 'teams'),
                'toreHeim' => $spiel->toreHeimText($faktor),
                'toreGast' => $spiel->toreGastText($faktor),
                'gegenBeide' => $spiel->wertung === 3,
            ];
        }

        return $spiele;
    }

    /**
     * Die Mannschaften ohne Spiel, wenn nicht alle an jedem Spieltag spielen; null sonst.
     *
     * @param array<int, array{int, int}> $belegt
     * @return list<string>|null
     */
    private function spielfrei(Liga $liga, array $belegt): ?array
    {
        $e = $liga->einstellungen();
        if (($e->mannschaften() - ($e->spieltage() / 2 + 1)) == 0) {
            return null;
        }
        $spielen = array_merge(array_column($belegt, 0), array_column($belegt, 1));
        $frei = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            if (!in_array($nr, $spielen, true)) {
                $frei[] = $mannschaft->anzeigename();
            }
        }

        return $frei;
    }

    /** @return list<Spiel> */
    private static function sortiert(Liga $liga, int $nr): array
    {
        $spiele = iterator_to_array($liga->spiele($nr), false);
        usort($spiele, static fn (Spiel $a, Spiel $b): int => ($a->anstoss ?? 0) <=> ($b->anstoss ?? 0));

        return $spiele;
    }

    /** @return array{string, string}|null erster und letzter Tag */
    private function datum(Spieltag $spieltag): ?array
    {
        return $spieltag->ersterTagText() !== '' ? [$spieltag->ersterTagText(), $spieltag->letzterTagText()] : null;
    }

    /**
     * Die Zeilen der Tabelle.
     *
     * @param array<int, int> $reihenfolge
     * @return list<array<string, mixed>>
     */
    private function tabelle(Liga $liga, Tabelle $tabelle, array $reihenfolge): array
    {
        $e = $liga->einstellungen();
        $zeilen = [];
        for ($i = 0; $i < $e->mannschaften(); $i++) {
            $nr = $reihenfolge[$i] ?? 0;
            $zeile = $tabelle->zeilen[$nr] ?? new Tabellenzeile(0);
            $name = $nr === 0 ? '___' : $liga->mannschaftsname($nr);
            $zeilen[] = [
                'platz' => $i + 1,
                'name' => $name,
                'bild' => $this->bilder->bild($name, 'teams'),
                'spiele' => $zeile->spiele,
                'punkte' => Darstellung::mitFaktor($zeile->punkte, $e->punktfaktor()),
                'minuspunkte' => Darstellung::mitFaktor($zeile->minuspunkte, $e->punktfaktor()),
                'tore' => Darstellung::mitFaktor($zeile->tore, $e->torfaktor()),
                'gegentore' => Darstellung::mitFaktor($zeile->gegentore, $e->torfaktor()),
                'differenz' => Darstellung::mitFaktor($zeile->tordifferenz(), $e->torfaktor()),
            ];
        }

        return $zeilen;
    }

    /** @return array<string, string> englische Namen der Tage und Monate => Sprachtexte */
    private function monateUndTage(): array
    {
        $namen = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
            'January', 'February', 'March', 'April', 18 => null, 'June', 'July', 'August', 'September', 'October', 'November', 'December',
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $datum = is_array($this->text['date'] ?? null) ? $this->text['date'] : [];
        $paare = [];
        foreach ($namen as $i => $name) {
            // "May" steht nur als Kurzname drin (wie bis 4.5)
            if ($name !== null) {
                $paare[$name] = (string) ($datum[$i] ?? '');
            }
        }

        return $paare;
    }
}
